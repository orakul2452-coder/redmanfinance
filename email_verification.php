<?php

require_once __DIR__ . '/email_notifications.php';

function redman_email_verification_issue(mysqli $link, int $userId, string $recipient, ?string $pendingEmail = null): bool
{
    if ($userId < 1 || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid account and email address are required.');
    }

    $tokenSecret = getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
    if ($tokenSecret === '') {
        throw new RuntimeException('Email verification signing secret is unavailable.');
    }
    $tokenNonce = bin2hex(random_bytes(32));
    $expiresTimestamp = time() + 86400;
    $token = hash_hmac('sha256', $userId . ':' . $expiresTimestamp . ':' . $tokenNonce, $tokenSecret);
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', $expiresTimestamp);
    $link->begin_transaction();
    try {
        $update = $link->prepare('UPDATE users SET pending_email = ?, email_verification_token_hash = ?, email_verification_expires_at = ? WHERE id = ?');
        if (!$update) {
            throw new RuntimeException('Unable to prepare email verification.');
        }
        $update->bind_param('sssi', $pendingEmail, $tokenHash, $expiresAt, $userId);
        if (!$update->execute() || $update->affected_rows !== 1) {
            $update->close();
            throw new RuntimeException('Unable to save email verification request.');
        }
        $update->close();

        $notificationId = redman_email_enqueue($link, 'email-verification:user:' . $userId . ':' . $tokenHash, $recipient, 'email_verification', [
            'name' => 'there',
            'verification_user_id' => $userId,
            'verification_token_nonce' => $tokenNonce,
            'verification_expires_timestamp' => $expiresTimestamp,
            'pending_email' => $pendingEmail,
        ]);
        if (!$link->commit()) {
            throw new RuntimeException('Unable to commit email verification request.');
        }
        redman_email_dispatch($link, $notificationId);
        return true;
    } catch (Throwable $error) {
        $link->rollback();
        throw $error;
    }
}
