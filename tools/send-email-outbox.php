<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../email_notifications.php';

$batchSize = isset($argv[1]) ? max(1, min(100, (int)$argv[1])) : 25;
$result = $link->query("SELECT id FROM email_outbox WHERE status = 'queued' AND available_at <= NOW() ORDER BY id LIMIT " . $batchSize);
if (!$result) {
    fwrite(STDERR, "Unable to load queued email notifications.\n");
    exit(1);
}

$sent = 0;
$attempted = 0;
while ($row = $result->fetch_assoc()) {
    $attempted++;
    if (redman_email_dispatch($link, (int)$row['id'])) {
        $sent++;
    }
}

fwrite(STDOUT, "Outbox batch complete. Sent {$sent} of {$attempted} attempted notifications.\n");