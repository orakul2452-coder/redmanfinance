<?php

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
include_once __DIR__ . '/../db.php';

$message = '';
$token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '');
$validToken = is_string($token) && preg_match('/\A[a-f0-9]{64}\z/i', $token) === 1;
$tokenHash = $validToken ? hash('sha256', $token) : '';
$tokenIsUsable = false;

if ($validToken) {
    $check = $link->prepare(
        'SELECT id FROM users
         WHERE password_reset_token_hash = ? AND password_reset_expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    if (!$check) {
        throw new RuntimeException('Unable to prepare password reset token check: ' . $link->error);
    }
    $check->bind_param('s', $tokenHash);
    if (!$check->execute()) {
        throw new RuntimeException('Unable to check password reset token: ' . $check->error);
    }
    $check->store_result();
    $tokenIsUsable = $check->num_rows === 1;
    $check->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$validToken || !$tokenIsUsable) {
        $message = 'This reset link is invalid, expired, or already used. Request a new password reset link.';
    } elseif (!is_string($password) || strlen($password) < 8) {
        $message = 'Choose a password with at least 8 characters.';
    } elseif (!is_string($confirmPassword) || !hash_equals($password, $confirmPassword)) {
        $message = 'The password confirmation does not match.';
    } else {
        $update = $link->prepare(
            'UPDATE users
             SET password = ?, password_reset_token_hash = NULL, password_reset_expires_at = NULL
             WHERE password_reset_token_hash = ? AND password_reset_expires_at > UTC_TIMESTAMP()'
        );
        if (!$update) {
            throw new RuntimeException('Unable to prepare password update: ' . $link->error);
        }
        $update->bind_param('ss', $password, $tokenHash);
        if (!$update->execute()) {
            throw new RuntimeException('Unable to update password: ' . $update->error);
        }
        $message = $update->affected_rows === 1
            ? 'Your password has been reset. You can now log in with your new password.'
            : 'This reset link is invalid, expired, or already used. Request a new password reset link.';
        $tokenIsUsable = false;
        $update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password | Redman Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#0D1627] text-gray-200 flex items-center justify-center p-6">
    <main class="max-w-md w-full rounded-xl border border-gray-700 bg-gray-800 p-8">
        <h1 class="text-2xl font-bold text-[#f4d35e]">Reset your password</h1>
        <?php if ($message !== ''): ?>
            <p class="mt-4 rounded bg-gray-900 p-3" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($tokenIsUsable): ?>
            <form class="mt-6" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <label class="block text-sm mb-2" for="password">New password</label>
                <input class="w-full rounded border border-gray-600 bg-gray-700 p-3 text-white" id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                <label class="block text-sm mt-4 mb-2" for="confirm_password">Confirm new password</label>
                <input class="w-full rounded border border-gray-600 bg-gray-700 p-3 text-white" id="confirm_password" name="confirm_password" type="password" minlength="8" required autocomplete="new-password">
                <button class="mt-4 w-full rounded bg-[#f4d35e] p-3 font-semibold text-gray-900" type="submit">Reset password</button>
            </form>
        <?php elseif ($message === ''): ?>
            <p class="mt-4">This reset link is invalid or expired. Request a new one to continue.</p>
        <?php endif; ?>
        <p class="mt-6"><a class="text-[#f4d35e] underline" href="forgot_password.php">Request a new reset link</a></p>
        <p class="mt-2"><a class="text-[#f4d35e] underline" href="login.php">Back to login</a></p>
    </main>
</body>
</html>
