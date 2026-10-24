<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../risk.php';
require_once __DIR__ . '/../admin_notifications.php';

if (empty($_SESSION['email'])) {
    header('Location: ../trade/login.php');
    exit();
}

$email = (string)$_SESSION['email'];
$message = '';
$messageType = 'success';
$csrfToken = $_SESSION['compliance_csrf'] ??= bin2hex(random_bytes(32));

$loadUser = $link->prepare('SELECT id, fname, lname, email, date, walletbalance, vault_balance, is_on_hold, hold_reason, hold_release_date, kyc_status, id_front, id_back, compliance_case_reference, compliance_id_front_path, compliance_id_back_path, compliance_address_path, compliance_submitted_at FROM users WHERE email = ? LIMIT 1');
if (!$loadUser) {
    http_response_code(503);
    exit('Compliance review is temporarily unavailable while its database update is applied. No changes were made to your account.');
}
$loadUser->bind_param('s', $email);
$loaded = $loadUser->execute();
if (!$loaded) {
    $loadUser->close();
    http_response_code(503);
    exit('Compliance review is temporarily unavailable while its database update is applied. No changes were made to your account.');
}
$user = $loadUser->get_result()->fetch_assoc();
$loadUser->close();
if (!$user) {
    header('Location: ../trade/login.php');
    exit();
}

