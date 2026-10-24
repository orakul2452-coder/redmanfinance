<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 2 || !filter_var($argv[1], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/test_smtp.php recipient@example.com\n");
    exit(2);
}

require_once __DIR__ . '/../email_notifications.php';

try {
    $smtpUsername = trim((string)(getenv('SMTP_USERNAME') ?: ''));
    $fromAddress = trim((string)(getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername));
    if ($fromAddress === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Set MAIL_FROM_ADDRESS to a valid verified sender address.');
    }

    redman_email_send_with_smtp_fallback(static function () use ($argv, $fromAddress) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->setFrom($fromAddress, getenv('MAIL_FROM_NAME') ?: 'Redman Finance');
        $mail->addAddress($argv[1]);
        $mail->isHTML(true);
        $mail->Subject = 'Redman Finance delivery test';
        $mail->Body = '<p>This is a test message confirming the Redman Finance email delivery configuration.</p>';
        $mail->AltBody = 'This is a test message confirming the Redman Finance email delivery configuration.';
        return $mail;
    }, 'Email delivery test');

    fwrite(STDOUT, "SMTP accepted the test email. Check the recipient inbox and spam folder.\n");
} catch (Throwable $error) {
    error_log('Email delivery test failed: ' . $error->getMessage());
    fwrite(STDERR, "Email delivery test failed: " . $error->getMessage() . "\n");
    exit(1);
}