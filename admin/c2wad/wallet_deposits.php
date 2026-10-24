<?php
session_start();

include "../../conn.php";
require_once "../../risk.php";
require_once "../../email_notifications.php";

if (!isset($_SESSION['uid'])) {
    header("location:../c2wadmin/signin.php");
    exit();
}

if (empty($_SESSION['wallet_deposit_csrf'])) {
    $_SESSION['wallet_deposit_csrf'] = bin2hex(random_bytes(32));
}

$msg = "";
$msgType = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backdate'])) {
    $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    $dateInput = trim((string)($_POST['transaction_date'] ?? ''));
    $transactionDate = DateTime::createFromFormat('Y-m-d\\TH:i', $dateInput);
    $dateErrors = DateTime::getLastErrors();
    if (!$transactionId || !hash_equals($_SESSION['wallet_deposit_csrf'], $csrfToken) || !$transactionDate || ($dateErrors && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $transactionDate->format('Y-m-d\\TH:i') !== $dateInput) {
        $msg = 'Enter a valid transaction date and time.';
        $msgType = 'danger';
    } elseif ($transactionDate->getTimestamp() > time()) {
        $msg = 'Transaction history dates cannot be in the future.';
        $msgType = 'danger';
    } else {
        $formattedDate = $transactionDate->format('Y-m-d H:i:s');
        $updateDate = $link->prepare("UPDATE btc SET date = ? WHERE id = ? AND type = 'Wallet Deposit'");
        if ($updateDate && $updateDate->bind_param('si', $formattedDate, $transactionId) && $updateDate->execute() && $updateDate->affected_rows === 1) {
            $msg = 'Wallet top-up transaction date updated.';
        } else {
            $msg = 'Unable to update wallet top-up transaction date.';
            $msgType = 'danger';
        }
        if ($updateDate) {
            $updateDate->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
    $csrfToken = $_POST['csrf_token'] ?? '';
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));
    if (!$transactionId || !hash_equals($_SESSION['wallet_deposit_csrf'], $csrfToken) || $reason === '' || strlen($reason) > 500) {
        $msg = 'A valid request and rejection reason (1-500 characters) are required.';
        $msgType = 'danger';
    } else {
        $link->begin_transaction();
        try {
            $select = $link->prepare("SELECT id, email, usd, tnxid FROM btc WHERE id = ? AND type = 'Wallet Deposit' AND status = 'pending' FOR UPDATE");
            $select->bind_param('i', $transactionId);
            $select->execute();
            $deposit = $select->get_result()->fetch_assoc();
            $select->close();
            if (!$deposit) {
                throw new RuntimeException('This wallet top-up is missing or already processed.');
            }

            $reject = $link->prepare("UPDATE btc SET status = 'rejected', comment = ? WHERE id = ? AND type = 'Wallet Deposit' AND status = 'pending'");
            $reject->bind_param('si', $reason, $transactionId);
            $reject->execute();
            if ($reject->affected_rows !== 1) {
                throw new RuntimeException('The wallet top-up could not be rejected.');
            }
            $reject->close();

            $userQuery = $link->prepare('SELECT fname, lname, username FROM users WHERE email = ? LIMIT 1');
            $userQuery->bind_param('s', $deposit['email']);
            $userQuery->execute();
            $user = $userQuery->get_result()->fetch_assoc() ?: [];
            $userQuery->close();
            $notificationId = redman_email_enqueue($link, 'wallet-topup-rejected:' . $transactionId, $deposit['email'], 'wallet_topup_rejected', [
                'name' => trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')) ?: ($user['username'] ?? 'there'),
                'amount' => (float)$deposit['usd'],
                'reference' => $deposit['tnxid'],
                'reason' => $reason,
            ]);
            if (!$link->commit()) {
                throw new RuntimeException('The rejection transaction could not be committed.');
            }
            redman_email_dispatch($link, $notificationId);
            $msg = 'Wallet top-up rejected and the customer was notified.';
        } catch (Throwable $error) {
            $link->rollback();
            $msg = $error->getMessage();
            $msgType = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!$transactionId || !hash_equals($_SESSION['wallet_deposit_csrf'], $csrfToken)) {
        $msg = "Invalid wallet top-up request.";
        $msgType = "danger";
    } else {
        $link->begin_transaction();

        try {
            $select = $link->prepare("SELECT id, email, usd, tnxid FROM btc WHERE id = ? AND type = 'Wallet Deposit' AND status = 'pending' FOR UPDATE");
            $select->bind_param('i', $transactionId);
            $select->execute();
            $result = $select->get_result();
            $deposit = $result->fetch_assoc();

            if (!$deposit || (float)$deposit['usd'] <= 0) {
                throw new RuntimeException("This wallet top-up is missing, already processed, or invalid.");
            }

            $userSelect = $link->prepare('SELECT * FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
            $userSelect->bind_param('s', $deposit['email']);
            $userSelect->execute();
            $user = $userSelect->get_result()->fetch_assoc();
            $userSelect->close();
            if (!$user) {
                throw new RuntimeException("The investor account could not be found.");
            }

            $riskDecision = redman_risk_deposit_must_be_held($link, $user, $deposit['usd']);
            if ($riskDecision['hold'] === null) {
                throw new RuntimeException($riskDecision['reason']);
            }

            if ($riskDecision['hold']) {
                $riskHoldNotificationId = redman_risk_start_hold($link, $user, $deposit['usd'], $riskDecision['reason'], (int)$_SESSION['uid']);
                if (!$riskHoldNotificationId) {
                    throw new RuntimeException("The account hold could not be recorded.");
                }
                $approve = $link->prepare("UPDATE btc SET status = 'approved', risk_held = 1 WHERE id = ? AND type = 'Wallet Deposit' AND status = 'pending'");
                $approve->bind_param('i', $transactionId);
                $approve->execute();
                if ($approve->affected_rows !== 1) {
                    throw new RuntimeException("The wallet top-up status could not be updated.");
                }
                $approve->close();
                $notificationId = redman_email_enqueue($link, 'wallet-topup-vaulted:' . $transactionId, $deposit['email'], 'wallet_topup_vaulted', [
                    'name' => trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')) ?: ($user['username'] ?? 'there'),
                    'amount' => (float)$deposit['usd'],
                    'reference' => $deposit['tnxid'],
                    'reason' => $riskDecision['reason'],
                ]);
                if (!$link->commit()) {
                    throw new RuntimeException('Unable to commit wallet top-up hold.');
                }
                if (is_int($riskHoldNotificationId) && $riskHoldNotificationId > 0) {
                    redman_email_dispatch($link, $riskHoldNotificationId);
                }
                redman_email_dispatch($link, $notificationId);
                $msg = "Wallet top-up approved into the account vault. Spending remains restricted pending risk review.";
            } else {
            $credit = $link->prepare("UPDATE users SET walletbalance = walletbalance + ? WHERE email = ?");
            $credit->bind_param('ds', $deposit['usd'], $deposit['email']);
            $credit->execute();
            if ($credit->affected_rows !== 1) {
                throw new RuntimeException("The investor account could not be credited.");
            }

            $approve = $link->prepare("UPDATE btc SET status = 'approved' WHERE id = ? AND type = 'Wallet Deposit' AND status = 'pending'");
            $approve->bind_param('i', $transactionId);
            $approve->execute();
            if ($approve->affected_rows !== 1) {
                throw new RuntimeException("The wallet top-up status could not be updated.");
            }
            $approve->close();
            $notificationId = redman_email_enqueue($link, 'wallet-topup-approved:' . $transactionId, $deposit['email'], 'wallet_topup_approved', [
                'name' => trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')) ?: ($user['username'] ?? 'there'),
                'amount' => (float)$deposit['usd'],
                'reference' => $deposit['tnxid'],
            ]);
            if (!$link->commit()) {
                throw new RuntimeException('Unable to commit wallet top-up approval.');
            }
            redman_email_dispatch($link, $notificationId);
            $msg = "Wallet top-up approved and credited.";
            }
        } catch (Throwable $error) {
            $link->rollback();
            $msg = $error->getMessage();
            $msgType = "danger";
        }
    }
}

include 'header.php';
?>
<div class="content-wrapper">
    <section class="content">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Wallet Top-up Requests</h3>
            </div>
            <div class="box-body">
                <?php if ($msg !== ""): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Investor</th>
                                <th>Amount (USD)</th>
                                <th>Payment Method</th>
                                <th>Payment Reference</th>
                                <th>Request ID</th>
                                <th>Status</th>
                                <th>Transaction Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $requests = $link->query("SELECT id, email, usd, mode, account, tnxid, status, date FROM btc WHERE type = 'Wallet Deposit' ORDER BY date DESC, id DESC");
                            if ($requests && $requests->num_rows > 0):
                                while ($request = $requests->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>$<?php echo number_format((float)$request['usd'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($request['mode'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($request['account'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($request['tnxid'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($request['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($request['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="transaction_id" value="<?php echo (int)$request['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['wallet_deposit_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="datetime-local" name="transaction_date" value="<?php echo htmlspecialchars(date('Y-m-d\\TH:i', strtotime((string)$request['date'])), ENT_QUOTES, 'UTF-8'); ?>" required style="max-width:190px;color:#222;">
                                            <button class="btn btn-default btn-xs" type="submit" name="backdate" value="1">Save date</button>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <form method="post">
                                                <input type="hidden" name="transaction_id" value="<?php echo (int)$request['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['wallet_deposit_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button class="btn btn-success btn-sm" type="submit" name="approve" value="1">Approve wallet credit</button>
                                            </form>
                                            <form method="post" class="mt-2">
                                                <input type="hidden" name="transaction_id" value="<?php echo (int)$request['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['wallet_deposit_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input class="form-control input-sm" type="text" name="rejection_reason" maxlength="500" placeholder="Reason required for rejection" required>
                                                <button class="btn btn-danger btn-sm" type="submit" name="reject" value="1">Reject top-up</button>
                                            </form>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr><td colspan="8">No wallet top-up requests.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
