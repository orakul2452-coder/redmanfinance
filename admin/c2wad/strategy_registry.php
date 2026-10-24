<?php
session_start();
require_once '../../conn.php';

if (empty($_SESSION['uid'])) {
    header('Location: ../c2wadmin/signin.php');
    exit();
}

$_SESSION['strategy_registry_csrf'] = $_SESSION['strategy_registry_csrf'] ?? bin2hex(random_bytes(32));
$message = '';
$error = '';
$adminId = (int)$_SESSION['uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['strategy_registry_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Request expired. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $name = trim((string)($_POST['strategy_name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $methodology = trim((string)($_POST['methodology'] ?? ''));
        $assets = trim((string)($_POST['assets_covered'] ?? ''));
        $risk = trim((string)($_POST['risk_level'] ?? ''));
        $minimum = filter_var($_POST['minimum_amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $maximum = filter_var($_POST['maximum_amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $term = filter_var($_POST['term_days'] ?? null, FILTER_VALIDATE_INT);
        $fees = trim((string)($_POST['fee_description'] ?? ''));
        $evidence = trim((string)($_POST['evidence_description'] ?? ''));
        $evidenceUrl = trim((string)($_POST['evidence_url'] ?? ''));
        if ($name === '' || $description === '' || $methodology === '' || $assets === '' || $risk === '' || $minimum === false || $maximum === false || $minimum < 0 || $maximum < $minimum || $term === false || $term < 0 || $evidence === '') {
            $error = 'Complete the required fields with valid amount and term values.';
        } elseif ($evidenceUrl !== '' && (!filter_var($evidenceUrl, FILTER_VALIDATE_URL) || !preg_match('/^https:\/\//i', $evidenceUrl))) {
            $error = 'Evidence links must use HTTPS.';
        } else {
            $insert = $link->prepare("INSERT INTO investment_strategies (strategy_name, strategy_type, description, methodology, assets_covered, risk_level, minimum_amount, maximum_amount, term_days, fee_description, evidence_description, evidence_url) VALUES (?, 'ai', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param('sssssddisss', $name, $description, $methodology, $assets, $risk, $minimum, $maximum, $term, $fees, $evidence, $evidenceUrl);
            if ($insert->execute()) {
                $message = 'Strategy submitted for evidence review. It is not available for investment.';
            } else {
                $error = 'Unable to save this strategy.';
            }
            $insert->close();
        }
    } elseif (($_POST['action'] ?? '') === 'review') {
        $strategyId = filter_var($_POST['strategy_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string)($_POST['decision'] ?? '');
        $evidenceChecked = trim((string)($_POST['evidence_checked'] ?? ''));
        $reviewNotes = trim((string)($_POST['review_notes'] ?? ''));
        if ($strategyId === false || $strategyId < 1 || !in_array($decision, ['verified', 'rejected'], true) || strlen($evidenceChecked) < 20) {
            $error = 'Select a decision and describe the evidence checked in at least 20 characters.';
        } else {
            try {
                $link->begin_transaction();
                $update = $link->prepare("UPDATE investment_strategies SET verification_status = ?, verification_notes = ?, verified_by_admin_id = ?, verified_at = NOW(), is_active = ?, is_investable = 0 WHERE id = ? AND verification_status = 'pending'");
                $active = $decision === 'verified' ? 1 : 0;
                $update->bind_param('ssiii', $decision, $reviewNotes, $adminId, $active, $strategyId);
                if (!$update->execute() || $update->affected_rows !== 1) {
                    $update->close();
                    throw new RuntimeException('Unable to update strategy review.');
                }
                $update->close();
                $audit = $link->prepare('INSERT INTO investment_strategy_reviews (strategy_id, admin_id, decision, evidence_checked, review_notes) VALUES (?, ?, ?, ?, ?)');
                $audit->bind_param('iisss', $strategyId, $adminId, $decision, $evidenceChecked, $reviewNotes);
                if (!$audit->execute()) {
                    throw new RuntimeException('Unable to write the review audit.');
                }
                $audit->close();
                $link->commit();
                $message = $decision === 'verified' ? 'Evidence review recorded. Strategy remains non-investable.' : 'Strategy rejected; review recorded.';
            } catch (Throwable $exception) {
                $link->rollback();
                $error = 'Unable to save strategy review.';
            }
        }
    }
}

$strategies = $link->query('SELECT * FROM investment_strategies ORDER BY created_at DESC LIMIT 100');
include 'header.php';
?>
<div class="content-wrapper"><section class="content-header"><h1>AI Strategy Evidence Registry</h1></section><section class="content">
<?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="alert alert-warning">A verified status records an evidence review only. This registry does not connect to trading execution, guarantee returns, or enable investment.</div>
<div class="box"><div class="box-header"><h3 class="box-title">Register strategy</h3></div><div class="box-body">
<form method="post" class="row"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['strategy_registry_csrf'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="create">
<?php
$fields = [
    ['strategy_name', 'Strategy name', 'text', true],
    ['assets_covered', 'Assets covered', 'text', true],
    ['risk_level', 'Risk level', 'text', true],
    ['minimum_amount', 'Minimum amount', 'number', true],
    ['maximum_amount', 'Maximum amount', 'number', true],
    ['term_days', 'Term in days', 'number', true],
    ['fee_description', 'Fees', 'text', false],
    ['evidence_url', 'Evidence URL (HTTPS)', 'url', false],
];
foreach ($fields as [$field, $label, $type, $required]):
?>
<div class="form-group col-md-6"><label><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" type="<?php echo $type; ?>" name="<?php echo $field; ?>" <?php echo $type === 'number' ? 'min="0" step="0.01"' : ''; ?> <?php echo $required ? 'required' : ''; ?>></div>
<?php endforeach; ?>
<div class="form-group col-md-12"><label>Description</label><textarea class="form-control" name="description" required></textarea></div>
<div class="form-group col-md-12"><label>Methodology</label><textarea class="form-control" name="methodology" required></textarea></div>
<div class="form-group col-md-12"><label>Evidence description</label><textarea class="form-control" name="evidence_description" required></textarea></div>
<div class="col-md-12"><button class="btn btn-primary" type="submit">Submit for review</button></div></form></div></div>
<div class="box"><div class="box-header"><h3 class="box-title">Registered strategies</h3></div><div class="box-body table-responsive"><table class="table table-bordered"><thead><tr><th>Name and methodology</th><th>Scope</th><th>Evidence</th><th>Status</th><th>Review</th></tr></thead><tbody>
<?php while ($strategy = $strategies->fetch_assoc()): ?>
<tr><td><strong><?php echo htmlspecialchars($strategy['strategy_name'], ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo nl2br(htmlspecialchars($strategy['description'], ENT_QUOTES, 'UTF-8')); ?></p><p><?php echo nl2br(htmlspecialchars($strategy['methodology'], ENT_QUOTES, 'UTF-8')); ?></p></td><td><?php echo htmlspecialchars($strategy['assets_covered'], ENT_QUOTES, 'UTF-8'); ?><br>Risk: <?php echo htmlspecialchars($strategy['risk_level'], ENT_QUOTES, 'UTF-8'); ?><br>Range: $<?php echo number_format((float)$strategy['minimum_amount'], 2); ?> - $<?php echo number_format((float)$strategy['maximum_amount'], 2); ?><br>Term: <?php echo (int)$strategy['term_days']; ?> days<br>Fees: <?php echo htmlspecialchars((string)$strategy['fee_description'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo nl2br(htmlspecialchars($strategy['evidence_description'], ENT_QUOTES, 'UTF-8')); ?><?php if (!empty($strategy['evidence_url'])): ?><br><a href="<?php echo htmlspecialchars($strategy['evidence_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open evidence</a><?php endif; ?></td><td><?php echo htmlspecialchars($strategy['verification_status'], ENT_QUOTES, 'UTF-8'); ?><br>Not investable</td><td><?php if ($strategy['verification_status'] === 'pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['strategy_registry_csrf'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="review"><input type="hidden" name="strategy_id" value="<?php echo (int)$strategy['id']; ?>"><div class="form-group"><select class="form-control" name="decision"><option value="verified">Evidence reviewed</option><option value="rejected">Reject</option></select></div><div class="form-group"><textarea class="form-control" name="evidence_checked" placeholder="Evidence checked and review basis (required)" required></textarea></div><div class="form-group"><textarea class="form-control" name="review_notes" placeholder="Review notes"></textarea></div><button type="submit" class="btn btn-primary">Record review</button></form><?php else: ?><?php echo nl2br(htmlspecialchars((string)$strategy['verification_notes'], ENT_QUOTES, 'UTF-8')); ?><?php endif; ?></td></tr>
<?php endwhile; ?>
</tbody></table></div></div>
</section></div>