<?php

function redman_referral_promo_amounts(float $grossAmount, bool $hasQualifiedReferral): array
{
    $discount = $hasQualifiedReferral && $grossAmount >= 5000 ? 500.0 : 0.0;
    return ['discount' => $discount, 'payable' => max(0.0, $grossAmount - $discount)];
}

function redman_referral_promo_preview(mysqli $link, int $referredUserId, float $grossAmount): array
{
    if (redman_referral_promo_amounts($grossAmount, true)['discount'] <= 0) {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    $user = $link->prepare('SELECT referred FROM users WHERE id = ? LIMIT 1');
    if (!$user) {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }
    $user->bind_param('i', $referredUserId);
    if (!$user->execute()) {
        $user->close();
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }
    $referralChain = (string)($user->get_result()->fetch_assoc()['referred'] ?? '');
    $user->close();
    $directReferrerCode = trim(explode('_', $referralChain)[0]);
    if ($directReferrerCode === '') {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    $referrerQuery = $link->prepare('SELECT id, email, fname, lname, username FROM users WHERE refcode = ? LIMIT 1');
    if (!$referrerQuery) {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }
    $referrerQuery->bind_param('s', $directReferrerCode);
    if (!$referrerQuery->execute()) {
        $referrerQuery->close();
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }
    $referrer = $referrerQuery->get_result()->fetch_assoc();
    $referrerQuery->close();
    if (!$referrer || (int)$referrer['id'] === $referredUserId) {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    $rewardQuery = $link->prepare('SELECT status FROM referral_cashback_rewards WHERE referred_user_id = ? LIMIT 1');
    if (!$rewardQuery) {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }
    $rewardQuery->bind_param('i', $referredUserId);
    if (!$rewardQuery->execute()) {
        $rewardQuery->close();
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }
    $reward = $rewardQuery->get_result()->fetch_assoc();
    $rewardQuery->close();
    if ($reward && strtolower((string)$reward['status']) !== 'released') {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    $amounts = redman_referral_promo_amounts($grossAmount, true);
    return ['eligible' => true, 'discount' => $amounts['discount'], 'payable' => $amounts['payable'], 'referrer' => $referrer];
}

function redman_referral_promo_reserve(mysqli $link, int $referredUserId, float $grossAmount, int $btcId = 0): array
{
    if (redman_referral_promo_amounts($grossAmount, true)['discount'] <= 0) {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    $lockUser = $link->prepare('SELECT referred FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$lockUser) {
        throw new RuntimeException('Unable to lock referral account.');
    }
    $lockUser->bind_param('i', $referredUserId);
    if (!$lockUser->execute()) {
        $lockUser->close();
        throw new RuntimeException('Unable to verify referral account.');
    }
    $referralChain = (string)($lockUser->get_result()->fetch_assoc()['referred'] ?? '');
    $lockUser->close();
    $directReferrerCode = trim(explode('_', $referralChain)[0]);
    if ($directReferrerCode === '') {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    $referrerQuery = $link->prepare('SELECT id, email, fname, lname, username FROM users WHERE refcode = ? LIMIT 1 FOR UPDATE');
    if (!$referrerQuery) {
        throw new RuntimeException('Unable to load direct referrer.');
    }
    $referrerQuery->bind_param('s', $directReferrerCode);
    if (!$referrerQuery->execute()) {
        $referrerQuery->close();
        throw new RuntimeException('Unable to verify direct referrer.');
    }
    $referrer = $referrerQuery->get_result()->fetch_assoc();
    $referrerQuery->close();
    if (!$referrer || (int)$referrer['id'] === $referredUserId) {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    $rewardQuery = $link->prepare('SELECT id, status FROM referral_cashback_rewards WHERE referred_user_id = ? LIMIT 1 FOR UPDATE');
    if (!$rewardQuery) {
        throw new RuntimeException('Unable to check previous cashback rewards.');
    }
    $rewardQuery->bind_param('i', $referredUserId);
    if (!$rewardQuery->execute()) {
        $rewardQuery->close();
        throw new RuntimeException('Unable to check previous cashback rewards.');
    }
    $reward = $rewardQuery->get_result()->fetch_assoc();
    $rewardQuery->close();
    if ($reward && strtolower((string)$reward['status']) !== 'released') {
        return ['eligible' => false, 'discount' => 0.0, 'referrer' => null];
    }

    if ($reward) {
        $reserve = $link->prepare("UPDATE referral_cashback_rewards SET referrer_user_id = ?, btc_id = NULLIF(?, 0), gross_amount = ?, status = 'reserved', investment_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'released'");
        if (!$reserve) {
            throw new RuntimeException('Unable to reserve referral cashback.');
        }
        $rewardId = (int)$reward['id'];
        $reserve->bind_param('iidi', $referrer['id'], $btcId, $grossAmount, $rewardId);
    } else {
        $reserve = $link->prepare("INSERT INTO referral_cashback_rewards (referred_user_id, referrer_user_id, btc_id, gross_amount, discount_amount, referrer_reward, status) VALUES (?, ?, NULLIF(?, 0), ?, 500.00, 500.00, 'reserved')");
        if (!$reserve) {
            throw new RuntimeException('Unable to reserve referral cashback.');
        }
        $reserve->bind_param('iiid', $referredUserId, $referrer['id'], $btcId, $grossAmount);
    }
    if (!$reserve->execute()) {
        $reserve->close();
        throw new RuntimeException('Unable to reserve referral cashback.');
    }
    $reserve->close();

    $amounts = redman_referral_promo_amounts($grossAmount, true);
    return ['eligible' => true, 'discount' => $amounts['discount'], 'payable' => $amounts['payable'], 'referrer' => $referrer];
}

function redman_referral_promo_bind_deposit(mysqli $link, int $referredUserId, int $btcId): bool
{
    $update = $link->prepare("UPDATE referral_cashback_rewards SET btc_id = ? WHERE referred_user_id = ? AND status = 'reserved' AND btc_id IS NULL");
    if (!$update) {
        return false;
    }
    $update->bind_param('ii', $btcId, $referredUserId);
    $success = $update->execute() && $update->affected_rows === 1;
    $update->close();
    return $success;
}

function redman_referral_promo_finalize(mysqli $link, int $referredUserId, int $investmentId, int $btcId = 0): ?array
{
    $query = $link->prepare($btcId > 0
        ? "SELECT id, referrer_user_id, referrer_reward, discount_amount, gross_amount FROM referral_cashback_rewards WHERE referred_user_id = ? AND btc_id = ? AND status = 'reserved' LIMIT 1 FOR UPDATE"
        : "SELECT id, referrer_user_id, referrer_reward, discount_amount, gross_amount FROM referral_cashback_rewards WHERE referred_user_id = ? AND btc_id IS NULL AND status = 'reserved' LIMIT 1 FOR UPDATE");
    if (!$query) {
        throw new RuntimeException('Unable to load reserved referral cashback.');
    }
    if ($btcId > 0) {
        $query->bind_param('ii', $referredUserId, $btcId);
    } else {
        $query->bind_param('i', $referredUserId);
    }
    if (!$query->execute()) {
        $query->close();
        throw new RuntimeException('Unable to load reserved referral cashback.');
    }
    $reward = $query->get_result()->fetch_assoc();
    $query->close();
    if (!$reward) {
        return null;
    }

    $credit = $link->prepare('UPDATE users SET refbonus = refbonus + ?, walletbalance = IF(is_on_hold = 1, walletbalance, walletbalance + ?), vault_balance = IF(is_on_hold = 1, vault_balance + ?, vault_balance) WHERE id = ?');
    if (!$credit) {
        throw new RuntimeException('Unable to credit direct referrer cashback.');
    }
    $rewardAmount = (float)$reward['referrer_reward'];
    $referrerId = (int)$reward['referrer_user_id'];
    $credit->bind_param('dddi', $rewardAmount, $rewardAmount, $rewardAmount, $referrerId);
    if (!$credit->execute() || $credit->affected_rows !== 1) {
        $credit->close();
        throw new RuntimeException('Unable to credit direct referrer cashback.');
    }
    $credit->close();

    $markPaid = $link->prepare("UPDATE referral_cashback_rewards SET status = 'paid', investment_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'reserved'");
    if (!$markPaid) {
        throw new RuntimeException('Unable to complete referral cashback record.');
    }
    $rewardId = (int)$reward['id'];
    $markPaid->bind_param('ii', $investmentId, $rewardId);
    if (!$markPaid->execute() || $markPaid->affected_rows !== 1) {
        $markPaid->close();
        throw new RuntimeException('Unable to complete referral cashback record.');
    }
    $markPaid->close();

    $referrerQuery = $link->prepare('SELECT email, fname, lname, username FROM users WHERE id = ? LIMIT 1');
    $referrerQuery->bind_param('i', $referrerId);
    $referrerQuery->execute();
    $referrer = $referrerQuery->get_result()->fetch_assoc();
    $referrerQuery->close();
    return $referrer ? [
        'referrer' => $referrer,
        'amount' => $rewardAmount,
        'discount' => (float)$reward['discount_amount'],
        'gross_amount' => (float)$reward['gross_amount'],
    ] : null;
}

function redman_referral_promo_release_deposit(mysqli $link, int $btcId): bool
{
    $release = $link->prepare("UPDATE referral_cashback_rewards SET status = 'released', updated_at = CURRENT_TIMESTAMP WHERE btc_id = ? AND status = 'reserved'");
    if (!$release) {
        return false;
    }
    $release->bind_param('i', $btcId);
    $success = $release->execute();
    $release->close();
    return $success;
}