$userId = (int)$user['id'];
if (empty($user['compliance_case_reference'])) {
    $candidateReference = 'RFM-' . strtoupper(bin2hex(random_bytes(6)));
    $saveCase = $link->prepare('UPDATE users SET compliance_case_reference = ? WHERE id = ? AND compliance_case_reference IS NULL');
    if ($saveCase) {
        $saveCase->bind_param('si', $candidateReference, $userId);
        $saveCase->execute();
        $saveCase->close();
    }
    $reloadCase = $link->prepare('SELECT compliance_case_reference FROM users WHERE id = ? LIMIT 1');
    if ($reloadCase) {
        $reloadCase->bind_param('i', $userId);
        if ($reloadCase->execute()) {
            $caseRow = $reloadCase->get_result()->fetch_assoc();
            $user['compliance_case_reference'] = $caseRow['compliance_case_reference'] ?? $candidateReference;
        }
        $reloadCase->close();
    }
    $caseEvent = $link->prepare("INSERT INTO compliance_case_events (user_id, event_type, details) VALUES (?, 'case_opened', ?)");
    if ($caseEvent) {
        $details = json_encode(['case_reference' => $user['compliance_case_reference']], JSON_UNESCAPED_SLASHES);
        $caseEvent->bind_param('is', $userId, $details);
        $caseEvent->execute();
        $caseEvent->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $message = 'Your session expired. Reload this page and try again.';
        $messageType = 'danger';
    } elseif (($_POST['action'] ?? '') === 'upload_document') {
        $documentType = (string)($_POST['document_type'] ?? '');
        $documentColumns = [
            'id_front' => 'compliance_id_front_path',
            'id_back' => 'compliance_id_back_path',
            'proof_of_address' => 'compliance_address_path',
        ];
        if (!isset($documentColumns[$documentType]) || !isset($_FILES['document'])) {
            $message = 'Choose a valid document and upload it again.';
            $messageType = 'danger';
        } else {
            $file = $_FILES['document'];
            $imageInfo = !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name']) ? @getimagesize($file['tmp_name']) : false;
            $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] < 1 || $file['size'] > 5 * 1024 * 1024 || !$imageInfo || !isset($allowedTypes[$imageInfo[2]])) {
                $message = 'Upload a clear JPG or PNG image up to 5 MB.';
                $messageType = 'danger';
            } else {
                $relativeDirectory = 'compliance' . DIRECTORY_SEPARATOR . $userId;
                $absoluteDirectory = redman_compliance_storage_root() . DIRECTORY_SEPARATOR . $relativeDirectory;
                if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0700, true) && !is_dir($absoluteDirectory)) {
                    $message = 'Secure document storage is unavailable. Please try again later.';
                    $messageType = 'danger';
                } else {
                    $filename = $documentType . '-' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$imageInfo[2]];
                    $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;
                    $relativePath = $relativeDirectory . DIRECTORY_SEPARATOR . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                        $message = 'The document could not be saved. Please try again.';
                        $messageType = 'danger';
                    } else {
                        $column = $documentColumns[$documentType];
                        $saveDocument = $link->prepare("UPDATE users SET $column = ?, compliance_submitted_at = NULL WHERE id = ?");
                        $saved = $saveDocument && $saveDocument->bind_param('si', $relativePath, $userId) && $saveDocument->execute();
                        if ($saveDocument) {
                            $saveDocument->close();
                        }
                        if (!$saved) {
                            @unlink($absolutePath);
                            $message = 'The document could not be recorded. Please try again.';
                            $messageType = 'danger';
                        } else {
                            $event = $link->prepare('INSERT INTO compliance_case_events (user_id, event_type, details) VALUES (?, ?, ?)');
                            if ($event) {
                                $eventType = 'document_' . $documentType . '_uploaded';
                                $details = json_encode(['filename' => $filename], JSON_UNESCAPED_SLASHES);
                                $event->bind_param('iss', $userId, $eventType, $details);
                                $event->execute();
                                $event->close();
                            }
                            $message = 'Document saved to your case. Your progress will be here when you return.';
                        }
                    }
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'submit_case') {
        $reloadUser = $link->prepare('SELECT compliance_id_front_path, compliance_id_back_path, compliance_address_path, compliance_case_reference FROM users WHERE id = ? LIMIT 1');
        $reloadUser->bind_param('i', $userId);
        $reloadUser->execute();
        $documents = $reloadUser->get_result()->fetch_assoc() ?: [];
        $reloadUser->close();
        $legacyFrontPresent = !empty($user['id_front']);
        $legacyBackPresent = !empty($user['id_back']);
        if ((empty($documents['compliance_id_front_path']) && !$legacyFrontPresent) || (empty($documents['compliance_id_back_path']) && !$legacyBackPresent) || empty($documents['compliance_address_path'])) {
            $message = 'Upload all three requested documents before submitting your case.';
            $messageType = 'warning';
        } else {
            $submitCase = $link->prepare("UPDATE users SET kyc_status = 'pending', kyc_reviewed_at = NULL, kyc_reviewed_by = NULL, compliance_submitted_at = NOW() WHERE id = ?");
            if (!$submitCase || !$submitCase->bind_param('i', $userId) || !$submitCase->execute()) {
                $message = 'Your case could not be submitted. Please try again.';
                $messageType = 'danger';
            } else {
                $submitCase->close();
                $event = $link->prepare("INSERT INTO compliance_case_events (user_id, event_type, details) VALUES (?, 'case_submitted', ?)");
                if ($event) {
                    $details = json_encode(['case_reference' => $documents['compliance_case_reference']], JSON_UNESCAPED_SLASHES);
                    $event->bind_param('is', $userId, $details);
                    $event->execute();
                    $event->close();
                }
                try {
                    $alertId = redman_admin_alert_enqueue($link, 'compliance-case-submitted:' . $userId . ':' . (string)$documents['compliance_case_reference'], 'compliance_case_submitted', [
                        'customer_email' => $email,
                        'customer_name' => trim((string)$user['fname'] . ' ' . (string)$user['lname']),
                        'case_reference' => (string)$documents['compliance_case_reference'],
                        'submitted_at' => date('c'),
                        'admin_path' => '/admin/c2wad/risk_management.php',
                    ]);
                    redman_admin_alert_dispatch($link, $alertId);
                } catch (Throwable $alertError) {
                    error_log('Unable to queue admin compliance alert for case ' . (string)$documents['compliance_case_reference']);
                }
                $message = 'Your review case has been submitted. We will update this page when it has been reviewed.';
            }
        }
    }
}

$refresh = $link->prepare('SELECT walletbalance, vault_balance, is_on_hold, hold_reason, hold_release_date, kyc_status, id_front, id_back, compliance_case_reference, compliance_id_front_path, compliance_id_back_path, compliance_address_path, compliance_submitted_at FROM users WHERE id = ? LIMIT 1');
$refresh->bind_param('i', $userId);
$refresh->execute();
$user = array_merge($user, $refresh->get_result()->fetch_assoc() ?: []);
$refresh->close();

