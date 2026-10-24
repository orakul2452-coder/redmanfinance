<?php

include_once __DIR__ . '/../db.php';
require_once __DIR__ . '/email_verification.php';

$message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmail = $_POST['email'] ?? '';
    $email = is_string($submittedEmail) ? trim($submittedEmail) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Enter a valid email address.';
    } else {
        $query = $link->prepare('SELECT id, fname FROM users WHERE email = ? AND email_verified = 0 LIMIT 1');
        if (!$query) {
            throw new RuntimeException('Unable to prepare resend lookup: ' . $link->error);
        }
        $query->bind_param('s', $email);
        if (!$query->execute()) {
            throw new RuntimeException('Unable to look up resend request: ' . $query->error);
        }
        $query->bind_result($userId, $firstName);
        $pendingAccount = $query->fetch();
        $query->close();

        if ($pendingAccount && !redman_send_verification_email($link, (int) $userId, $email, (string) $firstName)) {
            $message = 'We could not send the email right now. Please try again later.';
        } else {
            $message = 'If an unverified account uses that email address, a new verification link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resend verification | Redman Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#0D1627] text-gray-200 flex items-center justify-center p-6">
    <main class="max-w-md w-full rounded-xl border border-gray-700 bg-gray-800 p-8">
        <h1 class="text-2xl font-bold text-[#f4d35e]">Resend verification email</h1>
        <p class="mt-3">Enter the email address used to register.</p>
        <?php if ($message !== ''): ?>
            <p class="mt-4 rounded bg-gray-900 p-3" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form class="mt-6" method="post">
            <label class="block text-sm mb-2" for="email">Email address</label>
            <input class="w-full rounded border border-gray-600 bg-gray-700 p-3 text-white" id="email" name="email" type="email" required autocomplete="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            <button class="mt-4 w-full rounded bg-[#f4d35e] p-3 font-semibold text-gray-900" type="submit">Send verification link</button>
        </form>
        <p class="mt-6"><a class="text-[#f4d35e] underline" href="login.php">Back to login</a></p>
    </main>
</body>
</html>
