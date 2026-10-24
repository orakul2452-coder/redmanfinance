<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin_notifications.php';

$dryRun = in_array('--dry-run', $argv, true);
$batchSize = isset($argv[1]) ? max(1, min(100, (int)$argv[1])) : 25;
$batchSize = $dryRun ? max(1, min(100, (int)($argv[2] ?? 25))) : $batchSize;
if ($dryRun) {
    fwrite(STDOUT, "Dry run only. No Make webhook or SMTP email will be sent.\n");
    $result = $link->query("SELECT id, event_type, payload, attempts FROM admin_alert_outbox WHERE status = 'queued' AND available_at <= NOW() ORDER BY id LIMIT " . $batchSize);
    if (!$result) {
        fwrite(STDERR, "Unable to inspect queued admin alerts.\n");
        exit(1);
    }
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $decoded = json_decode((string)$row['payload'], true);
        $customerEmail = is_array($decoded) ? (string)($decoded['data']['customer_email'] ?? '') : '';
        fwrite(STDOUT, 'Queued alert #' . (int)$row['id'] . ' ' . $row['event_type'] . ' ref=' . substr((string)($decoded['data']['reference'] ?? $decoded['data']['case_reference'] ?? ''), 0, 80) . ' customer=' . $customerEmail . ' attempts=' . (int)$row['attempts'] . PHP_EOL);
        $count++;
    }
    fwrite(STDOUT, "Dry run complete. {$count} queued alerts inspected.\n");
    exit(0);
}

$sent = redman_admin_alert_dispatch_pending($link, $batchSize);
fwrite(STDOUT, "Admin alert batch complete. Delivered {$sent} alerts.\n");
