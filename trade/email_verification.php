<?php

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
