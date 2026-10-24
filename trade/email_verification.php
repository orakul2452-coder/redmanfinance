<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';

function redman_configure_smtp(PHPMailer $mail): void
{
    $portSetting = getenv('SMTP_PORT') ?: '465';
    $port = filter_var($portSetting, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false) {
        throw new \InvalidArgumentException('SMTP_PORT must be a valid TCP port number.');
    }

    $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'ssl');
    if ($encryption !== 'ssl' && $encryption !== 'tls') {
        throw new \InvalidArgumentException('SMTP_ENCRYPTION must be either ssl or tls.');
    }

    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: '';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: '';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = $encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $port;
}

function redman_send_verification_email(mysqli $link, int $userId, string $email, string $firstName): bool
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $update = $link->prepare(
        'UPDATE users
         SET email_verification_token_hash = ?, email_verification_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
         WHERE id = ? AND email_verified = 0'
    );
    if (!$update) {
        throw new RuntimeException('Unable to prepare email verification token update: ' . $link->error);
    }
    $update->bind_param('si', $tokenHash, $userId);
    if (!$update->execute()) {
        throw new RuntimeException('Unable to save email verification token: ' . $update->error);
    }
    if ($update->affected_rows !== 1) {
        $update->close();
        throw new RuntimeException('No pending account was found for email verification.');
    }
    $update->close();

    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://redmanfinance.onrender.com', '/');
    $verificationUrl = $baseUrl . '/trade/verify-email.php?token=' . rawurlencode($token);
    $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
    $mail = new PHPMailer(true);

    try {
        redman_configure_smtp($mail);
        $mail->setFrom(getenv('SMTP_USERNAME') ?: '', 'Redman Finance');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your Redman Finance email';
        $mail->Body = '
            <div style="background:#0D1627;color:#fff;padding:20px;font-family:Arial,sans-serif">
                <div style="max-width:600px;margin:0 auto;border:1px solid #f4d35e;border-radius:5px;padding:24px">
                    <h2 style="color:#f4d35e">Verify your email address</h2>
                    <p>Hello ' . $safeName . ',</p>
                    <p>Please verify your email address to finish creating your Redman Finance account.</p>
                    <p><a href="' . $safeUrl . '" style="background:#f4d35e;color:#0D1627;padding:12px 20px;text-decoration:none;border-radius:5px">Verify email</a></p>
                    <p>This link expires in 24 hours. If you did not create an account, you can ignore this email.</p>
                </div>
            </div>';
        $mail->AltBody = "Hello $firstName,\n\nVerify your Redman Finance email address using this link:\n$verificationUrl\n\nThis link expires in 24 hours. If you did not create an account, you can ignore this email.";
        $mail->send();
        return true;
    } catch (Exception | \InvalidArgumentException $exception) {
        error_log(
            'Email verification message failed for user ' . $userId .
            ' using SMTP host ' . ($mail->Host ?: '(empty)') . ':' . $mail->Port .
            ' (' . ($mail->SMTPSecure ?: 'no encryption') . '): ' .
            $exception->getMessage() . ' ' . $mail->ErrorInfo
        );
        return false;
    }
}

function redman_send_welcome_email(string $email, string $firstName, string $username, string $accountType): bool
{
    $mail = new PHPMailer(true);

    try {
        redman_configure_smtp($mail);
        $mail->setFrom(getenv('SMTP_USERNAME') ?: '', 'Redman Finance');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Redman Finance';
        $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeAccountType = htmlspecialchars($accountType, ENT_QUOTES, 'UTF-8');
        $mail->Body = '
            <div style="background:#0D1627;color:#fff;padding:20px;font-family:Arial,sans-serif">
                <div style="max-width:600px;margin:0 auto;border:1px solid #f4d35e;border-radius:5px;padding:24px">
                    <h2 style="color:#f4d35e">Welcome to Redman Finance</h2>
                    <p>Hello ' . $safeFirstName . ',</p>
                    <p>Your account has been created. Verify your email address before logging in.</p>
                    <p>Username: <strong>' . $safeUsername . '</strong><br>Account type: <strong>' . $safeAccountType . '</strong></p>
                </div>
            </div>';
        $mail->AltBody = "Welcome to Redman Finance, $firstName.\n\nYour account has been created. Verify your email address before logging in.\n\nUsername: $username\nAccount type: $accountType";
        $mail->send();
        return true;
    } catch (Exception | \InvalidArgumentException $exception) {
        error_log(
            'Welcome email failed using SMTP host ' . ($mail->Host ?: '(empty)') . ':' . $mail->Port .
            ' (' . ($mail->SMTPSecure ?: 'no encryption') . '): ' .
            $exception->getMessage() . ' ' . $mail->ErrorInfo
        );
        return false;
    }
}
