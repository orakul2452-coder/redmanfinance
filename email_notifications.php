<?php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function redman_email_delivery_paused(): bool
{
    $value = strtolower(trim((string)(getenv('EMAIL_DELIVERY_PAUSED') ?: '')));
    return $value !== '' && in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function redman_smtp_ports(): array
{
    $primarySetting = getenv('SMTP_PORT') ?: '587';
    $primary = filter_var($primarySetting, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($primary === false || !in_array($primary, [465, 587], true)) {
        throw new InvalidArgumentException('SMTP_PORT must be 465 or 587.');
    }

    $fallbackSetting = getenv('SMTP_PORT_FALLBACK') ?: (string)($primary === 465 ? 587 : 465);
    $fallback = filter_var($fallbackSetting, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($fallback === false || !in_array($fallback, [465, 587], true) || $fallback === $primary) {
        throw new InvalidArgumentException('SMTP_PORT_FALLBACK must be the other port: 465 or 587.');
    }

    return [$primary, $fallback];
}

function redman_make_send_email(PHPMailer\PHPMailer\PHPMailer $mail, string $context, ?callable $request = null): void
{
    if (redman_email_delivery_paused()) {
        return;
    }

    $webhookUrl = trim((string)(getenv('MAKE_EMAIL_WEBHOOK_URL') ?: getenv('MAKE_FINANCE_ALERT_WEBHOOK_URL') ?: ''));
    if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL) || strtolower((string)parse_url($webhookUrl, PHP_URL_SCHEME)) !== 'https') {
        throw new RuntimeException('A valid HTTPS Make email webhook URL is not configured.');
    }
    $recipients = array_map(static fn(array $address): string => $address[0], $mail->getToAddresses());
    if ($recipients === [] || $mail->From === '') {
        throw new RuntimeException('Email sender and recipient must be configured.');
    }

    $payload = json_encode([
        'schema_version' => 1,
        'type' => 'email',
        'to' => implode(',', $recipients),
        'from' => $mail->From,
        'from_name' => $mail->FromName,
        'subject' => $mail->Subject,
        'html' => $mail->Body,
        'text' => $mail->AltBody,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $request ??= static function (string $url, string $payload): array {
        $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
        $apiKey = trim((string)(getenv('MAKE_FINANCE_ALERT_WEBHOOK_API_KEY') ?: ''));
        if ($apiKey !== '') {
            $headers .= 'x-make-apikey: ' . $apiKey . "\r\n";
        }
        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];
        $response = @file_get_contents($url, false, stream_context_create($contextOptions));
        $status = 0;
        if (preg_match('/\\s(\\d{3})\\s/', $http_response_header[0] ?? '', $matches)) {
            $status = (int)$matches[1];
        }
        return [$status, is_string($response) ? $response : ''];
    };

    [$status, $responseBody] = $request($webhookUrl, $payload);
    $result = json_decode((string)$responseBody, true);
    $isSuccess = $status >= 200 && $status < 300 && (
        trim((string)$responseBody) === '' ||
        (
            is_array($result) && (
                ($result['delivered'] ?? false) === true ||
                ($result['accepted'] ?? false) === true ||
                ($result['success'] ?? false) === true ||
                in_array(strtolower((string)($result['status'] ?? '')), ['delivered', 'accepted', 'sent', 'success', 'queued'], true)
            )
        )
    );

    if (!$isSuccess) {
        error_log($context . ' Make webhook did not confirm delivery (HTTP ' . (int)$status . ').');
        throw new RuntimeException($context . ' Make webhook did not confirm delivery.');
    }
}

function redman_email_send_with_smtp_fallback(callable $configureMessage, string $context): void
{
    if (redman_email_delivery_paused()) {
        return;
    }

    $makeWebhookUrl = trim((string)(getenv('MAKE_EMAIL_WEBHOOK_URL') ?: getenv('MAKE_FINANCE_ALERT_WEBHOOK_URL') ?: ''));
    if ($makeWebhookUrl !== '') {
        $mail = $configureMessage();
        if (!$mail instanceof PHPMailer\PHPMailer\PHPMailer) {
            throw new RuntimeException('Email message factory must return a PHPMailer instance.');
        }
        redman_make_send_email($mail, $context);
        return;
    }

    $ports = redman_smtp_ports();
    $host = getenv('SMTP_HOST') ?: '';
    $username = getenv('SMTP_USERNAME') ?: '';
    $password = getenv('SMTP_PASSWORD') ?: '';
    if ($host === '' || $username === '' || $password === '') {
        throw new RuntimeException('SMTP_HOST, SMTP_USERNAME, and SMTP_PASSWORD must be configured.');
    }
    $lastError = null;

    foreach ($ports as $attempt => $port) {
        try {
            $mail = $configureMessage();
            if (!$mail instanceof PHPMailer\PHPMailer\PHPMailer) {
                throw new RuntimeException('SMTP message factory must return a PHPMailer instance.');
            }
            $mail->isSMTP();
            $mail->Timeout = 8;
            $mail->getSMTPInstance()->Timelimit = 8;
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->SMTPSecure = $port === 465 ? 'ssl' : 'tls';
            $mail->send();

            if ($attempt > 0) {
                error_log($context . ' succeeded using SMTP fallback port ' . $port . '.');
            }
            return;
        } catch (Throwable $error) {
            $lastError = $error;
            error_log(
                $context . ' failed using SMTP host ' . ($host !== '' ? $host : '(empty)') .
                ':' . $port . ' (' . ($port === 465 ? 'ssl' : 'tls') . '): ' . $error->getMessage()
            );
        }
    }

    throw new RuntimeException($context . ' failed using both SMTP ports.', 0, $lastError);
}

function redman_email_enqueue(mysqli $link, string $eventKey, string $recipient, string $eventType, array $payload): int
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email notification recipient is invalid.');
    }

    $allowedEvents = [
        'welcome', 'deposit_pending', 'deposit_approved', 'deposit_vaulted', 'deposit_rejected',
        'wallet_topup_pending', 'wallet_topup_approved', 'wallet_topup_vaulted', 'wallet_topup_rejected',
        'withdrawal_pending', 'withdrawal_approved', 'withdrawal_rejected',
        'kyc_verified', 'kyc_rejected', 'compliance_submitted', 'compliance_more_info', 'risk_hold_started', 'release_cooldown_started', 'vault_released',
        'transfer_sent', 'transfer_received', 'investment_activated', 'investment_completed',
        'referral_cashback_paid', 'email_verification', 'password_reset_requested', 'password_changed',
    ];
    if (!in_array($eventType, $allowedEvents, true)) {
        throw new InvalidArgumentException('Unknown email notification event.');
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $insert = $link->prepare("INSERT INTO email_outbox (event_key, recipient_email, event_type, payload) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
    if (!$insert) {
        throw new RuntimeException('Unable to queue email notification.');
    }
    $insert->bind_param('ssss', $eventKey, $recipient, $eventType, $json);
    if (!$insert->execute()) {
        $insert->close();
        throw new RuntimeException('Unable to queue email notification.');
    }
    $id = (int)$link->insert_id;
    $insert->close();
    return $id;
}

function redman_email_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redman_email_render(string $eventType, array $payload): array
{
    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://redmanfinance.onrender.com', '/');
    $logoUrl = $baseUrl . '/public/REDMAN%20FINANCE.svg';
    $supportPhone = defined('REDMAN_SUPPORT_PHONE_LABEL') ? REDMAN_SUPPORT_PHONE_LABEL : '+1 (816) 748-8978';
    $supportTel = defined('REDMAN_SUPPORT_PHONE') ? REDMAN_SUPPORT_PHONE : '+18167488978';
    $name = redman_email_escape($payload['name'] ?? 'there');
    $amount = isset($payload['amount']) ? '$' . number_format((float)$payload['amount'], 2) : '';
    $reference = redman_email_escape($payload['reference'] ?? '');
    $reason = nl2br(redman_email_escape($payload['reason'] ?? ''));
    $detail = redman_email_escape($payload['detail'] ?? '');
    $title = 'Account update';
    $message = 'There is an update to your Redman Finance account.';
    $cta = '';

    switch ($eventType) {
        case 'welcome':
            $title = 'Welcome to Redman Finance';
            $message = 'Your account has been created. Investments involve risk; returns are not guaranteed. Review the relevant terms before making an investment decision.';
            $cta = '<a href="' . $baseUrl . '/trade/login.php" style="display:inline-block;padding:12px 18px;background:#f4d35e;color:#101820;text-decoration:none;font-weight:bold">Sign in</a>';
            break;
        case 'deposit_pending':
        case 'wallet_topup_pending':
            $title = 'Deposit request received';
            $message = 'Your deposit request ' . $reference . ' for ' . $amount . ' is pending review. Your account will be updated if it is approved.';
            break;
        case 'deposit_approved':
            $title = 'Deposit approved';
            $message = 'Your deposit request ' . $reference . ' for ' . $amount . ' has been approved and your investment has been activated.';
            break;
        case 'wallet_topup_approved':
            $title = 'Wallet top-up approved';
            $message = 'Your wallet top-up ' . $reference . ' for ' . $amount . ' has been approved and credited to your available wallet.';
            break;
        case 'deposit_vaulted':
        case 'wallet_topup_vaulted':
            $title = 'Deposit received during account review';
            $message = 'Your deposit request ' . $reference . ' for ' . $amount . ' was approved and recorded in the restricted vault while your account is under review. It is not available to spend or withdraw until the review release process is completed. No additional payment is required to request review.';
            break;
        case 'deposit_rejected':
        case 'wallet_topup_rejected':
            $title = 'Deposit request not approved';
            $message = 'Your deposit request ' . $reference . ' for ' . $amount . ' was not approved.';
            break;
        case 'withdrawal_pending':
            $title = 'Withdrawal request received';
            $message = 'Your withdrawal request ' . $reference . ' for ' . $amount . ' is pending review. Processing time may vary; we will update you when its status changes.';
            break;
        case 'withdrawal_approved':
            $title = 'Withdrawal approved';
            $message = 'Your withdrawal request ' . $reference . ' for ' . $amount . ' has been approved.';
            if ($detail !== '') {
                $message .= '<br><br>Approved destination: ' . $detail;
            }
            break;
        case 'withdrawal_rejected':
            $title = 'Withdrawal request not approved';
            $refundLocation = ($payload['refund_location'] ?? 'wallet') === 'vault' ? 'restricted vault' : 'available wallet';
            $message = 'Your withdrawal request ' . $reference . ' for ' . $amount . ' was not approved. The reserved amount has been returned to your ' . $refundLocation . '.';
            break;
        case 'kyc_verified':
            $title = 'Identity review completed';
            $message = 'Your identity documents have been marked as verified.';
            break;
        case 'kyc_rejected':
            $title = 'Identity review needs attention';
            $message = 'Your identity documents could not be approved.';
            break;
        case 'compliance_more_info':
            $title = 'More information requested';
            $message = 'Our Compliance Review Team needs additional information for your case.';
            break;
        case 'compliance_submitted':
            $title = 'Compliance documents submitted';
            $message = 'Your compliance case has been submitted for review. We will notify you when its status changes.';
            break;
        case 'risk_hold_started':
            $title = 'Account review started';
            $message = 'Some account activity is temporarily restricted while we review your account. A new deposit or payment is not required to request review or release of funds already held.';
            break;
        case 'release_cooldown_started':
            $title = 'Review checks complete';
            $message = 'Your submitted documents, identity review, and required account history have passed their checks. A release cooldown is now in progress. Staff action is still required after it ends; funds are not released automatically.';
            break;
        case 'vault_released':
            $title = 'Account review completed';
            $message = 'The restricted vault funds have been released to your available wallet.';
            break;
        case 'transfer_sent':
            $title = 'Transfer sent';
            $message = 'Your transfer ' . $reference . ' for ' . $amount . ' was sent to account ' . $detail . '.';
            break;
        case 'transfer_received':
            $title = 'Transfer received';
            $message = 'You received a transfer ' . $reference . ' for ' . $amount . ' from account ' . $detail . '.';
            break;
        case 'investment_activated':
            $title = 'Investment activated';
            $message = 'Your investment ' . $reference . ' for ' . $amount . ' has been activated.';
            break;
        case 'investment_completed':
            $title = 'Investment completed';
            $message = 'Your investment ' . $reference . ' has completed. Check your account for the recorded outcome.';
            break;
        case 'referral_cashback_paid':
            $title = 'Referral cashback credited';
            $message = 'A referred customer completed their first qualifying investment. A $500 referral cashback reward has been credited to your account.';
            break;
        case 'email_verification':
            $title = 'Verify your email address';
            $pendingEmail = trim((string)($payload['pending_email'] ?? ''));
            $tokenSecret = getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
            $verificationUserId = filter_var($payload['verification_user_id'] ?? null, FILTER_VALIDATE_INT);
            $tokenNonce = (string)($payload['verification_token_nonce'] ?? '');
            $expiresTimestamp = filter_var($payload['verification_expires_timestamp'] ?? null, FILTER_VALIDATE_INT);
            if ($tokenSecret === '' || $verificationUserId === false || $verificationUserId < 1 || !preg_match('/^[a-f0-9]{64}$/', $tokenNonce) || $expiresTimestamp === false || $expiresTimestamp < time()) {
                throw new RuntimeException('Email verification message is invalid or expired.');
            }
            $token = hash_hmac('sha256', $verificationUserId . ':' . $expiresTimestamp . ':' . $tokenNonce, $tokenSecret);
            $verificationUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://redmanfinance.onrender.com', '/') . '/trade/verify-email.php?token=' . rawurlencode($token);
            $message = $pendingEmail !== ''
                ? 'Confirm this address to update the email on your Redman Finance account to ' . redman_email_escape($pendingEmail) . '.'
                : 'Confirm that you own this email address to finish setting up your Redman Finance account.';
            $cta = '<a href="' . redman_email_escape($verificationUrl) . '" style="display:inline-block;padding:12px 18px;background:#f4d35e;color:#101820;text-decoration:none;font-weight:bold">Verify email address</a>';
            $message .= '<br><br>This link expires in 24 hours. If you did not request this, you can ignore this email.';
            break;
        case 'password_reset_requested':
            $title = 'Password reset requested';
            $message = 'A password reset was requested for your account. Use the secure one-time link below if this was you.';
            $cta = '<a href="' . redman_email_escape($payload['reset_url'] ?? '') . '" style="display:inline-block;padding:12px 18px;background:#f4d35e;color:#101820;text-decoration:none;font-weight:bold">Reset password</a>';
            break;
        case 'password_changed':
            $title = 'Password changed';
            $message = 'Your account password was changed. If you did not make this change, contact support promptly.';
            break;
    }

    if ($reason !== '') {
        $message .= '<br><br><strong>Reason:</strong> ' . $reason;
    }
    if ($detail !== '' && !in_array($eventType, ['transfer_sent', 'transfer_received'], true)) {
        $message .= '<br><br>' . $detail;
    }

    $html = '<!doctype html><html><body style="margin:0;background:#f2f4f5;color:#19242b;font-family:Arial,sans-serif"><div style="max-width:600px;margin:24px auto;background:#fff;border:1px solid #d7dfe2"><div style="padding:22px;background:#11212b;text-align:center"><img src="' . $logoUrl . '" alt="Redman Finance" style="max-height:46px"><h1 style="margin:16px 0 0;color:#fff;font-size:22px">' . redman_email_escape($title) . '</h1></div><div style="padding:24px"><p>Hello ' . $name . ',</p><p style="line-height:1.6">' . $message . '</p>' . $cta . ($reference !== '' ? '<p style="color:#586970;font-size:13px">Reference: ' . $reference . '</p>' : '') . '</div><div style="padding:16px;background:#edf1f2;color:#586970;font-size:12px">Automated account notification from Redman Finance. Support: <a href="tel:' . rawurlencode($supportTel) . '" style="color:#0d6efd;text-decoration:none">' . redman_email_escape($supportPhone) . '</a> • SMS / Telegram support available.</div></div></body></html>';
    $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
    $plain .= "\n\nSupport: " . $supportPhone . "\nSMS / Telegram support is available.";
    if (isset($payload['reset_url']) && $eventType === 'password_reset_requested') {
        $plain .= "\n\nReset password: " . $payload['reset_url'];
    }
    return [$title . ' | Redman Finance', $html, $plain];
}

function redman_email_dispatch(mysqli $link, int $id): bool
{
    $link->begin_transaction();
    try {
        $select = $link->prepare("SELECT id, recipient_email, event_type, payload, attempts FROM email_outbox WHERE id = ? AND status = 'queued' AND available_at <= NOW() FOR UPDATE");
        $select->bind_param('i', $id);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();
        if (!$row) {
            $link->rollback();
            return false;
        }

        $attempts = (int)$row['attempts'] + 1;
        $claim = $link->prepare("UPDATE email_outbox SET status = 'sending', attempts = ? WHERE id = ? AND status = 'queued'");
        $claim->bind_param('ii', $attempts, $id);
        $claim->execute();
        $claimed = $claim->affected_rows === 1;
        $claim->close();
        if (!$claimed || !$link->commit()) {
            $link->rollback();
            return false;
        }
    } catch (Throwable $error) {
        $link->rollback();
        error_log('Email outbox claim failed for id ' . $id);
        return false;
    }

    try {
        $payload = json_decode((string)$row['payload'], true, 512, JSON_THROW_ON_ERROR);
        [$subject, $html, $plain] = redman_email_render((string)$row['event_type'], $payload);
        redman_email_send_with_smtp_fallback(static function () use ($row, $subject, $html, $plain) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: (getenv('SMTP_USERNAME') ?: ''), getenv('MAIL_FROM_NAME') ?: 'Redman Finance');
            $mail->addAddress((string)$row['recipient_email']);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $plain;
            return $mail;
        }, 'Email outbox ' . $id . ' event ' . $row['event_type']);

        $sent = $link->prepare("UPDATE email_outbox SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ? AND status = 'sending'");
        $sent->bind_param('i', $id);
        $sent->execute();
        $sent->close();
        return true;
    } catch (Throwable $error) {
        $nextStatus = $attempts >= 5 ? 'failed' : 'queued';
        $delayMinutes = min(60, 2 ** $attempts);
        $safeError = 'Delivery failed; see application logs.';
        $failed = $link->prepare('UPDATE email_outbox SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? MINUTE), last_error = ? WHERE id = ?');
        $failed->bind_param('sisi', $nextStatus, $delayMinutes, $safeError, $id);
        $failed->execute();
        $failed->close();
        error_log('Email delivery failed for outbox id ' . $id . ' event ' . $row['event_type'] . ': ' . $error->getMessage());
        return false;
    }
}

function redman_email_enqueue_and_dispatch(mysqli $link, string $eventKey, string $recipient, string $eventType, array $payload): bool
{
    $id = redman_email_enqueue($link, $eventKey, $recipient, $eventType, $payload);
    return redman_email_dispatch($link, $id);
}

function redman_email_dispatch_pending(mysqli $link, int $limit = 10): int
{
    $limit = max(1, min(100, $limit));
    $result = $link->query("SELECT id FROM email_outbox WHERE status = 'queued' AND available_at <= NOW() ORDER BY id LIMIT " . $limit);
    if (!$result) {
        return 0;
    }

    $sent = 0;
    while ($row = $result->fetch_assoc()) {
        if (redman_email_dispatch($link, (int)$row['id'])) {
            $sent++;
        }
    }
    return $sent;
}