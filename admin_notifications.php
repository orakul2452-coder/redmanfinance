<?php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function redman_admin_alert_enqueue(mysqli $link, string $eventKey, string $eventType, array $payload): int
{
    $allowedEvents = [
        'investment_deposit_submitted',
        'wallet_topup_submitted',
        'withdrawal_submitted',
        'compliance_case_submitted',
    ];
    if (!in_array($eventType, $allowedEvents, true)) {
        throw new InvalidArgumentException('Unknown admin alert event.');
    }

    $body = json_encode([
        'schema_version' => 1,
        'event_key' => $eventKey,
        'event_type' => $eventType,
        'occurred_at' => gmdate('c'),
        'data' => $payload,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $insert = $link->prepare("INSERT INTO admin_alert_outbox (event_key, event_type, payload) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
    if (!$insert) {
        throw new RuntimeException('Unable to queue admin alert.');
    }
    $insert->bind_param('sss', $eventKey, $eventType, $body);
    if (!$insert->execute()) {
        $insert->close();
        throw new RuntimeException('Unable to queue admin alert.');
    }
    $id = (int)$link->insert_id;
    $insert->close();
    return $id;
}

function redman_admin_alert_send_email(array $alert): bool
{
    $smtpHost = trim((string)(getenv('SMTP_HOST') ?: ''));
    $smtpUsername = trim((string)(getenv('SMTP_USERNAME') ?: ''));
    $smtpPassword = (string)(getenv('SMTP_PASSWORD') ?: '');
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 465);
    $recipient = trim((string)(getenv('ADMIN_ALERT_EMAIL') ?: $smtpUsername));
    if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || !in_array($smtpPort, [465, 587], true) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $eventTitles = [
        'investment_deposit_submitted' => 'Investment deposit submitted',
        'wallet_topup_submitted' => 'Wallet top-up submitted',
        'withdrawal_submitted' => 'Withdrawal request submitted',
        'compliance_case_submitted' => 'Compliance case submitted',
    ];
    $eventType = (string)($alert['event_type'] ?? 'admin_alert');
    $payload = json_decode((string)($alert['payload'] ?? ''), true);
    if (!is_array($payload)) {
        return false;
    }
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://redmanfinance.onrender.com', '/');
    $adminPath = (string)($data['admin_path'] ?? '/admin/c2wad/');
    if ($adminPath === '' || $adminPath[0] !== '/') {
        $adminPath = '/admin/c2wad/';
    }
    $adminUrl = $baseUrl . $adminPath;

    $rows = '';
    $plainRows = [];
    foreach ($data as $key => $value) {
        if ($key === 'admin_path' || !is_scalar($value)) {
            continue;
        }
        $label = ucwords(str_replace('_', ' ', (string)$key));
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeValue = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows .= '<tr><th align="left" style="padding:8px;border-bottom:1px solid #e5e7eb">' . $safeLabel . '</th><td style="padding:8px;border-bottom:1px solid #e5e7eb">' . $safeValue . '</td></tr>';
        $plainRows[] = $label . ': ' . (string)$value;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->Port = $smtpPort;
        $mail->SMTPSecure = $smtpPort === 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername, getenv('MAIL_FROM_NAME') ?: 'Redman Finance');
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $title = $eventTitles[$eventType] ?? 'Finance activity requires review';
        $mail->Subject = '[Redman Finance] ' . $title;
        $mail->Body = '<!doctype html><html><body style="margin:0;background:#f2f4f5;color:#19242b;font-family:Arial,sans-serif"><div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #d7dfe2"><header style="padding:20px;background:#11212b;color:#fff"><div style="font-size:12px;color:#cbd5e1">REDMAN FINANCE | ADMIN ALERT</div><h1 style="margin:10px 0 0;font-size:22px">' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1></header><main style="padding:22px"><p>A new finance or compliance request needs review.</p><table style="width:100%;border-collapse:collapse">' . $rows . '</table><p style="margin:22px 0"><a href="' . htmlspecialchars($adminUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;background:#f4d35e;color:#101820;text-decoration:none;font-weight:bold">Open admin review</a></p></main><footer style="padding:14px;background:#edf1f2;color:#586970;font-size:12px">Automated operational alert. Do not reply to this email.</footer></div></body></html>';
        $mail->AltBody = "REDMAN FINANCE ADMIN ALERT\n{$title}\n\n" . implode("\n", $plainRows) . "\n\nReview: {$adminUrl}";
        $mail->send();
        return true;
    } catch (Throwable $error) {
        error_log('Admin alert SMTP fallback failed for event ' . $eventType);
        return false;
    }
}

function redman_admin_alert_dispatch(mysqli $link, int $id): bool
{
    $link->begin_transaction();
    try {
        $select = $link->prepare("SELECT id, payload, attempts FROM admin_alert_outbox WHERE id = ? AND status = 'queued' AND available_at <= NOW() FOR UPDATE");
        if (!$select) {
            throw new RuntimeException('Unable to claim admin alert.');
        }
        $select->bind_param('i', $id);
        if (!$select->execute()) {
            $select->close();
            throw new RuntimeException('Unable to claim admin alert.');
        }
        $row = $select->get_result()->fetch_assoc();
        $select->close();
        if (!$row) {
            $link->rollback();
            return false;
        }

        $attempts = (int)$row['attempts'] + 1;
        $claim = $link->prepare("UPDATE admin_alert_outbox SET status = 'sending', attempts = ? WHERE id = ? AND status = 'queued'");
        if (!$claim) {
            throw new RuntimeException('Unable to claim admin alert.');
        }
        $claim->bind_param('ii', $attempts, $id);
        $claimed = $claim->execute() && $claim->affected_rows === 1;
        $claim->close();
        if (!$claimed || !$link->commit()) {
            $link->rollback();
            return false;
        }
    } catch (Throwable $error) {
        $link->rollback();
        error_log('Admin alert claim failed for outbox id ' . $id);
        return false;
    }

    $webhookUrl = trim((string)(getenv('MAKE_FINANCE_ALERT_WEBHOOK_URL') ?: ''));
    $makeDelivered = false;
    if ($webhookUrl !== '' && filter_var($webhookUrl, FILTER_VALIDATE_URL) && strtolower((string)parse_url($webhookUrl, PHP_URL_SCHEME)) === 'https') {
        $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
        $webhookApiKey = trim((string)(getenv('MAKE_FINANCE_ALERT_WEBHOOK_API_KEY') ?: ''));
        if ($webhookApiKey !== '') {
            $headers .= 'x-make-apikey: ' . $webhookApiKey . "\r\n";
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => (string)$row['payload'],
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($webhookUrl, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        $makeResult = is_string($response) ? json_decode($response, true) : null;
        $makeDelivered = $response !== false
            && preg_match('/\s2\d\d\s/', $statusLine) === 1
            && is_array($makeResult)
            && ($makeResult['delivered'] ?? false) === true;
    }

    if ($makeDelivered) {
        $sent = $link->prepare("UPDATE admin_alert_outbox SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ? AND status = 'sending'");
        if (!$sent) {
            error_log('Admin alert sent but could not update outbox status for id ' . $id);
            return false;
        }
        $sent->bind_param('i', $id);
        $success = $sent->execute();
        $sent->close();
        return $success;
    }

    if (redman_admin_alert_send_email($row)) {
        $fallback = $link->prepare("UPDATE admin_alert_outbox SET status = 'sent', sent_at = NOW(), last_error = 'Make unavailable; delivered via SMTP fallback.' WHERE id = ? AND status = 'sending'");
        if (!$fallback) {
            error_log('Admin alert delivered via SMTP but fallback status could not be saved for id ' . $id);
            return false;
        }
        $fallback->bind_param('i', $id);
        $success = $fallback->execute();
        $fallback->close();
        return $success;
    }

    $nextStatus = $attempts >= 8 ? 'failed' : 'queued';
    $delayMinutes = min(360, 2 ** min($attempts, 8));
    $safeError = 'Make and SMTP delivery failed; inspect Make scenario history and application logs.';
    $retry = $link->prepare('UPDATE admin_alert_outbox SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? MINUTE), last_error = ? WHERE id = ? AND status = \'sending\'');
    if (!$retry) {
        error_log('Admin alert delivery failed and retry state could not be saved for id ' . $id);
        return false;
    }
    $retry->bind_param('sisi', $nextStatus, $delayMinutes, $safeError, $id);
    $retry->execute();
    $retry->close();
    error_log('Admin alert Make and SMTP delivery failed for outbox id ' . $id);
    return false;
}

function redman_admin_alert_dispatch_pending(mysqli $link, int $limit = 25): int
{
    $limit = max(1, min(100, $limit));
    $result = $link->query("SELECT id FROM admin_alert_outbox WHERE status = 'queued' AND available_at <= NOW() ORDER BY id LIMIT " . $limit);
    if (!$result) {
        return 0;
    }
    $sent = 0;
    while ($row = $result->fetch_assoc()) {
        if (redman_admin_alert_dispatch($link, (int)$row['id'])) {
            $sent++;
        }
    }
    return $sent;
}
