<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana</title>
    <link rel="stylesheet" href="css/ana.css">
</head>
<body class="ana-index-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<?php
$stats = [];
try {
    $res = $mysqli->query("
        SELECT
            (SELECT COUNT(*) FROM classes WHERE is_interface = 0 AND is_trait = 0) AS classes,
            (SELECT COUNT(*) FROM classes WHERE is_interface = 1) AS interfaces,
            (SELECT COUNT(*) FROM classes WHERE is_trait = 1) AS traits,
            (SELECT COUNT(*) FROM methods WHERE class_name != '') AS methods,
            (SELECT COUNT(*) FROM methods WHERE class_name = '' AND is_builtin = 0) AS functions,
            (SELECT COUNT(*) FROM calls) AS calls,
            (SELECT ROUND(AVG(cyclo_complexity), 2) FROM methods WHERE is_builtin = 0) AS avg_cyclo
    ");
    $stats = $res->fetch_assoc();
} catch (mysqli_sql_exception $e) {
    $statsError = $e->getMessage();
}
$statLabels = [
    'classes' => 'Classes',
    'interfaces' => 'Interfaces',
    'traits' => 'Traits',
    'methods' => 'Methods',
    'functions' => 'Functions',
    'calls' => 'Calls',
    'avg_cyclo' => 'Avg cyclomatic complexity',
];
?>
<div class="ana-index">
    <h1><?= htmlspecialchars($anaProjectName !== '' ? $anaProjectName : 'Ana') ?></h1>
    <p class="ana-subtitle"><?= $anaAnalyzedAt !== '' ? 'Analyzed ' . htmlspecialchars($anaAnalyzedAt) : 'Static analysis overview' ?></p>

<?php if (isset($statsError)): ?>
    <p class="ana-error">Could not read the analysis data: <?= htmlspecialchars($statsError) ?></p>
<?php else: ?>
    <div class="ana-stats">
<?php foreach ($statLabels as $key => $label): ?>
        <div class="ana-stat">
            <div class="value"><?= htmlspecialchars((string) ($stats[$key] ?? 0)) ?></div>
            <div class="label"><?= $label ?></div>
        </div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <div class="ana-cards">
<?php foreach ($anaPages as $file => [$label, $description]): ?>
<?php if ($file === 'index.php') continue; ?>
        <a class="ana-card" href="<?= $file ?>">
            <h2><?= htmlspecialchars($label) ?></h2>
            <p><?= htmlspecialchars($description) ?></p>
        </a>
<?php endforeach; ?>
    </div>
</div>
</body>
</html>
