<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 2 || !filter_var($argv[1], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/test_smtp.php recipient@example.com\n");
    exit(2);
}

$smtpHost = getenv('SMTP_HOST') ?: '';
$smtpUsername = getenv('SMTP_USERNAME') ?: '';
$smtpPassword = getenv('SMTP_PASSWORD') ?: '';
$smtpPort = (int)(getenv('SMTP_PORT') ?: 465);
if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || !in_array($smtpPort, [465, 587], true)) {
    fwrite(STDERR, "SMTP settings are incomplete. Configure host, port, username and password in the local process environment.\n");
    exit(2);
}

require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort;
    $mail->SMTPSecure = $smtpPort === 465 ? 'ssl' : 'tls';
    $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername, getenv('MAIL_FROM_NAME') ?: 'Redman Finance');
    $mail->addAddress($argv[1]);
    $mail->isHTML(true);
    $mail->Subject = 'Redman Finance SMTP test';
    $mail->Body = '<p>This is a test message confirming the Redman Finance local SMTP configuration.</p>';
    $mail->AltBody = 'This is a test message confirming the Redman Finance local SMTP configuration.';
    $mail->send();
    fwrite(STDOUT, "SMTP test email sent successfully. Check the recipient inbox and spam folder.\n");
} catch (Throwable $error) {
    error_log('Local SMTP test failed: ' . $error->getMessage());
    fwrite(STDERR, "SMTP test failed. Check the local SMTP settings and see the local PHP error log for details.\n");
    exit(1);
}