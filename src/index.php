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
            (SELECT ROUND(AVG(cyclo_complexity), 2) FROM methods WHERE is_builtin = 0) AS avg_cyclo,
            (SELECT ROUND(AVG(cognitive_complexity), 2) FROM methods WHERE is_builtin = 0) AS avg_cognitive
    ");
    $stats = $res->fetch_assoc();

    // Namespaces are the classes' namespaces, the same rows as the Namespace Metrics page
    $namespaces = [];
    foreach ($mysqli->query("SELECT class_name FROM classes")->fetch_all(MYSQLI_NUM) as [$className]) {
        $parts = explode('\\', $className);
        array_pop($parts);
        $namespaces[implode('\\', $parts) ?: '[Global]'] = true;
    }
    $stats['namespaces'] = count($namespaces);

    // The most complex method by each measure, for the complexity cards
    foreach (['cyclo_complexity' => 'cyclo', 'cognitive_complexity' => 'cognitive'] as $column => $key) {
        $max = $mysqli->query("
            SELECT class_name, method_name, $column AS value FROM methods
            WHERE is_builtin = 0 ORDER BY $column DESC, class_name, method_name LIMIT 1
        ")->fetch_assoc();
        if ($max === null) continue;
        $maxMethods[$key] = [
            'value' => $max['value'],
            'name' => $max['class_name'] === '' ? $max['method_name'] : $max['class_name'] . '::' . $max['method_name'],
        ];
    }
} catch (mysqli_sql_exception $e) {
    $statsError = $e->getMessage();
}
// The summary boxes: key in $stats => [label, key of the most complex method shown in the box, or null]
$statItems = [
    'namespaces' => ['Namespaces', null], 'classes' => ['Classes', null], 'interfaces' => ['Interfaces', null], 'traits' => ['Traits', null],
    'methods' => ['Methods', null], 'functions' => ['Functions', null], 'calls' => ['Calls', null],
    'avg_cyclo' => ['Avg cyclomatic complexity', 'cyclo'], 'avg_cognitive' => ['Avg cognitive complexity', 'cognitive'],
];
?>
<div class="ana-index">
    <h1><?= htmlspecialchars($anaProjectName !== '' ? $anaProjectName : 'Ana') ?></h1>
    <p class="ana-subtitle"><?= $anaAnalyzedAt !== '' ? 'Analyzed ' . htmlspecialchars($anaAnalyzedAt) : 'Static analysis overview' ?></p>

<?php if (isset($statsError)): ?>
    <p class="ana-error">Could not read the analysis data: <?= htmlspecialchars($statsError) ?></p>
<?php else: ?>
    <div class="ana-stats-flexbox">
<?php foreach ($statItems as $key => [$label, $maxKey]): ?>
        <div class="ana-stat-item">
            <div class="value"><?= htmlspecialchars((string) ($stats[$key] ?? 0)) ?></div>
            <div class="label"><?= $label ?></div>
<?php if (isset($maxKey, $maxMethods[$maxKey])): ?>
            <div class="max">Max <strong><?= htmlspecialchars((string) $maxMethods[$maxKey]['value']) ?></strong>: <a href="detail.php?type=method&amp;name=<?= urlencode($maxMethods[$maxKey]['name']) ?>" title="<?= htmlspecialchars($maxMethods[$maxKey]['name']) ?>"><?= htmlspecialchars($maxMethods[$maxKey]['name']) ?></a></div>
<?php endif; ?>
        </div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php foreach (array_merge([''], $anaGroups) as $group): ?>
<?php if ($group !== '') : ?>
    <h2 class="ana-group"><?= htmlspecialchars($group) ?></h2>
<?php endif; ?>
    <div class="ana-cards">
<?php foreach ($anaPagesIn($group) as $file => [$label, $description]): ?>
<?php if ($file === 'index.php') continue; ?>
        <a class="ana-card" href="<?= $file ?>">
            <h2><?= htmlspecialchars($label) ?></h2>
            <p><?= htmlspecialchars($description) ?></p>
        </a>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>
</body>
</html>
