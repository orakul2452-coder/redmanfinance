<?php
session_start();
require_once '../db.php';
require_once '../email_notifications.php';

if (empty($_SESSION['email'])) {
    header('Location: ../trade/login.php');
    exit();
}

$email = (string)$_SESSION['email'];
$message = '';
$error = '';
$recipientAccountInput = '';
$amountInput = '';
$noteInput = '';
$pendingTransfer = $_SESSION['pending_transfer'] ?? null;
if ($pendingTransfer) {
    $recipientAccountInput = (string)$pendingTransfer['recipient_account'];
    $amountInput = (string)$pendingTransfer['amount'];
    $noteInput = (string)$pendingTransfer['note'];
}
$_SESSION['transfer_csrf'] = $_SESSION['transfer_csrf'] ?? bin2hex(random_bytes(32));

$currentUserQuery = $link->prepare('SELECT id, account_number, walletbalance, is_on_hold FROM users WHERE email = ? LIMIT 1');
if (!$currentUserQuery) {
    http_response_code(500);
    exit('Unable to load your account.');
}
$currentUserQuery->bind_param('s', $email);
$currentUserQuery->execute();
$currentUser = $currentUserQuery->get_result()->fetch_assoc();
$currentUserQuery->close();
if (!$currentUser) {
    header('Location: ../trade/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $action = (string)($_POST['action'] ?? 'lookup');

    if (!hash_equals($_SESSION['transfer_csrf'], $csrf)) {
        $error = 'This request expired. Reload the page and try again.';
        unset($_SESSION['pending_transfer']);
    } elseif ($action === 'lookup') {
        $recipientAccountInput = strtoupper(trim((string)($_POST['recipient_account'] ?? '')));
        $amountInput = trim((string)($_POST['amount'] ?? ''));
        $noteInput = trim((string)($_POST['note'] ?? ''));
        $amount = filter_var($amountInput, FILTER_VALIDATE_FLOAT);

        if (!preg_match('/^RF[0-9]{10}$/', $recipientAccountInput)) {
            $error = 'Enter a valid recipient account number.';
        } elseif ($recipientAccountInput === $currentUser['account_number']) {
            $error = 'You cannot transfer funds to your own account.';
        } elseif (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $amountInput) || $amount === false || !is_finite((float)$amount) || $amount <= 0) {
            $error = 'Enter an amount greater than zero with no more than two decimal places.';
        } elseif (strlen($noteInput) > 140) {
            $error = 'The transfer note must be 140 characters or fewer.';
        } else {
            $recipientLookup = $link->prepare('SELECT id, account_number, fname, lname FROM users WHERE account_number = ? LIMIT 1');
            $recipientLookup->bind_param('s', $recipientAccountInput);
            $recipientLookup->execute();
            $recipient = $recipientLookup->get_result()->fetch_assoc();
            $recipientLookup->close();

            if (!$recipient) {
                $error = 'No account was found for that number.';
            } else {
                $_SESSION['pending_transfer'] = [
                    'sender_id' => (int)$currentUser['id'],
                    'recipient_id' => (int)$recipient['id'],
                    'recipient_account' => (string)$recipient['account_number'],
                    'recipient_name' => trim((string)$recipient['fname'] . ' ' . (string)$recipient['lname']),
                    'amount' => number_format((float)$amount, 2, '.', ''),
                    'note' => $noteInput,
                    'request_id' => bin2hex(random_bytes(16)),
                    'confirm_token' => bin2hex(random_bytes(32)),
                ];
                $pendingTransfer = $_SESSION['pending_transfer'];
            }
        }
    } elseif ($action === 'confirm') {
        $pendingTransfer = $_SESSION['pending_transfer'] ?? null;
        $confirmToken = (string)($_POST['confirm_token'] ?? '');
        if (!$pendingTransfer || (int)$pendingTransfer['sender_id'] !== (int)$currentUser['id'] || !hash_equals($pendingTransfer['confirm_token'], $confirmToken)) {
            unset($_SESSION['pending_transfer']);
            $pendingTransfer = null;
            $error = 'The transfer confirmation expired. Enter the details again.';
        } else {
            $senderId = (int)$pendingTransfer['sender_id'];
            $recipientId = (int)$pendingTransfer['recipient_id'];
            $recipientAccount = (string)$pendingTransfer['recipient_account'];
            $requestId = (string)$pendingTransfer['request_id'];
            $amountValue = (float)$pendingTransfer['amount'];
            $note = (string)$pendingTransfer['note'];
            $transferReference = 'TRF' . strtoupper(bin2hex(random_bytes(10)));
            $idsToLock = [$senderId, $recipientId];
            sort($idsToLock, SORT_NUMERIC);

            try {
                $link->begin_transaction();
                $lockUsers = $link->prepare('SELECT id, email, account_number, fname, lname, walletbalance, is_on_hold FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE');
                $lockUsers->bind_param('ii', $idsToLock[0], $idsToLock[1]);
                $lockUsers->execute();
                $lockedUsers = [];
                $lockedResult = $lockUsers->get_result();
                while ($locked = $lockedResult->fetch_assoc()) {
                    $lockedUsers[(int)$locked['id']] = $locked;
                }
                $lockUsers->close();

                $sender = $lockedUsers[$senderId] ?? null;
                $lockedRecipient = $lockedUsers[$recipientId] ?? null;
                if (!$sender || !$lockedRecipient || !hash_equals($recipientAccount, (string)$lockedRecipient['account_number'])) {
                    throw new RuntimeException('The sender or recipient account is unavailable. Start the transfer again.');
                }
                if ((int)$sender['is_on_hold'] === 1) {
                    throw new RuntimeException('Transfers are unavailable while your account is under review.');
                }
                if ((int)$lockedRecipient['is_on_hold'] === 1) {
                    throw new RuntimeException('Transfers to this account are temporarily unavailable.');
                }
                if ((float)$sender['walletbalance'] < $amountValue) {
                    throw new RuntimeException('Insufficient available wallet balance.');
                }

                $debit = $link->prepare('UPDATE users SET walletbalance = walletbalance - ? WHERE id = ? AND walletbalance >= ? AND is_on_hold = 0');
                $debit->bind_param('did', $amountValue, $senderId, $amountValue);
                if (!$debit->execute() || $debit->affected_rows !== 1) {
                    $debit->close();
                    throw new RuntimeException('Unable to debit the sender account.');
                }
                $debit->close();

                $credit = $link->prepare('UPDATE users SET walletbalance = walletbalance + ? WHERE id = ? AND is_on_hold = 0');
                $credit->bind_param('di', $amountValue, $recipientId);
                if (!$credit->execute() || $credit->affected_rows !== 1) {
                    $credit->close();
                    throw new RuntimeException('Unable to credit the recipient account.');
                }
                $credit->close();

                $insert = $link->prepare('INSERT INTO account_transfers (request_id, reference, sender_user_id, recipient_user_id, amount, note) VALUES (?, ?, ?, ?, ?, ?)');
                $insert->bind_param('ssiids', $requestId, $transferReference, $senderId, $recipientId, $amountValue, $note);
                if (!$insert->execute()) {
                    $insert->close();
                    throw new RuntimeException('Unable to record the transfer.');
                }
                $insert->close();

                $senderName = trim((string)$sender['fname'] . ' ' . (string)$sender['lname']) ?: $email;
                $recipientName = trim((string)$lockedRecipient['fname'] . ' ' . (string)$lockedRecipient['lname']) ?: $recipientAccount;
                $sentNotificationId = redman_email_enqueue($link, 'transfer-sent:' . $requestId, $email, 'transfer_sent', [
                    'name' => $senderName,
                    'amount' => $amountValue,
                    'reference' => $transferReference,
                    'detail' => $recipientAccount,
                ]);
                $receivedNotificationId = redman_email_enqueue($link, 'transfer-received:' . $requestId, (string)$lockedRecipient['email'], 'transfer_received', [
                    'name' => $recipientName,
                    'amount' => $amountValue,
                    'reference' => $transferReference,
                    'detail' => (string)$sender['account_number'],
                ]);

                if (!$link->commit()) {
                    throw new RuntimeException('Unable to complete the transfer.');
                }
                redman_email_dispatch($link, $sentNotificationId);
                redman_email_dispatch($link, $receivedNotificationId);
                $message = 'Transfer completed. Reference: ' . $transferReference;
                $currentUser['walletbalance'] = (float)$sender['walletbalance'] - $amountValue;
                unset($_SESSION['pending_transfer']);
                $pendingTransfer = null;
                $_SESSION['transfer_csrf'] = bin2hex(random_bytes(32));
            } catch (Throwable $exception) {
                $link->rollback();
                unset($_SESSION['pending_transfer']);
                $pendingTransfer = null;
                $error = $exception instanceof mysqli_sql_exception && $exception->getCode() === 1062
                    ? 'This transfer request was already processed.'
                    : ($exception instanceof mysqli_sql_exception ? 'The transfer could not be completed. Please try again.' : $exception->getMessage());
            }
        }
    } elseif ($action === 'cancel') {
        unset($_SESSION['pending_transfer']);
        $pendingTransfer = null;
        $message = 'Transfer cancelled. No funds were moved.';
    }
}

