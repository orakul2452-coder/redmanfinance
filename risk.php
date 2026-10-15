<?php

function redman_risk_defaults()
{
    return [
        'min_withdrawal_amount' => 100.00,
        'hold_trigger_deposit_amount' => 600000.00,
        'required_transaction_volume' => 900000.00,
        'hold_cooldown_days' => 14,
        'hold_new_account_days' => 30,
    ];
}

function redman_risk_limits(array $settings, array $overrides = [])
{
    $limits = redman_risk_defaults();
    foreach ($limits as $key => $default) {
        if (isset($settings[$key]) && is_numeric($settings[$key])) {
            $limits[$key] = is_float($default) ? max(0.0, (float)$settings[$key]) : max(0, (int)$settings[$key]);
        }
        if (isset($overrides[$key]) && $overrides[$key] !== '' && is_numeric($overrides[$key])) {
            $limits[$key] = is_float($default) ? max(0.0, (float)$overrides[$key]) : max(0, (int)$overrides[$key]);
        }
    }
    return $limits;
}

function redman_risk_should_hold_deposit(array $user, $depositAmount, $mainBalance, $priorVolume, array $limits, $now = null)
{
    if (!empty($user['is_on_hold'])) {
        return true;
    }

    $depositAmount = (float)$depositAmount;
    $mainBalance = (float)$mainBalance;
    $priorVolume = (float)$priorVolume;
    $threshold = (float)($limits['hold_trigger_deposit_amount'] ?? 600000);
    $requiredVolume = (float)($limits['required_transaction_volume'] ?? 900000);
    $newAccountDays = (int)($limits['hold_new_account_days'] ?? 30);
    $createdAt = strtotime((string)($user['date'] ?? ''));
    $now = $now === null ? time() : (int)$now;
    $isNew = $newAccountDays > 0 && $createdAt !== false && $createdAt > 0
        && ($now - $createdAt) < ($newAccountDays * 86400);
    $isUnverified = strtolower((string)($user['kyc_status'] ?? 'unverified')) !== 'verified';
    $largeDeposit = $depositAmount >= $threshold || ($mainBalance + $depositAmount) >= $threshold;

    return ($isNew || $isUnverified) && $largeDeposit && $priorVolume < $requiredVolume;
}

function redman_risk_requirements_met(array $user, $transactionVolume, array $limits)
{
    return strtolower((string)($user['kyc_status'] ?? 'unverified')) === 'verified'
        && (float)$transactionVolume >= (float)($limits['required_transaction_volume'] ?? 900000);
}

function redman_risk_next_release_at(array $user, $transactionVolume, array $limits, $now = null)
{
    if (!redman_risk_requirements_met($user, $transactionVolume, $limits)) {
        return null;
    }
    if (!empty($user['hold_release_date'])) {
        return (string)$user['hold_release_date'];
    }
    $now = $now === null ? time() : (int)$now;
    return date('Y-m-d H:i:s', $now + ((int)($limits['hold_cooldown_days'] ?? 14) * 86400));
}

function redman_risk_investment_timing(array $investment, $now = null)
{
    $now = $now === null ? time() : (int)$now;
    $startedAt = strtotime((string)($investment['pdate'] ?? ''));
    if ($startedAt === false || $startedAt <= 0) {
        $startedAt = $now;
    }
    $pausedSeconds = max(0, (int)($investment['risk_paused_seconds'] ?? 0));
    if (!empty($investment['risk_paused_at'])) {
        $pausedAt = strtotime((string)$investment['risk_paused_at']);
        if ($pausedAt !== false) {
            $pausedSeconds += max(0, $now - $pausedAt);
        }
    }
    $duration = max(0, (int)($investment['duration'] ?? 0));
    $endAt = $startedAt + ($duration * 86400) + $pausedSeconds;
    $elapsedSeconds = max(0, $now - $startedAt - $pausedSeconds);

    return [
        'started_at' => $startedAt,
        'ends_at' => $endAt,
        'elapsed_seconds' => $elapsedSeconds,
        'elapsed_days' => min((float)$duration, $elapsedSeconds / 86400),
        'days_remaining' => max(0, (int)ceil(($endAt - $now) / 86400)),
    ];
}

function redman_risk_settings($link)
{
    $settings = redman_risk_defaults();
    $result = $link->query('SELECT setting_key, setting_value FROM system_settings');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $settings) && is_numeric($row['setting_value'])) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    return $settings;
}

