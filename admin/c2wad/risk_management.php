<?php
session_start();
include "../../conn.php";
require_once "../../risk.php";
require_once "../../email_notifications.php";

if (!isset($_SESSION['uid'])) {
    header('location:../c2wadmin/signin.php');
    exit();
}

if (empty($_SESSION['risk_admin_csrf'])) {
    $_SESSION['risk_admin_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';
$adminId = (int)$_SESSION['uid'];
$settingKeys = array_keys(redman_risk_defaults());
$kycStatuses = ['unverified', 'pending', 'verified', 'rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['risk_admin_csrf'], $_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token.';
        $messageType = 'danger';
    } elseif (($_POST['action'] ?? '') === 'save_settings') {
        $values = [];
        $valid = true;
        $defaults = redman_risk_defaults();
        foreach ($settingKeys as $key) {
            $rawValue = trim((string)($_POST[$key] ?? ''));
            if ($rawValue === '' || !is_numeric($rawValue) || !is_finite((float)$rawValue) || (float)$rawValue < 0) {
                $valid = false;
                break;
            }
            if (is_int($defaults[$key]) && floor((float)$rawValue) !== (float)$rawValue) {
                $valid = false;
                break;
            }
            $values[$key] = $rawValue;
        }
        if (!$valid) {
            $message = 'Enter non-negative numeric values for every global setting.';
            $messageType = 'danger';
        } else {
            $save = $link->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            if (!$save) {
                $message = 'Unable to prepare global settings update.';
                $messageType = 'danger';
            } else {
                foreach ($values as $key => $value) {
                    $save->bind_param('ss', $key, $value);
                    if (!$save->execute()) {
                        $valid = false;
                        break;
                    }
                }
                $save->close();
                $message = $valid ? 'Global risk settings saved.' : 'A global setting could not be saved.';
                $messageType = $valid ? 'success' : 'danger';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'save_user') {
        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $kycDecision = (string)($_POST['kyc_decision'] ?? '');
        $kycStatus = in_array($kycDecision, ['verified', 'rejected'], true)
            ? $kycDecision
            : (string)($_POST['kyc_status'] ?? '');
        $kycReason = trim((string)($_POST['kyc_rejection_reason'] ?? ''));
        $overrideNames = ['hold_trigger_deposit_amount', 'required_transaction_volume', 'hold_cooldown_days', 'hold_new_account_days'];
        $overrides = [];
        $valid = $userId && $userId > 0 && in_array($kycStatus, $kycStatuses, true);
        if ($kycStatus === 'rejected' && $kycReason === '') {
            $valid = false;
        }
        foreach ($overrideNames as $name) {
            $value = trim((string)($_POST[$name] ?? ''));
            if ($value === '') {
                $overrides[$name] = null;
            } elseif (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) {
                $valid = false;
            } elseif (in_array($name, ['hold_cooldown_days', 'hold_new_account_days'], true) && floor((float)$value) !== (float)$value) {
                $valid = false;
            } else {
                $overrides[$name] = $value;
            }
        }

        if (!$valid) {
            $message = 'Choose a valid KYC status and use non-negative numeric overrides or leave them blank.';
            $messageType = 'danger';
        } else {
            $reviewedAt = in_array($kycStatus, ['verified', 'rejected'], true) ? date('Y-m-d H:i:s') : null;
            $link->begin_transaction();
            try {
                $findEmail = $link->prepare('SELECT email FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                $findEmail->bind_param('i', $userId);
                $findEmail->execute();
                $userRecord = $findEmail->get_result()->fetch_assoc();
                $findEmail->close();
                if (!$userRecord) {
                    throw new RuntimeException('Investor account could not be found.');
                }

                $findRiskUser = $link->prepare('SELECT * FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                if (!$findRiskUser) {
                    throw new RuntimeException('Unable to load investor risk state.');
                }
                $findRiskUser->bind_param('i', $userId);
                if (!$findRiskUser->execute()) {
                    $findRiskUser->close();
                    throw new RuntimeException('Unable to load investor risk state.');
                }
                $riskUser = $findRiskUser->get_result()->fetch_assoc();
                $findRiskUser->close();
                if (!$riskUser) {
                    throw new RuntimeException('Investor account could not be found.');
                }
                if ($kycStatus === 'verified' && !redman_risk_compliance_documents_complete($riskUser)) {
                    throw new RuntimeException('Cannot approve KYC: the customer must submit ID front, ID back, and proof of address first.');
                }
                $riskUser['kyc_status'] = $kycStatus;
                $kycNotificationId = 0;

                $updateUser = $link->prepare('UPDATE users SET kyc_status = ?, kyc_reviewed_at = ?, kyc_reviewed_by = ?, hold_requirements_met_at = IF(is_on_hold = 1, NULL, hold_requirements_met_at), hold_release_date = IF(is_on_hold = 1, NULL, hold_release_date) WHERE id = ?');
                if (!$updateUser) {
                    throw new RuntimeException('Unable to prepare KYC update.');
                }
                $updateUser->bind_param('ssii', $kycStatus, $reviewedAt, $adminId, $userId);
                if (!$updateUser->execute() || $updateUser->affected_rows < 0) {
                    throw new RuntimeException('KYC status could not be updated.');
                }
                $updateUser->close();

                if (count(array_filter($overrides, static function ($value) { return $value !== null; })) === 0) {
                    $clearOverrides = $link->prepare('DELETE FROM user_risk_overrides WHERE user_id = ?');
                    if (!$clearOverrides) {
                        throw new RuntimeException('Unable to prepare risk override reset.');
                    }
                    $clearOverrides->bind_param('i', $userId);
                    if (!$clearOverrides->execute()) {
                        throw new RuntimeException('Risk overrides could not be reset.');
                    }
                    $clearOverrides->close();
                } else {
                    $saveOverride = $link->prepare('INSERT INTO user_risk_overrides (user_id, hold_trigger_deposit_amount, required_transaction_volume, hold_cooldown_days, hold_new_account_days, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hold_trigger_deposit_amount = VALUES(hold_trigger_deposit_amount), required_transaction_volume = VALUES(required_transaction_volume), hold_cooldown_days = VALUES(hold_cooldown_days), hold_new_account_days = VALUES(hold_new_account_days), updated_by = VALUES(updated_by)');
                    if (!$saveOverride) {
                        throw new RuntimeException('Unable to prepare risk overrides.');
                    }
                    $triggerAmount = $overrides['hold_trigger_deposit_amount'];
                    $requiredVolume = $overrides['required_transaction_volume'];
                    $cooldownDays = $overrides['hold_cooldown_days'];
                    $newAccountDays = $overrides['hold_new_account_days'];
                    $saveOverride->bind_param('issssi', $userId, $triggerAmount, $requiredVolume, $cooldownDays, $newAccountDays, $adminId);
                    if (!$saveOverride->execute()) {
                        throw new RuntimeException('Risk overrides could not be saved.');
                    }
                    $saveOverride->close();
                }

                $event = $link->prepare("INSERT INTO account_hold_events (user_id, event_type, reason, details, actor_admin_id) VALUES (?, 'kyc_status_changed', 'Admin updated KYC status and risk overrides', ?, ?)");
                if (!$event) {
                    throw new RuntimeException('KYC audit event could not be prepared.');
                }
                $details = json_encode(['kyc_status' => $kycStatus, 'overrides' => $overrides], JSON_UNESCAPED_SLASHES);
                $event->bind_param('isi', $userId, $details, $adminId);
                if (!$event->execute()) {
                    throw new RuntimeException('KYC audit event could not be recorded.');
                }
                $event->close();

                if ($kycStatus === 'verified') {
                    $kycNotificationId = redman_email_enqueue($link, 'kyc-verified:' . $userId, $riskUser['email'], 'kyc_verified', [
                        'name' => trim((string)($riskUser['fname'] ?? '') . ' ' . (string)($riskUser['lname'] ?? '')) ?: ($riskUser['username'] ?? 'there'),
                        'reason' => 'Your KYC review was approved.',
                    ]);
                } elseif ($kycStatus === 'rejected') {
                    $kycNotificationId = redman_email_enqueue($link, 'kyc-rejected:' . $userId, $riskUser['email'], 'kyc_rejected', [
                        'name' => trim((string)($riskUser['fname'] ?? '') . ' ' . (string)($riskUser['lname'] ?? '')) ?: ($riskUser['username'] ?? 'there'),
                        'reason' => $kycReason,
                    ]);
                }

                $caseFiles = $link->prepare('SELECT compliance_id_front_path, compliance_id_back_path, compliance_address_path, compliance_submitted_at FROM users WHERE id = ? LIMIT 1');
                $caseFiles->bind_param('i', $userId);
                $caseFiles->execute();
                $caseState = $caseFiles->get_result()->fetch_assoc() ?: [];
                $caseFiles->close();
                $riskUser = array_merge($riskUser, $caseState);
                $volume = redman_risk_transaction_volume($link, $riskUser['email']);
                if ($volume === null) {
                    throw new RuntimeException('Unable to calculate approved transaction volume; review settings were not applied.');
                }
                $effectiveLimits = redman_risk_limits(redman_risk_settings($link), redman_risk_user_overrides($link, $userId));
                $releaseChecks = redman_risk_release_checks($riskUser, $volume, $effectiveLimits);
                if ((int)$riskUser['is_on_hold'] === 1 && $releaseChecks['checks_met']) {
                    $releaseAt = redman_risk_next_release_at($riskUser, $volume, $effectiveLimits);
                    if (empty($riskUser['hold_release_date']) && $releaseAt !== null) {
                        $schedule = $link->prepare('UPDATE users SET hold_requirements_met_at = NOW(), hold_release_date = ? WHERE id = ? AND is_on_hold = 1 AND hold_release_date IS NULL');
                        if (!$schedule) {
                            throw new RuntimeException('Unable to start release cooldown.');
                        }
                        $schedule->bind_param('si', $releaseAt, $userId);
                        if (!$schedule->execute()) {
                            $schedule->close();
                            throw new RuntimeException('Unable to start release cooldown.');
                        }
                        $schedule->close();
                        $event = $link->prepare("INSERT INTO account_hold_events (user_id, event_type, reason, details, actor_admin_id) VALUES (?, 'cooldown_started', 'Submitted documents, verified KYC, and completed-history requirement passed review', ?, ?)");
                        if (!$event) {
                            throw new RuntimeException('Unable to prepare cooldown audit event.');
                        }
                        $details = json_encode(['transaction_volume' => $volume, 'required_volume' => $effectiveLimits['required_transaction_volume'], 'scheduled_release_at' => $releaseAt], JSON_UNESCAPED_SLASHES);
                        $event->bind_param('isi', $userId, $details, $adminId);
                        if (!$event->execute()) {
                            $event->close();
                            throw new RuntimeException('Unable to record cooldown audit event.');
                        }
                        $event->close();

                        if (filter_var($riskUser['email'], FILTER_VALIDATE_EMAIL)) {
                            try {
                                $cooldownNotificationId = redman_email_enqueue($link, 'release-cooldown:' . $userId, $riskUser['email'], 'release_cooldown_started', [
                                    'name' => trim((string)($riskUser['fname'] ?? '') . ' ' . (string)($riskUser['lname'] ?? '')) ?: ($riskUser['username'] ?? 'there'),
                                    'reference' => 'Release review',
                                    'reason' => 'Your submitted documents, verified KYC, and approved transaction history met the review requirements.',
                                    'detail' => 'The hold is now in the configured release cooldown. Staff review must still complete before funds are released automatically.',
                                ]);
                            } catch (Throwable $error) {
                                error_log('Unable to queue cooldown email for user ' . $userId . ': ' . $error->getMessage());
                            }
                        }
                    }
                    $message = 'Documents, KYC, and completed-history checks passed. The release cooldown is active; staff must release the vault after it expires.';
                } elseif (redman_risk_should_hold_deposit($riskUser, 0, $riskUser['walletbalance'], $volume, $effectiveLimits)) {
                    if ((int)$riskUser['is_on_hold'] === 0) {
                        $balanceToVault = max(0.0, (float)$riskUser['walletbalance']);
                        $secureBalance = $link->prepare('UPDATE users SET walletbalance = 0 WHERE id = ? AND is_on_hold = 0');
                        if (!$secureBalance) {
                            throw new RuntimeException('Unable to prepare automatic account hold.');
                        }
                        $secureBalance->bind_param('i', $userId);
                        if (!$secureBalance->execute()) {
                            $secureBalance->close();
                            throw new RuntimeException('Unable to secure the account balance.');
                        }
                        $secureBalance->close();
                        $riskUser['is_on_hold'] = 0;
                        $holdReason = 'Account review: current balance meets the configured large-balance trigger and completed approved history is below the required threshold.';
                        if (!redman_risk_start_hold($link, $riskUser, $balanceToVault, $holdReason, $adminId)) {
                            throw new RuntimeException('Automatic hold, investment pause, or audit event failed.');
                        }
                        $message = 'KYC and risk settings saved. The saved account state meets the configured hold criteria; available balance was placed in the vault and active investments were paused.';
                    }
                } elseif ((int)$riskUser['is_on_hold'] === 1) {
                    $message = 'Review saved. Hold remains active until submitted documents, staff-verified KYC, and completed-history checks pass.';
                } else {
                    $message = 'Investor KYC status and risk overrides saved. The current balance/history does not meet the automatic hold criteria.';
                }
                $link->commit();
                if ($message === '') {
                    $message = 'Investor KYC status and risk overrides saved.';
                }
                if ($kycNotificationId > 0) {
                    redman_email_dispatch($link, $kycNotificationId);
                }
            } catch (Throwable $error) {
                $link->rollback();
                $message = $error->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'place_hold') {
        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$userId || $userId < 1) {
            $message = 'Invalid investor account.';
            $messageType = 'danger';
        } else {
            $link->begin_transaction();
            try {
                $select = $link->prepare('SELECT * FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                $select->bind_param('i', $userId);
                $select->execute();
                $user = $select->get_result()->fetch_assoc();
                $select->close();
                if (!$user || (int)$user['is_on_hold'] === 1) {
                    throw new RuntimeException('The investor is missing or already on hold.');
                }
                $vaultAmount = max(0, (float)$user['walletbalance']);
                $debit = $link->prepare('UPDATE users SET walletbalance = 0 WHERE id = ? AND is_on_hold = 0');
                $debit->bind_param('i', $userId);
                if (!$debit->execute()) {
                    throw new RuntimeException('The available balance could not be secured.');
                }
                $debit->close();
                $holdNotificationId = redman_risk_start_hold($link, $user, $vaultAmount, 'Manual administrative risk review', $adminId);
                if (!$holdNotificationId) {
                    throw new RuntimeException('The hold or audit record could not be saved.');
                }
                $link->commit();
                if (is_int($holdNotificationId) && $holdNotificationId > 0) {
                    redman_email_dispatch($link, $holdNotificationId);
                }
                $message = 'Account placed on hold and its available balance moved to the vault.';
            } catch (Throwable $error) {
                $link->rollback();
                $message = $error->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'release_hold') {
        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $releaseReason = trim((string)($_POST['release_reason'] ?? ''));
        if (!$userId || $userId < 1 || $releaseReason === '' || strlen($releaseReason) > 500) {
            $message = 'Enter a release reason (maximum 500 characters) and choose a valid account.';
            $messageType = 'danger';
        } else {
            $releaseNotificationId = redman_risk_admin_release_hold($link, $userId, $releaseReason, $adminId, !empty($_POST['emergency_override']));
            if (!$releaseNotificationId) {
                $message = 'Release was not allowed. Check submitted documents, KYC verification, completed-history status, and cooldown; emergency override must be explicitly selected.';
                $messageType = 'danger';
            } else {
                if (is_int($releaseNotificationId) && $releaseNotificationId > 0) {
                    redman_email_dispatch($link, $releaseNotificationId);
                }
                $message = 'Hold released by administrator. Vault funds were returned to the main balance and paused investments resumed.';
            }
        }
    }
}

$settings = redman_risk_settings($link);
$usersResult = $link->query('SELECT id, fname, lname, username, email, date, walletbalance, vault_balance, is_on_hold, hold_reason, hold_release_date, kyc_status, id_front, id_back, compliance_case_reference, compliance_id_front_path, compliance_id_back_path, compliance_address_path, compliance_submitted_at FROM users ORDER BY id DESC LIMIT 100');
$users = [];
$transactionVolumes = [];
$volumeResult = $link->query("SELECT email, COALESCE(SUM(ABS(usd)), 0) AS volume FROM btc WHERE status = 'approved' AND (risk_held = 0 OR type IN ('Deposit', 'Wallet Deposit')) AND type IN ('Deposit', 'Wallet Deposit', 'Withdrawal') GROUP BY email");
if ($volumeResult) {
    while ($volumeRow = $volumeResult->fetch_assoc()) {
        $transactionVolumes[$volumeRow['email']] = (float)$volumeRow['volume'];
    }
}
$overridesByUser = [];
$overridesResult = $link->query('SELECT user_id, hold_trigger_deposit_amount, required_transaction_volume, hold_cooldown_days, hold_new_account_days FROM user_risk_overrides');
if ($overridesResult) {
    while ($overrideRow = $overridesResult->fetch_assoc()) {
        $overridesByUser[(int)$overrideRow['user_id']] = $overrideRow;
    }
}
if ($usersResult) {
    while ($userRow = $usersResult->fetch_assoc()) {
        $userRow['transaction_volume'] = $transactionVolumes[$userRow['email']] ?? 0.0;
        $userRow['risk_overrides'] = $overridesByUser[(int)$userRow['id']] ?? [];
        $userRow['effective_limits'] = redman_risk_limits($settings, $userRow['risk_overrides']);
        $userRow['volume_progress'] = redman_risk_volume_progress($userRow['transaction_volume'], $userRow['effective_limits']['required_transaction_volume']);
        $users[] = $userRow;
    }
}

include 'header.php';
?>
<div class="content-wrapper">
    <section class="content">
        <h2>Risk Management and KYC</h2>
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Global Thresholds</h3></div>
            <div class="box-body">
                <p class="text-muted">Deposit approval checks incoming amount, projected balance, and completed approved history. Saving a review starts the release cooldown only after the submitted documents are complete, KYC is marked Verified by staff, and the completed-history requirement is met. Cooldown expiry enables the staff Release vault action; funds are not released automatically.</p>
                <form method="post" class="row">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['risk_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="save_settings">
                    <?php
                    $settingLabels = [
                        'min_withdrawal_amount' => 'Minimum withdrawal (USD)',
                        'hold_trigger_deposit_amount' => 'Large deposit or balance trigger (USD)',
                        'required_transaction_volume' => 'Required approved transaction volume (USD)',
                        'hold_cooldown_days' => 'Release cooldown (days)',
                        'hold_new_account_days' => 'New-account window (days)',
                    ];
                    foreach ($settingLabels as $key => $label):
                    ?>
                        <div class="col-md-4 form-group">
                            <label for="setting-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                            <input class="form-control" id="setting-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" type="number" min="0" step="<?php echo strpos($key, 'days') !== false ? '1' : '0.01'; ?>" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$settings[$key], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-12"><button class="btn btn-primary" type="submit">Save global thresholds</button></div>
                </form>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">Investor review (latest 100)</h3><p class="text-muted">Per-investor overrides take precedence over global values. Clear all four override fields and save to restore the global settings for that investor.</p></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Investor / case</th><th>KYC</th><th>Approved volume / effective tier</th><th>Main balance</th><th>Vault</th><th>Hold</th><th>Documents</th><th>Review and overrides</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $overrides = $user['risk_overrides']; ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars(trim($user['fname'] . ' ' . $user['lname']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?><br>
                                <small>Joined <?php echo htmlspecialchars($user['date'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php if (!empty($user['compliance_case_reference'])): ?><br><small>Case <?php echo htmlspecialchars($user['compliance_case_reference'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                                <?php if (!empty($user['compliance_submitted_at'])): ?><br><small>Submitted <?php echo htmlspecialchars($user['compliance_submitted_at'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                            </td>
                            <?php $releaseChecks = redman_risk_release_checks($user, $user['transaction_volume'], $user['effective_limits']); ?>
                            <td><?php echo htmlspecialchars(ucfirst($user['kyc_status'] ?: 'unverified'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo '$' . number_format((float)$user['transaction_volume'], 2); ?> approved<br>
                                <small>Required: $<?php echo number_format((float)$user['volume_progress']['required'], 2); ?>; <?php echo $user['volume_progress']['met'] ? 'requirement met' : '$' . number_format($user['volume_progress']['remaining'], 2) . ' remaining'; ?></small><br>
                                <small>Deposit/balance trigger: $<?php echo number_format((float)$user['effective_limits']['hold_trigger_deposit_amount'], 2); ?></small>
                            </td>
                            <td>$<?php echo number_format((float)$user['walletbalance'], 2); ?></td>
                            <td>$<?php echo number_format((float)$user['vault_balance'], 2); ?></td>
                            <td>
                                <?php if ((int)$user['is_on_hold'] === 1): ?>
                                    <strong>Held</strong><br>
                                    <?php echo htmlspecialchars((string)$user['hold_reason'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <small>Documents: <?php echo $releaseChecks['documents_complete'] ? '&#10003; passed' : '&#10007; pending'; ?>; KYC: <?php echo $releaseChecks['kyc_verified'] ? '&#10003; verified' : '&#10007; pending'; ?>; history: <?php echo $releaseChecks['history_met'] ? '&#10003; passed' : '&#10007; short'; ?></small><br>
                                    <?php if (!empty($user['hold_release_date'])): ?>
                                        <small>Cooldown started: <?php echo htmlspecialchars((string)$user['hold_requirements_met_at'], ENT_QUOTES, 'UTF-8'); ?><br>Release eligible after: <?php echo htmlspecialchars((string)$user['hold_release_date'], ENT_QUOTES, 'UTF-8'); ?></small><br>
                                    <?php else: ?>
                                        <small>Cooldown starts after submitted documents, verified KYC, and completed history all pass.</small><br>
                                    <?php endif; ?>
                                    <form method="post" onsubmit="return confirm('Release the vault to the main balance and resume paused investments?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['risk_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="release_hold">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                        <input class="form-control input-sm" type="text" name="release_reason" maxlength="500" placeholder="Release decision reason" aria-label="Release decision reason" required>
                                        <button class="btn btn-success btn-xs" type="submit" <?php echo $releaseChecks['cooldown_elapsed'] ? '' : 'disabled'; ?>>Release vault</button>
                                    </form>
                                    <details class="mt-1">
                                        <summary>Emergency override</summary>
                                        <form method="post" onsubmit="return confirm('Emergency override bypasses normal document/history/cooldown checks. Continue only with documented authorization.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['risk_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="release_hold">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                            <input type="hidden" name="emergency_override" value="1">
                                            <input class="form-control input-sm" type="text" name="release_reason" maxlength="500" placeholder="Documented emergency reason" aria-label="Documented emergency reason" required>
                                            <button class="btn btn-danger btn-xs" type="submit">Emergency release override</button>
                                        </form>
                                    </form>
                                <?php else: ?>
                                    Not held
                                    <form method="post" onsubmit="return confirm('Place this account on hold and move its available balance to the vault?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['risk_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="place_hold">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                        <button class="btn btn-warning btn-xs" type="submit">Place hold</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ([
                                    'compliance_id_front_path' => ['front', 'New front ID'],
                                    'compliance_id_back_path' => ['back', 'New back ID'],
                                    'compliance_address_path' => ['address', 'Proof of address'],
                                ] as $field => [$kind, $label]): ?>
                                    <?php if (!empty($user[$field])): ?>
                                        <a target="_blank" rel="noopener" href="compliance_document.php?user_id=<?php echo (int)$user['id']; ?>&amp;kind=<?php echo htmlspecialchars($kind, ENT_QUOTES, 'UTF-8'); ?>">View <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
                                        <a class="btn btn-default btn-xs" href="compliance_document.php?user_id=<?php echo (int)$user['id']; ?>&amp;kind=<?php echo htmlspecialchars($kind, ENT_QUOTES, 'UTF-8'); ?>&amp;download=1">Download</a><br>
                                    <?php else: ?>
                                        <small><?php echo htmlspecialchars($label . ' not submitted', ENT_QUOTES, 'UTF-8'); ?></small><br>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php foreach (['id_front' => 'Existing front ID', 'id_back' => 'Existing back ID'] as $field => $label): ?>
                                    <?php if (empty($user['compliance_' . $field . '_path']) && !empty($user[$field])): ?>
                                        <a target="_blank" rel="noopener" href="compliance_document.php?user_id=<?php echo (int)$user['id']; ?>&amp;kind=<?php echo $field === 'id_front' ? 'legacy_front' : 'legacy_back'; ?>">View <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
                                        <a class="btn btn-default btn-xs" href="compliance_document.php?user_id=<?php echo (int)$user['id']; ?>&amp;kind=<?php echo $field === 'id_front' ? 'legacy_front' : 'legacy_back'; ?>&amp;download=1">Download</a><br>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (!empty($user['compliance_case_reference'])): ?>
                                    <?php
                                    $caseEvents = $link->prepare('SELECT event_type, details, created_at FROM compliance_case_events WHERE user_id = ? ORDER BY id DESC LIMIT 5');
                                    if ($caseEvents):
                                        $caseUserId = (int)$user['id'];
                                        $caseEvents->bind_param('i', $caseUserId);
                                        $caseEvents->execute();
                                        $eventRows = $caseEvents->get_result();
                                        while ($caseEvent = $eventRows->fetch_assoc()):
                                    ?>
                                        <small><?php echo htmlspecialchars($caseEvent['event_type'] . ' · ' . $caseEvent['created_at'], ENT_QUOTES, 'UTF-8'); ?></small><br>
                                    <?php
                                        endwhile;
                                        $caseEvents->close();
                                    endif;
                                    ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['risk_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="save_user">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                    <select class="form-control input-sm" name="kyc_status" aria-label="KYC status">
                                        <?php foreach ($kycStatuses as $status): ?>
                                            <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $user['kyc_status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-success btn-xs" type="submit" name="kyc_decision" value="verified" <?php echo $releaseChecks['documents_complete'] ? '' : 'disabled'; ?>>Approve KYC</button>
                                    <button class="btn btn-warning btn-xs" type="submit" name="kyc_decision" value="rejected">Reject KYC</button>
                                    <input class="form-control input-sm" type="text" name="kyc_rejection_reason" maxlength="500" placeholder="Rejection reason (required for rejected KYC)" aria-label="Rejection reason">
                                    <input class="form-control input-sm" type="number" min="0" step="0.01" name="hold_trigger_deposit_amount" placeholder="Deposit trigger override" value="<?php echo htmlspecialchars((string)($overrides['hold_trigger_deposit_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input class="form-control input-sm" type="number" min="0" step="0.01" name="required_transaction_volume" placeholder="Volume override" value="<?php echo htmlspecialchars((string)($overrides['required_transaction_volume'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input class="form-control input-sm" type="number" min="0" step="1" name="hold_cooldown_days" placeholder="Cooldown days override" value="<?php echo htmlspecialchars((string)($overrides['hold_cooldown_days'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input class="form-control input-sm" type="number" min="0" step="1" name="hold_new_account_days" placeholder="New-account days override" value="<?php echo htmlspecialchars((string)($overrides['hold_new_account_days'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="btn btn-primary btn-xs" type="submit">Save review</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td colspan="8">No investor accounts found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
