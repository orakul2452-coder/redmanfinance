<?php
session_start();
require_once '../../conn.php';
require_once '../../risk.php';

if (!isset($_SESSION['uid'])) {
    http_response_code(403);
    exit('Forbidden');
}

$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$kind = (string)($_GET['kind'] ?? '');
$columns = [
    'front' => 'compliance_id_front_path',
    'back' => 'compliance_id_back_path',
    'address' => 'compliance_address_path',
    'legacy_front' => 'id_front',
    'legacy_back' => 'id_back',
];
if (!$userId || !isset($columns[$kind])) {
    http_response_code(404);
    exit('Not found');
}

$column = $columns[$kind];
$statement = $link->prepare("SELECT $column AS document_path FROM users WHERE id = ? LIMIT 1");
if (!$statement) {
    http_response_code(500);
    exit('Unable to load document');
}
$statement->bind_param('i', $userId);
$statement->execute();
$record = $statement->get_result()->fetch_assoc();
$statement->close();
$relativePath = (string)($record['document_path'] ?? '');
if ($relativePath === '') {
    http_response_code(404);
    exit('Not found');
}

$projectRoot = dirname(__DIR__, 2);
$legacyDocument = strpos($kind, 'legacy_') === 0;
if ($legacyDocument) {
    $legacyName = basename(str_replace('\\', '/', $relativePath));
    $legacyRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'account' . DIRECTORY_SEPARATOR . 'uploads');
    $filePath = $legacyRoot ? realpath($legacyRoot . DIRECTORY_SEPARATOR . $legacyName) : false;
    if (!$legacyRoot || !$filePath || strpos($filePath, $legacyRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
        http_response_code(404);
        exit('Not found');
    }
} else {
    if (strpos($relativePath, 'compliance' . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(404);
        exit('Not found');
    }
    $storageRoot = realpath(redman_compliance_storage_root() . DIRECTORY_SEPARATOR . 'compliance');
    $filePath = realpath(redman_compliance_storage_root() . DIRECTORY_SEPARATOR . $relativePath);
    if (!$storageRoot || !$filePath || strpos($filePath, $storageRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
        http_response_code(404);
        exit('Not found');
    }
}

$image = @getimagesize($filePath);
$mimeType = $image['mime'] ?? '';
if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Unsupported document');
}
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . (!empty($_GET['download']) ? 'attachment' : 'inline') . '; filename="compliance-' . preg_replace('/[^a-z0-9_-]/i', '-', $kind) . '.' . ($mimeType === 'image/png' ? 'png' : 'jpg') . '"');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
