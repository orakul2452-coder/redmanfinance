<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

$status = 'invalid';
$submittedToken = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($submittedToken) ? trim($submittedToken) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    $tokenHash = hash('sha256', $token);
    $link->begin_transaction();
    try {
        $select = $link->prepare('SELECT id, email, pending_email FROM users WHERE email_verification_token_hash = ? AND email_verification_expires_at >= NOW() LIMIT 1 FOR UPDATE');
        if (!$select) {
            throw new RuntimeException('Verification is temporarily unavailable.');
        }
        $select->bind_param('s', $tokenHash);
        if (!$select->execute()) {
            $select->close();
            throw new RuntimeException('Verification is temporarily unavailable.');
        }
        $user = $select->get_result()->fetch_assoc();
        $select->close();

        if (!$user) {
            $link->rollback();
            $status = 'expired';
        } else {
            $newEmail = trim((string)($user['pending_email'] ?? ''));
            if ($newEmail !== '') {
                $check = $link->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                if (!$check) {
                    throw new RuntimeException('Unable to check the requested email address.');
                }
                $check->bind_param('si', $newEmail, $user['id']);
                if (!$check->execute()) {
                    $check->close();
                    throw new RuntimeException('Unable to check the requested email address.');
                }
                $duplicate = $check->get_result()->fetch_assoc();
                $check->close();
                if ($duplicate) {
                    $link->rollback();
                    $status = 'address_taken';
                } else {
                    $update = $link->prepare('UPDATE users SET email = ?, email_verified = 1, pending_email = NULL, email_verification_token_hash = NULL, email_verification_expires_at = NULL WHERE id = ? AND email_verification_token_hash = ?');
                    if (!$update) {
                        throw new RuntimeException('Unable to prepare email confirmation.');
                    }
                    $update->bind_param('sis', $newEmail, $user['id'], $tokenHash);
                    if (!$update->execute() || $update->affected_rows !== 1) {
                        $update->close();
                        throw new RuntimeException('Unable to confirm email change.');
                    }
                    $update->close();
                    if (!$link->commit()) {
                        throw new RuntimeException('Unable to commit email change.');
                    }
                    if ((string)($_SESSION['email'] ?? '') === (string)$user['email']) {
                        $_SESSION['email'] = $newEmail;
                    }
                    $status = 'changed';
                }
            } else {
                $update = $link->prepare('UPDATE users SET email_verified = 1, email_verification_token_hash = NULL, email_verification_expires_at = NULL WHERE id = ? AND email_verification_token_hash = ?');
                if (!$update) {
                    throw new RuntimeException('Unable to prepare email verification.');
                }
                $update->bind_param('is', $user['id'], $tokenHash);
                if (!$update->execute() || $update->affected_rows !== 1) {
                    $update->close();
                    throw new RuntimeException('Unable to verify email address.');
                }
                $update->close();
                if (!$link->commit()) {
                    throw new RuntimeException('Unable to commit email verification.');
                }
                $status = 'verified';
            }
        }
    } catch (Throwable $error) {
        $link->rollback();
        error_log('Email verification failed: ' . $error->getMessage());
        $status = 'error';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification | Redman Finance</title>
    <style>
        body{margin:0;background:#0d1627;color:#f3f4f6;font-family:Arial,sans-serif;display:grid;min-height:100vh;place-items:center;padding:24px}
        main{width:min(100%,520px);background:#1a2639;border:1px solid #334155;padding:28px}
        h1{color:#f4d35e;font-size:24px;margin:0 0 12px}
        p{line-height:1.6;color:#d1d5db}
        a,.a{display:inline-block;margin-top:12px;color:#111827;background:#f4d35e;padding:12px 16px;text-decoration:none;font-weight:bold}
        button{border:0;cursor:pointer;font:inherit}
    </style>
</head>
<body>
<main>
    <?php if ($status === 'invalid' && preg_match('/\A[a-f0-9]{64}\z/', $token) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <h1>Confirm your email</h1><p>Select below to verify your email address or apply your requested email change.</p>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="a">Confirm email address</button>
        </form>
    <?php elseif ($status === 'verified'): ?>
        <h1>Email verified</h1><p>Your account email is verified. You can now sign in.</p>
    <?php elseif ($status === 'changed'): ?>
        <h1>Email updated</h1><p>Your new email address is verified and has been applied to your account.</p>
    <?php elseif ($status === 'expired'): ?>
        <h1>Link expired or already used</h1><p>Request a new verification link to continue.</p><a href="resend-verification.php">Resend verification email</a>
    <?php elseif ($status === 'address_taken'): ?>
        <h1>Email address unavailable</h1><p>That address is already associated with another account. Your current account email was not changed.</p>
    <?php elseif ($status === 'invalid'): ?>
        <h1>Invalid verification link</h1><p>Request a new verification email to continue.</p><a href="resend-verification.php">Resend verification email</a>
    <?php else: ?>
        <h1>Verification unavailable</h1><p>Please try again later or contact support.</p>
    <?php endif; ?>
    <a href="login.php">Go to sign in</a>
</main>
</body>
</html>