$history = $link->prepare("SELECT t.reference, t.amount, t.note, t.created_at, CASE WHEN t.sender_user_id = ? THEN 'Sent' ELSE 'Received' END AS direction, CASE WHEN t.sender_user_id = ? THEN recipient.account_number ELSE sender.account_number END AS other_account FROM account_transfers t JOIN users sender ON sender.id = t.sender_user_id JOIN users recipient ON recipient.id = t.recipient_user_id WHERE t.sender_user_id = ? OR t.recipient_user_id = ? ORDER BY t.id DESC LIMIT 50");
$userId = (int)$currentUser['id'];
$history->bind_param('iiii', $userId, $userId, $userId, $userId);
$history->execute();
$transferRows = $history->get_result();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transfer Funds | Redman Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{background:#101a22;color:#f4f4ef}.panel{background:#1b2a31;border:1px solid #35464a}.field{background:#101a22;border:1px solid #536367;color:#fff}</style>
</head>
<body class="min-h-screen">
<main class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><a class="text-sm text-emerald-300" href="./">Dashboard</a><h1 class="mt-2 text-2xl font-semibold">Transfer Funds</h1></div>
        <div class="panel px-4 py-3" data-account-container><span class="text-sm text-gray-300">Your account number</span><div class="flex items-center gap-2"><p class="font-mono text-lg" id="own-account-number"><?php echo htmlspecialchars($currentUser['account_number'], ENT_QUOTES, 'UTF-8'); ?></p><button type="button" data-copy-account="own-account-number" class="px-2 py-1 text-emerald-300 hover:text-white" aria-label="Copy account number" title="Copy account number"><i class="fa fa-copy" aria-hidden="true"></i></button></div><span class="text-xs text-gray-400" data-copy-account-status aria-live="polite"></span></div>
    </div>
    <?php if ($message !== ''): ?><p class="mb-4 border border-emerald-700 bg-emerald-950 p-4" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="mb-4 border border-red-700 bg-red-950 p-4" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <section class="panel p-5">
        <h2 class="mb-4 text-lg font-medium">Send to a Redman Finance customer</h2>
        <div class="mb-5 flex items-center justify-between text-sm text-gray-300"><p>Available balance: <span data-private-balance="<?php echo htmlspecialchars(number_format((float)$currentUser['walletbalance'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">••••••</span></p><button type="button" data-balance-toggle class="px-3 py-2 text-gray-300 hover:text-white" aria-pressed="false" aria-label="Show balances" title="Show balances"><i class="fa fa-eye" aria-hidden="true"></i></button></div>
        <?php if ($pendingTransfer): ?>
            <div class="mb-5 border border-amber-500 bg-amber-950/40 p-4" role="alert">
                <h3 class="text-lg font-semibold text-amber-200">Confirm recipient before sending</h3>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-400">Full name</dt><dd class="text-lg font-medium"><?php echo htmlspecialchars($pendingTransfer['recipient_name'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt class="text-gray-400">Account number</dt><dd class="font-mono text-lg"><?php echo htmlspecialchars($pendingTransfer['recipient_account'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt class="text-gray-400">Amount</dt><dd>$<?php echo htmlspecialchars($pendingTransfer['amount'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <?php if ($pendingTransfer['note'] !== ''): ?><div><dt class="text-gray-400">Note</dt><dd><?php echo htmlspecialchars($pendingTransfer['note'], ENT_QUOTES, 'UTF-8'); ?></dd></div><?php endif; ?>
                </dl>
                <p class="mt-4 text-sm text-amber-100">Check that the name and account number match the person you intend to pay. Transfers cannot be reversed.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['transfer_csrf'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="confirm_token" value="<?php echo htmlspecialchars($pendingTransfer['confirm_token'], ENT_QUOTES, 'UTF-8'); ?>"><button class="bg-emerald-400 px-4 py-2 font-semibold text-gray-950" type="submit">Confirm transfer</button></form>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['transfer_csrf'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="cancel"><button class="border border-gray-500 px-4 py-2" type="submit">Cancel</button></form>
                </div>
            </div>
        <?php else: ?>
            <form method="post" class="grid gap-4 md:grid-cols-2" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['transfer_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="lookup">
                <label class="grid gap-2 text-sm">Recipient account number<input class="field px-3 py-2" name="recipient_account" inputmode="text" pattern="RF[0-9]{10}" placeholder="RF0000000000" maxlength="12" required></label>
                <label class="grid gap-2 text-sm">Amount (USD)<input class="field px-3 py-2" name="amount" type="number" min="0.01" step="0.01" required></label>
                <label class="grid gap-2 text-sm md:col-span-2">Note (optional)<input class="field px-3 py-2" name="note" maxlength="140"></label>
                <button class="bg-emerald-400 px-4 py-2 font-semibold text-gray-950 md:col-span-2" type="submit">Find recipient</button>
            </form>
        <?php endif; ?>
    </section>
    <section class="mt-8">
        <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-medium">Recent transfers</h2><button type="button" data-balance-toggle class="px-3 py-2 text-gray-300 hover:text-white" aria-pressed="false" aria-label="Show balances" title="Show balances"><i class="fa fa-eye" aria-hidden="true"></i></button></div>
        <div class="overflow-x-auto border border-gray-700">
            <table class="w-full min-w-[640px] text-left text-sm"><thead class="bg-gray-800"><tr><th class="p-3">Date</th><th class="p-3">Direction</th><th class="p-3">Other account</th><th class="p-3">Amount</th><th class="p-3">Reference</th></tr></thead><tbody>
            <?php if ($transferRows->num_rows === 0): ?><tr><td class="p-3 text-gray-400" colspan="5">No transfers yet.</td></tr><?php endif; ?>
            <?php while ($transfer = $transferRows->fetch_assoc()): ?><tr class="border-t border-gray-700"><td class="p-3"><?php echo htmlspecialchars($transfer['created_at'], ENT_QUOTES, 'UTF-8'); ?></td><td class="p-3"><?php echo htmlspecialchars($transfer['direction'], ENT_QUOTES, 'UTF-8'); ?></td><td class="p-3 font-mono"><?php echo htmlspecialchars($transfer['other_account'], ENT_QUOTES, 'UTF-8'); ?></td><td class="p-3" data-private-balance="<?php echo htmlspecialchars(number_format((float)$transfer['amount'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">••••••</td><td class="p-3 font-mono"><?php echo htmlspecialchars($transfer['reference'], ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endwhile; ?>
            </tbody></table>
        </div>
    </section>
</main>
<script src="assets/js/balance-privacy.js"></script>
<script src="assets/js/account-number.js"></script>
</body>
</html>