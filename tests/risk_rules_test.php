<?php
require_once __DIR__ . '/../risk.php';
require_once __DIR__ . '/../email_notifications.php';

function check($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

$defaults = redman_risk_defaults();
check($defaults['min_withdrawal_amount'] === 100.0, 'minimum withdrawal defaults to 100');
check($defaults['hold_trigger_deposit_amount'] === 600000.0, 'large deposit trigger defaults to 600000');
check($defaults['required_transaction_volume'] === 900000.0, 'required prior transaction volume defaults to 900000');
check($defaults['hold_cooldown_days'] === 14, 'cooldown defaults to fourteen days');
check($defaults['hold_new_account_days'] === 30, 'new account window defaults to thirty days');

$limits = redman_risk_limits($defaults);
$now = strtotime('2026-10-15 12:00:00');
$newUnverified = ['id' => 10, 'email' => 'new@example.test', 'date' => '2026-10-10 12:00:00', 'kyc_status' => 'unverified', 'is_on_hold' => 0];
$verifiedMature = ['id' => 11, 'email' => 'old@example.test', 'date' => '2026-01-01 00:00:00', 'kyc_status' => 'verified', 'is_on_hold' => 0];

check(redman_risk_should_hold_deposit($newUnverified, 600000, 0, 0, $limits, $now), 'single 600000 deposit holds new unverified account');
check(redman_risk_should_hold_deposit($newUnverified, 100, 599900, 0, $limits, $now), 'deposit taking projected main balance to threshold triggers hold');
check(!redman_risk_should_hold_deposit($newUnverified, 600000, 0, 900000, $limits, $now), 'qualifying prior transaction volume avoids automatic hold');
check(!redman_risk_should_hold_deposit($verifiedMature, 600000, 0, 0, $limits, $now), 'mature verified account does not meet new/unverified trigger');
$customLimits = redman_risk_limits($defaults, ['hold_trigger_deposit_amount' => '1000', 'required_transaction_volume' => '1000']);
check(!redman_risk_should_hold_deposit($newUnverified, 2000, 0, 1019.99, $customLimits, $now), 'deposit is not held when prior approved volume already meets the configured threshold');
check(redman_risk_should_hold_deposit($newUnverified, 2000, 0, 999.99, $customLimits, $now), 'deposit is held when prior approved volume is below the configured threshold');
$customerTierLimits = redman_risk_limits($defaults, ['hold_trigger_deposit_amount' => '20000', 'required_transaction_volume' => '5000']);
check(!redman_risk_should_hold_deposit($newUnverified, 20000, 0, 5000, $customerTierLimits, $now), 'admin-configured prior volume qualifies a large deposit at the exact threshold');
check(redman_risk_should_hold_deposit($newUnverified, 20000, 0, 4999.99, $customerTierLimits, $now), 'large deposit is held when prior completed volume is below the admin-configured threshold');
$reviewTierLimits = redman_risk_limits($defaults, ['hold_trigger_deposit_amount' => '2200', 'required_transaction_volume' => '2000']);
check(redman_risk_should_hold_deposit($newUnverified, 0, 2322.54, 1019.99, $reviewTierLimits, $now), 'saving review catches an unheld account whose existing balance exceeds its effective trigger while history is short');
$newUnverified['is_on_hold'] = 1;
check(redman_risk_should_hold_deposit($newUnverified, 10, 0, 9999999, $limits, $now), 'existing held account routes later incoming funds to vault');

check(!redman_risk_requirements_met($newUnverified, 900000, $limits), 'held account cannot satisfy release without verified KYC');
$newUnverified['kyc_status'] = 'verified';
check(redman_risk_requirements_met($newUnverified, 900000, $limits), 'KYC and required transaction volume satisfy prerequisites');
$releaseUser = [
    'kyc_status' => 'verified',
    'id_front' => '',
    'id_back' => '',
    'compliance_id_front_path' => 'compliance/1/front.jpg',
    'compliance_id_back_path' => 'compliance/1/back.jpg',
    'compliance_address_path' => 'compliance/1/address.jpg',
    'compliance_submitted_at' => '2026-10-16 10:00:00',
    'hold_release_date' => '2026-10-29 10:00:00',
];
$releaseChecks = redman_risk_release_checks($releaseUser, 900000, $limits, strtotime('2026-10-16 12:00:00'));
check($releaseChecks['documents_complete'] && $releaseChecks['kyc_verified'] && $releaseChecks['history_met'], 'release checks pass only with submitted documents, verified KYC, and qualifying history');
check(!$releaseChecks['cooldown_elapsed'], 'normal release remains unavailable before cooldown ends');
$releaseChecks = redman_risk_release_checks($releaseUser, 900000, $limits, strtotime('2026-10-30 10:00:00'));
check($releaseChecks['cooldown_elapsed'], 'normal release becomes eligible for staff action after cooldown expires');
$incompleteReleaseUser = $releaseUser;
$incompleteReleaseUser['compliance_address_path'] = '';
check(!redman_risk_release_checks($incompleteReleaseUser, 900000, $limits, strtotime('2026-10-30 10:00:00'))['checks_met'], 'missing proof of address blocks starting the release cooldown');
$progress = redman_risk_volume_progress(1019.99, 1000);
check($progress['met'] && $progress['remaining'] === 0.0, 'history progress reports the configured requirement met');
$progress = redman_risk_volume_progress(4999.99, 5000);
check(!$progress['met'] && abs($progress['remaining'] - 0.01) < 0.00001, 'history progress reports the exact remaining configured amount');
$releaseAt = redman_risk_next_release_at($newUnverified, 900000, $limits, $now);
check($releaseAt === date('Y-m-d H:i:s', $now + 14 * 86400), 'release deadline is fourteen days after prerequisites are met');
$newUnverified['hold_release_date'] = '2026-11-01 12:00:00';
check(redman_risk_next_release_at($newUnverified, 900000, $limits, $now) === '2026-11-01 12:00:00', 'existing hold deadline is preserved when still eligible');
check(redman_risk_next_release_at($newUnverified, 899999, $limits, $now) === null, 'volume below threshold cannot schedule release');

$timing = redman_risk_investment_timing([
    'pdate' => '2026-10-01 12:00:00',
    'duration' => 30,
    'risk_paused_seconds' => 86400,
], strtotime('2026-10-11 12:00:00'));
check($timing['elapsed_days'] == 9, 'completed pause duration is excluded from investment earnings');
check($timing['ends_at'] === strtotime('2026-11-01 12:00:00'), 'completed pause extends the investment end date');

$activePauseTiming = redman_risk_investment_timing([
    'pdate' => '2026-10-01 12:00:00',
    'duration' => 30,
    'risk_paused_seconds' => 0,
    'risk_paused_at' => '2026-10-10 12:00:00',
], strtotime('2026-10-15 12:00:00'));
check($activePauseTiming['elapsed_days'] == 9, 'active hold freezes elapsed investment earnings');
check($activePauseTiming['ends_at'] === strtotime('2026-11-05 12:00:00'), 'active hold continuously extends the investment end date');

$overrides = redman_risk_limits($defaults, ['hold_trigger_deposit_amount' => '250000', 'required_transaction_volume' => '300000', 'hold_cooldown_days' => '21']);
check($overrides['hold_trigger_deposit_amount'] === 250000.0, 'per-user trigger override takes precedence');
check($overrides['required_transaction_volume'] === 300000.0, 'per-user transaction threshold override takes precedence');
check($overrides['hold_cooldown_days'] === 21, 'per-user cooldown override takes precedence');

[$subject, $html, $plain] = redman_email_render('kyc_rejected', [
    'name' => 'Jane Doe',
    'reference' => 'KYC-101',
    'reason' => 'The uploaded ID photo is blurry and cannot be verified.',
]);
check(strpos($html, 'The uploaded ID photo is blurry and cannot be verified.') !== false, 'rejection emails include the admin-supplied reason in the HTML body');
check(strpos($plain, 'The uploaded ID photo is blurry and cannot be verified.') !== false, 'rejection emails include the admin-supplied reason in the plain-text body');

echo "All risk rule tests passed.\n";