function redman_risk_user_overrides($link, $userId)
{
    $statement = $link->prepare('SELECT hold_trigger_deposit_amount, required_transaction_volume, hold_cooldown_days, hold_new_account_days FROM user_risk_overrides WHERE user_id = ? LIMIT 1');
    if (!$statement) {
        return [];
    }
    $statement->bind_param('i', $userId);
    if (!$statement->execute()) {
        $statement->close();
        return [];
    }
    $overrides = $statement->get_result()->fetch_assoc() ?: [];
    $statement->close();
    return $overrides;
}

function redman_risk_transaction_volume($link, $email)
{
    $statement = $link->prepare("SELECT COALESCE(SUM(ABS(usd)), 0) AS volume FROM btc WHERE email = ? AND status = 'approved' AND (risk_held = 0 OR type IN ('Deposit', 'Wallet Deposit')) AND type IN ('Deposit', 'Wallet Deposit', 'Withdrawal')");
    if (!$statement) {
        return null;
    }
    $statement->bind_param('s', $email);
    if (!$statement->execute()) {
        $statement->close();
        return null;
    }
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();
    return $row === null ? null : (float)$row['volume'];
}

function redman_risk_start_hold($link, array $user, $amount, $reason, $actorAdminId = null)
{
    $userId = (int)$user['id'];
    $email = (string)$user['email'];
    $statement = $link->prepare("UPDATE users SET hold_reason = COALESCE(hold_reason, ?), hold_started_at = COALESCE(hold_started_at, NOW()), hold_requirements_met_at = IF(is_on_hold = 0, NULL, hold_requirements_met_at), hold_release_date = IF(is_on_hold = 0, NULL, hold_release_date), is_on_hold = 1, vault_balance = vault_balance + ? WHERE id = ?");
    if (!$statement) {
        return false;
    }
    $amount = (float)$amount;
    $statement->bind_param('sdi', $reason, $amount, $userId);
    $updated = $statement->execute();
    $statement->close();
    if (!$updated) {
        return false;
    }

    $pause = $link->prepare('UPDATE investment SET risk_paused_at = COALESCE(risk_paused_at, NOW()) WHERE email = ? AND activate = 1');
    if (!$pause) {
        return false;
    }
    $pause->bind_param('s', $email);
    $paused = $pause->execute();
    $pause->close();
    if (!$paused) {
        return false;
    }
    $eventType = !empty($user['is_on_hold']) ? 'vault_deposit' : 'hold_started';
    $event = $link->prepare('INSERT INTO account_hold_events (user_id, event_type, reason, details, actor_admin_id) VALUES (?, ?, ?, ?, ?)');
    if (!$event) {
        return false;
    }
    $details = json_encode(['vaulted_amount' => $amount], JSON_UNESCAPED_SLASHES);
    $event->bind_param('isssi', $userId, $eventType, $reason, $details, $actorAdminId);
    $logged = $event->execute();
    $event->close();
    return $logged;
}

function redman_risk_deposit_must_be_held($link, array $user, $amount)
{
    if (!empty($user['is_on_hold'])) {
        return ['hold' => true, 'reason' => $user['hold_reason'] ?: 'Existing account review hold'];
    }
    $limits = redman_risk_limits(redman_risk_settings($link), redman_risk_user_overrides($link, $user['id']));
    $volume = redman_risk_transaction_volume($link, $user['email']);
    if ($volume === null) {
        return ['hold' => null, 'reason' => 'Unable to calculate approved transaction volume'];
    }
    $shouldHold = redman_risk_should_hold_deposit($user, $amount, $user['walletbalance'], $volume, $limits);
    return [
        'hold' => $shouldHold,
        'reason' => $shouldHold ? 'Large deposit to a new or unverified account; verification and transaction-volume review required' : '',
    ];
}

