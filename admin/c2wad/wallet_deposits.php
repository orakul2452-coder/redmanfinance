<?php
session_start();

include "../../conn.php";

if (!isset($_SESSION['uid'])) {
    header("location:../c2wadmin/signin.php");
    exit();
}

if (empty($_SESSION['wallet_deposit_csrf'])) {
    $_SESSION['wallet_deposit_csrf'] = bin2hex(random_bytes(32));
}

$msg = "";
$msgType = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!$transactionId || !hash_equals($_SESSION['wallet_deposit_csrf'], $csrfToken)) {
        $msg = "Invalid wallet top-up request.";
        $msgType = "danger";
    } else {
        $link->begin_transaction();

        try {
            $select = $link->prepare("SELECT email, usd FROM btc WHERE id = ? AND type = 'Wallet Deposit' AND status = 'pending' FOR UPDATE");
            $select->bind_param('i', $transactionId);
            $select->execute();
            $result = $select->get_result();
            $deposit = $result->fetch_assoc();

            if (!$deposit || (float)$deposit['usd'] <= 0) {
                throw new RuntimeException("This wallet top-up is missing, already processed, or invalid.");
            }

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

            $link->commit();
            $msg = "Wallet top-up approved and credited.";
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
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $requests = $link->query("SELECT id, email, usd, mode, account, tnxid, status, date FROM btc WHERE type = 'Wallet Deposit' ORDER BY id DESC");
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
                                    <td><?php echo htmlspecialchars($request['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <form method="post">
                                                <input type="hidden" name="transaction_id" value="<?php echo (int)$request['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['wallet_deposit_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button class="btn btn-success btn-sm" type="submit" name="approve" value="1">Approve wallet credit</button>
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
