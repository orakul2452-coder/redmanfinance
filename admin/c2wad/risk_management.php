<?php
session_start();
include "../../conn.php";
require_once "../../risk.php";

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
        $kycStatus = (string)($_POST['kyc_status'] ?? '');
        $overrideNames = ['hold_trigger_deposit_amount', 'required_transaction_volume', 'hold_cooldown_days', 'hold_new_account_days'];
        $overrides = [];
        $valid = $userId && $userId > 0 && in_array($kycStatus, $kycStatuses, true);
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

                $updateUser = $link->prepare('UPDATE users SET kyc_status = ?, kyc_reviewed_at = ?, kyc_reviewed_by = ?, hold_requirements_met_at = IF(is_on_hold = 1, NULL, hold_requirements_met_at), hold_release_date = IF(is_on_hold = 1, NULL, hold_release_date) WHERE id = ?');
                if (!$updateUser) {
                    throw new RuntimeException('Unable to prepare KYC update.');
                }
                $updateUser->bind_param('ssii', $kycStatus, $reviewedAt, $adminId, $userId);
                if (!$updateUser->execute() || $updateUser->affected_rows < 0) {
                    throw new RuntimeException('KYC status could not be updated.');
                }
                $updateUser->close();

                $saveOverride = $link->prepare('INSERT INTO user_risk_overrides (user_id, hold_trigger_deposit_amount, required_transaction_volume, hold_cooldown_days, hold_new_account_days, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hold_trigger_deposit_amount = VALUES(hold_trigger_deposit_amount), required_transaction_volume = VALUES(required_transaction_volume), hold_cooldown_days = VALUES(hold_cooldown_days), hold_new_account_days = VALUES(hold_new_account_days), updated_by = VALUES(updated_by)');
                if (!$saveOverride) {
                    throw new RuntimeException('Unable to prepare risk overrides.');
                }
                $triggerAmount = $overrides['hold_trigger_deposit_amount'];
                $requiredVolume = $overrides['required_transaction_volume'];
                $cooldownDays = $overrides['hold_cooldown_days'];
                $newAccountDays = $overrides['hold_new_account_days'];
                $saveOverride->bind_param('iddiii', $userId, $triggerAmount, $requiredVolume, $cooldownDays, $newAccountDays, $adminId);
                if (!$saveOverride->execute()) {
                    throw new RuntimeException('Risk overrides could not be saved.');
                }
                $saveOverride->close();

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
                $link->commit();
                if ($userRecord) {
                    redman_risk_refresh_hold($link, $userRecord['email']);
                }
                $message = 'Investor KYC status and risk overrides saved.';
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
                if (!redman_risk_start_hold($link, $user, $vaultAmount, 'Manual administrative risk review', $adminId)) {
                    throw new RuntimeException('The hold or audit record could not be saved.');
                }
                $link->commit();
                $message = 'Account placed on hold and its available balance moved to the vault.';
            } catch (Throwable $error) {
                $link->rollback();
                $message = $error->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

$settings = redman_risk_settings($link);
$usersResult = $link->query('SELECT id, fname, lname, username, email, date, walletbalance, vault_balance, is_on_hold, hold_reason, hold_release_date, kyc_status, id_front, id_back FROM users ORDER BY id DESC LIMIT 100');
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
            <div class="box-header with-border"><h3 class="box-title">Investor review (latest 100)</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Investor</th><th>KYC</th><th>Approved volume</th><th>Main balance</th><th>Vault</th><th>Hold</th><th>Documents</th><th>Review and overrides</th>
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
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst($user['kyc_status'] ?: 'unverified'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $user['transaction_volume'] === null ? 'Unavailable' : '$' . number_format($user['transaction_volume'], 2); ?></td>
                            <td>$<?php echo number_format((float)$user['walletbalance'], 2); ?></td>
                            <td>$<?php echo number_format((float)$user['vault_balance'], 2); ?></td>
                            <td>
                                <?php if ((int)$user['is_on_hold'] === 1): ?>
                                    <strong>Held</strong><br>
                                    <?php echo htmlspecialchars((string)$user['hold_reason'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <?php if (!empty($user['hold_release_date'])): ?>
                                        <small>Scheduled release: <?php echo htmlspecialchars($user['hold_release_date'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php else: ?>
                                        <small>Awaiting KYC and transaction-volume requirements</small>
                                    <?php endif; ?>
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
                                <?php foreach (['id_front' => 'Front ID', 'id_back' => 'Back ID'] as $field => $label): ?>
                                    <?php if (!empty($user[$field])): ?>
                                        <a target="_blank" rel="noopener" href="../../account/uploads/<?php echo rawurlencode(basename($user[$field])); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a><br>
                                    <?php else: ?>
                                        <small><?php echo htmlspecialchars($label . ' missing', ENT_QUOTES, 'UTF-8'); ?></small><br>
                                    <?php endif; ?>
                                <?php endforeach; ?>
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