$limits = redman_risk_limits(redman_risk_settings($link), redman_risk_user_overrides($link, $userId));
$volume = redman_risk_transaction_volume($link, $email);
$volume = $volume === null ? 0.0 : $volume;
$requiredVolume = (float)$limits['required_transaction_volume'];
$volumeProgress = redman_risk_volume_progress($volume, $requiredVolume);
$volumeRemaining = $volumeProgress['remaining'];
$releaseChecks = redman_risk_release_checks($user, $volume, $limits);
$documents = [
    'id_front' => ['label' => 'Government ID, front', 'path' => $user['compliance_id_front_path'] ?: (!empty($user['id_front']) ? 'legacy' : '')],
    'id_back' => ['label' => 'Government ID, back', 'path' => $user['compliance_id_back_path'] ?: (!empty($user['id_back']) ? 'legacy' : '')],
    'proof_of_address' => ['label' => 'Proof of address', 'path' => $user['compliance_address_path'] ?? ''],
];
$allDocumentsPresent = true;
foreach ($documents as $document) {
    if (empty($document['path'])) {
        $allDocumentsPresent = false;
    }
}
$isHeld = (int)$user['is_on_hold'] === 1;
$caseSubmitted = !empty($user['compliance_submitted_at']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compliance Review | Redman Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background:#0D1627; color:#f4f5f7; font-family:Arial,sans-serif; }
        .panel { background:#172338; border:1px solid #334155; }
        .muted { color:#b5c0cf; }
    </style>
</head>
<body class="min-h-screen">
<main class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <nav class="mb-6 flex items-center justify-between">
        <a class="text-sm text-slate-300 underline" href="index.php">Back to dashboard</a>
        <a class="text-sm text-slate-300 underline" href="profile.php">Profile</a>
    </nav>
    <h1 class="text-2xl font-semibold">Compliance review</h1>
    <p class="mt-2 muted">Case reference: <strong><?php echo htmlspecialchars((string)$user['compliance_case_reference'], ENT_QUOTES, 'UTF-8'); ?></strong></p>

    <?php if ($message !== ''): ?>
        <div class="mt-5 border-l-4 border-yellow-400 bg-slate-800 p-4" role="status">
            <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <section class="panel mt-6 p-5">
        <h2 class="text-lg font-semibold"><?php echo $isHeld ? 'Your account is under review' : 'Your verification progress'; ?></h2>
        <?php if ($isHeld): ?>
            <p class="mt-3 muted">We temporarily paused withdrawals and investment earnings while our team reviews your account. Your held vault balance is <strong>$<?php echo number_format((float)$user['vault_balance'], 2); ?></strong>; your available main balance is <strong>$<?php echo number_format((float)$user['walletbalance'], 2); ?></strong>.</p>
            <p class="mt-3 muted">No additional deposit or payment is required to submit this review or release funds already in the vault. Complete the document checklist below. Our Compliance Review Team will review your case and post the decision here.</p>
        <?php else: ?>
            <p class="mt-3 muted">Complete and submit your verification documents here. Your progress is saved to this case, so you can leave and continue later.</p>
        <?php endif; ?>
        <?php if ($caseSubmitted): ?>
            <p class="mt-4 rounded bg-slate-900 p-3">Submitted for review on <?php echo htmlspecialchars((string)$user['compliance_submitted_at'], ENT_QUOTES, 'UTF-8'); ?>. Current KYC status: <strong><?php echo htmlspecialchars(ucfirst((string)$user['kyc_status']), ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
        <?php else: ?>
            <p class="mt-4 muted">Case status: <strong><?php echo htmlspecialchars(ucfirst((string)$user['kyc_status']), ENT_QUOTES, 'UTF-8'); ?></strong>. Documents are saved as you upload them.</p>
        <?php endif; ?>
        <?php if (!empty($user['hold_release_date'])): ?>
            <p class="mt-3 muted">The required checks passed on <?php echo htmlspecialchars((string)$user['hold_requirements_met_at'], ENT_QUOTES, 'UTF-8'); ?>, starting the cooldown. The cooldown ends <?php echo htmlspecialchars((string)$user['hold_release_date'], ENT_QUOTES, 'UTF-8'); ?>. After that, staff will review and complete the release; funds are not moved automatically.</p>
        <?php endif; ?>
    </section>

    <section class="panel mt-5 p-5">
        <h2 class="text-lg font-semibold">Verification checklist</h2>
        <p class="mt-2 muted">Upload JPG or PNG images up to 5 MB. Each completed item stays attached to your case.</p>
        <div class="mt-4 space-y-4">
            <?php foreach ($documents as $type => $document): ?>
                <?php $complete = !empty($document['path']); ?>
                <article class="border-b border-slate-600 pb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-medium"><?php echo htmlspecialchars($document['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="text-sm <?php echo $complete ? 'text-green-300' : 'text-yellow-200'; ?>"><?php echo $complete ? 'Saved' : 'Needed'; ?></span>
                    </div>
                    <?php if (!empty($document['path']) && $document['path'] !== 'legacy'): ?>
                        <p class="mt-1 text-sm muted">Latest file saved to your case.</p>
                    <?php elseif ($document['path'] === 'legacy'): ?>
                        <p class="mt-1 text-sm muted">An existing ID image is on file. Upload here only if you need to replace it.</p>
                    <?php endif; ?>
                    <form class="mt-3 flex flex-wrap items-center gap-3" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="upload_document">
                        <input type="hidden" name="document_type" value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                        <input class="max-w-full text-sm" type="file" name="document" accept="image/jpeg,image/png" required>
                        <button class="rounded bg-slate-600 px-3 py-2 text-sm font-medium hover:bg-slate-500" type="submit"><?php echo $complete ? 'Replace document' : 'Upload document'; ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <form class="mt-5" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="submit_case">
            <button class="rounded bg-yellow-300 px-4 py-2 font-semibold text-slate-900 disabled:cursor-not-allowed disabled:opacity-50" type="submit" <?php echo $allDocumentsPresent ? '' : 'disabled'; ?>>Submit documents for review</button>
            <?php if (!$allDocumentsPresent): ?><span class="ml-2 text-sm muted">Upload all three items to submit.</span><?php endif; ?>
        </form>
    </section>

    <section class="panel mt-5 p-5">
        <h2 class="text-lg font-semibold">Completed transaction history</h2>
        <p class="mt-2 muted">Configured completed-history requirement: <strong>$<?php echo number_format($requiredVolume, 2); ?></strong>. Qualifying completed history: <strong>$<?php echo number_format($volume, 2); ?></strong>.</p>
        <ul class="mt-3 space-y-2 text-sm muted" aria-label="Account review checks">
            <li><?php echo $releaseChecks['documents_complete'] ? '&#10003; Required documents submitted' : '&#8226; Required documents are not all submitted'; ?></li>
            <li><?php echo $releaseChecks['kyc_verified'] ? '&#10003; Identity verification approved' : '&#8226; Identity verification is awaiting Compliance Review Team decision'; ?></li>
            <li><?php echo $releaseChecks['history_met'] ? '&#10003; Completed-history requirement met' : '&#8226; Completed-history requirement is not met'; ?></li>
            <?php if (!empty($user['hold_release_date'])): ?>
                <li><?php echo $releaseChecks['cooldown_elapsed'] ? '&#10003; Cooldown elapsed; staff release decision remains' : '&#8226; Review cooldown ends ' . htmlspecialchars((string)$user['hold_release_date'], ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
        </ul>
        <p class="mt-3 text-sm muted">The completed-history amount is used as an eligibility check; any shortfall is information, not money due. The review and cooldown do not request a new payment. Staff make the release decision after reviewing the case.</p>
        <a class="mt-3 inline-block rounded border border-slate-500 px-3 py-2 text-sm text-slate-200 hover:bg-slate-700" href="wallet.php">Optional: Add Funds</a>
    </section>

    <section class="mt-5 border-t border-slate-700 pt-4 text-sm muted">
        <p>Review times may vary. Include case reference <?php echo htmlspecialchars((string)$user['compliance_case_reference'], ENT_QUOTES, 'UTF-8'); ?> when contacting support.</p>
        <p class="mt-2"><a class="underline" href="../index.html#contact">Contact support</a></p>
    </section>
</main>
</body>
</html>