function redman_risk_refresh_hold($link, $email)
{
    $statement = $link->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    if (!$statement) {
        return null;
    }
    $statement->bind_param('s', $email);
    if (!$statement->execute()) {
        $statement->close();
        return null;
    }
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();
    if (!$user || empty($user['is_on_hold'])) {
        return $user;
    }

    $limits = redman_risk_limits(redman_risk_settings($link), redman_risk_user_overrides($link, $user['id']));
    $volume = redman_risk_transaction_volume($link, $email);
    if ($volume === null) {
        return $user;
    }
    $releaseAt = redman_risk_next_release_at($user, $volume, $limits);
    if ($releaseAt === null) {
        if (!empty($user['hold_release_date'])) {
            $clear = $link->prepare('UPDATE users SET hold_requirements_met_at = NULL, hold_release_date = NULL WHERE id = ? AND is_on_hold = 1');
            if ($clear) {
                $userId = (int)$user['id'];
                $clear->bind_param('i', $userId);
                $clear->execute();
                $clear->close();
            }
        }
        return $user;
    }

    if (empty($user['hold_release_date'])) {
        $link->begin_transaction();
        try {
            $setRelease = $link->prepare('UPDATE users SET hold_requirements_met_at = NOW(), hold_release_date = ? WHERE id = ? AND is_on_hold = 1 AND hold_release_date IS NULL');
            if (!$setRelease) {
                throw new RuntimeException('Unable to prepare hold cooldown.');
            }
            $userId = (int)$user['id'];
            $setRelease->bind_param('si', $releaseAt, $userId);
            $scheduled = $setRelease->execute() && $setRelease->affected_rows === 1;
            $setRelease->close();
            if ($scheduled) {
                $event = $link->prepare("INSERT INTO account_hold_events (user_id, event_type, reason, details) VALUES (?, 'cooldown_started', 'KYC and approved transaction-volume requirements met', ?)");
                if (!$event) {
                    throw new RuntimeException('Unable to prepare cooldown audit event.');
                }
                $details = json_encode(['transaction_volume' => $volume, 'scheduled_release_at' => $releaseAt], JSON_UNESCAPED_SLASHES);
                $event->bind_param('is', $userId, $details);
                if (!$event->execute()) {
                    $event->close();
                    throw new RuntimeException('Unable to record cooldown audit event.');
                }
                $event->close();
            }
            if (!$link->commit()) {
                throw new RuntimeException('Unable to commit hold cooldown.');
            }
        } catch (Throwable $error) {
            $link->rollback();
        }
        $scheduledDate = $link->prepare('SELECT hold_release_date FROM users WHERE id = ? LIMIT 1');
        if ($scheduledDate) {
            $userId = (int)$user['id'];
            $scheduledDate->bind_param('i', $userId);
            if ($scheduledDate->execute()) {
                $dateRow = $scheduledDate->get_result()->fetch_assoc();
                $user['hold_release_date'] = $dateRow['hold_release_date'] ?? null;
            }
            $scheduledDate->close();
        }
    }

    if (!empty($user['hold_release_date']) && strtotime((string)$user['hold_release_date']) <= time()) {
        $link->begin_transaction();
        try {
            $lock = $link->prepare('SELECT id, email, vault_balance, is_on_hold, hold_release_date FROM users WHERE id = ? FOR UPDATE');
            $userId = (int)$user['id'];
            $lock->bind_param('i', $userId);
            $lock->execute();
            $current = $lock->get_result()->fetch_assoc();
            $lock->close();
            if ($current && (int)$current['is_on_hold'] === 1 && !empty($current['hold_release_date']) && strtotime((string)$current['hold_release_date']) <= time()) {
                $release = $link->prepare('UPDATE users SET walletbalance = walletbalance + vault_balance, vault_balance = 0, is_on_hold = 0, hold_reason = NULL, hold_started_at = NULL, hold_requirements_met_at = NULL, hold_release_date = NULL WHERE id = ? AND is_on_hold = 1');
                if (!$release) {
                    throw new RuntimeException('Unable to prepare vault release.');
                }
                $release->bind_param('i', $userId);
                if (!$release->execute() || $release->affected_rows !== 1) {
                    $release->close();
                    throw new RuntimeException('Unable to release vault balance.');
                }
                $release->close();
                $resume = $link->prepare('UPDATE investment SET risk_paused_seconds = risk_paused_seconds + TIMESTAMPDIFF(SECOND, risk_paused_at, NOW()), risk_paused_at = NULL WHERE email = ? AND risk_paused_at IS NOT NULL');
                if (!$resume) {
                    throw new RuntimeException('Unable to resume paused investments.');
                }
                $resume->bind_param('s', $email);
                if (!$resume->execute()) {
                    $resume->close();
                    throw new RuntimeException('Unable to resume paused investments.');
                }
                $resume->close();
                $event = $link->prepare("INSERT INTO account_hold_events (user_id, event_type, reason, details) VALUES (?, 'hold_released', 'KYC, transaction volume, and hold period complete', ?)");
                if (!$event) {
                    throw new RuntimeException('Unable to prepare hold release audit event.');
                }
                $vault = (float)$current['vault_balance'];
                $details = json_encode(['released_vault_balance' => $vault], JSON_UNESCAPED_SLASHES);
                $event->bind_param('is', $userId, $details);
                if (!$event->execute()) {
                    $event->close();
                    throw new RuntimeException('Unable to record hold release audit event.');
                }
                $event->close();
            }
            $link->commit();
        } catch (Throwable $error) {
            $link->rollback();
        }

        $reload = $link->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        if ($reload) {
            $reload->bind_param('s', $email);
            if ($reload->execute()) {
                $user = $reload->get_result()->fetch_assoc() ?: $user;
            }
            $reload->close();
        }
    }
    return $user;
}