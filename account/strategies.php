<?php
session_start();
require_once '../db.php';

if (empty($_SESSION['email'])) {
    header('Location: ../trade/login.php');
    exit();
}

$strategies = $link->query("SELECT strategy_name, description, methodology, assets_covered, risk_level, minimum_amount, maximum_amount, term_days, fee_description, evidence_description, verification_notes, verified_at FROM investment_strategies WHERE verification_status = 'verified' AND is_active = 1 ORDER BY verified_at DESC");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Strategies | Redman Finance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#101a22;color:#f4f4ef}.panel{background:#1b2a31;border:1px solid #35464a}</style>
</head>
<body class="min-h-screen">
<main class="mx-auto max-w-5xl px-4 py-8">
    <a class="text-sm text-emerald-300" href="./">Dashboard</a>
    <h1 class="mt-2 text-2xl font-semibold">AI Strategy Evidence Registry</h1>
    <p class="my-5 border-l-4 border-amber-400 bg-amber-950/50 p-4 text-sm">Evidence review is not proof of future performance. Strategies listed here are informational only and are not available for investment through this site. No returns are promised.</p>
    <?php if (!$strategies || $strategies->num_rows === 0): ?><p class="text-gray-400">No strategies have completed evidence review.</p><?php endif; ?>
    <div class="space-y-4">
    <?php while ($strategy = $strategies->fetch_assoc()): ?>
        <article class="panel p-5">
            <div class="flex flex-wrap items-start justify-between gap-3"><h2 class="text-xl font-medium"><?php echo htmlspecialchars($strategy['strategy_name'], ENT_QUOTES, 'UTF-8'); ?></h2><span class="border border-emerald-600 px-2 py-1 text-xs text-emerald-200">Evidence reviewed</span></div>
            <p class="mt-3"><?php echo nl2br(htmlspecialchars($strategy['description'], ENT_QUOTES, 'UTF-8')); ?></p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-gray-400">Methodology</dt><dd><?php echo nl2br(htmlspecialchars($strategy['methodology'], ENT_QUOTES, 'UTF-8')); ?></dd></div><div><dt class="text-gray-400">Assets covered</dt><dd><?php echo htmlspecialchars($strategy['assets_covered'], ENT_QUOTES, 'UTF-8'); ?></dd></div><div><dt class="text-gray-400">Risk category</dt><dd><?php echo htmlspecialchars($strategy['risk_level'], ENT_QUOTES, 'UTF-8'); ?></dd></div><div><dt class="text-gray-400">Term</dt><dd><?php echo (int)$strategy['term_days']; ?> days</dd></div><div><dt class="text-gray-400">Indicative amount range</dt><dd>$<?php echo number_format((float)$strategy['minimum_amount'], 2); ?> to $<?php echo number_format((float)$strategy['maximum_amount'], 2); ?></dd></div><div><dt class="text-gray-400">Fees</dt><dd><?php echo htmlspecialchars((string)$strategy['fee_description'], ENT_QUOTES, 'UTF-8'); ?></dd></div><div class="sm:col-span-2"><dt class="text-gray-400">Evidence summary</dt><dd><?php echo nl2br(htmlspecialchars($strategy['evidence_description'], ENT_QUOTES, 'UTF-8')); ?></dd></div><div class="sm:col-span-2"><dt class="text-gray-400">Review notes</dt><dd><?php echo nl2br(htmlspecialchars((string)$strategy['verification_notes'], ENT_QUOTES, 'UTF-8')); ?></dd></div></dl>
            <p class="mt-4 text-xs text-gray-400">Reviewed <?php echo htmlspecialchars((string)$strategy['verified_at'], ENT_QUOTES, 'UTF-8'); ?>. Informational only; not investable.</p>
        </article>
    <?php endwhile; ?>
    </div>
</main>
</body>
</html>