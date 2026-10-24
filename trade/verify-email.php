<?php

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
include_once __DIR__ . '/../db.php';

$message = 'This link is invalid, expired, or already used. If you have already verified your email, log in; otherwise request a new verification email.';
$verified = false;
$token = $_GET['token'] ?? '';

if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/i', $token) === 1) {
    $tokenHash = hash('sha256', $token);
    $update = $link->prepare(
        'UPDATE users
         SET email_verified = 1, email_verification_token_hash = NULL, email_verification_expires_at = NULL
         WHERE email_verification_token_hash = ? AND email_verification_expires_at > UTC_TIMESTAMP() AND email_verified = 0'
    );
    if (!$update) {
        throw new RuntimeException('Unable to prepare email verification: ' . $link->error);
    }
    $update->bind_param('s', $tokenHash);
    if (!$update->execute()) {
        throw new RuntimeException('Unable to verify email address: ' . $update->error);
    }
    $verified = $update->affected_rows === 1;
    $update->close();

    if ($verified) {
        $message = 'Your email address is verified. You can now log in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email verification | Redman Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#0D1627] text-gray-200 flex items-center justify-center p-6">
    <main class="max-w-lg w-full rounded-xl border border-gray-700 bg-gray-800 p-8 text-center">
        <h1 class="text-2xl font-bold text-[#f4d35e]"><?php echo $verified ? 'Email verified' : 'Verification link unavailable'; ?></h1>
        <p class="mt-4"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!$verified): ?>
            <p class="mt-3"><a class="text-[#f4d35e] underline" href="resend-verification.php">Request a new verification email</a></p>
        <?php endif; ?>
        <p class="mt-6"><a class="text-[#f4d35e] underline" href="login.php">Go to login</a></p>
    </main>
</body>
</html>
