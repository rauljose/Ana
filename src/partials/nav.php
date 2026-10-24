<?php declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

// Single source of truth for the pages: the top menu and the index page both read this list
$anaPages = [
    'index.php' => ['Home', 'Project overview and links to every page'],
    'class_metrics.php' => ['Class Metrics', 'Every recovered class metric in one sortable, filterable table'],
    'method_metrics.php' => ['Method Metrics', 'Every recovered method and function metric in one sortable, filterable table'],
    'class_graph.php' => ['Class Graphs', 'Zone of Pain scatter plot and class health radar'],
    'method_graph.php' => ['Method Graphs', 'Method hotspots scatter plot and method health radar'],
];

$anaProjectName = '';
$anaAnalyzedAt = '';
try {
    $res = $mysqli->query("SELECT name, analyzed_at FROM project LIMIT 1");
    $row = $res->fetch_assoc();
    $anaProjectName = $row['name'] ?? '';
    $anaAnalyzedAt = $row['analyzed_at'] ?? '';
} catch (mysqli_sql_exception $e) {
    // Database analyzed before the project table existed: show the menu without a project name
}

$anaCurrentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="ana-nav">
    <span class="ana-brand">Ana</span>
    <span class="ana-project" title="Analyzed <?= htmlspecialchars($anaAnalyzedAt) ?>">
        Project: <strong><?= htmlspecialchars($anaProjectName !== '' ? $anaProjectName : 'unknown') ?></strong>
    </span>
    <ul class="ana-menu">
<?php foreach ($anaPages as $file => [$label, $description]): ?>
        <li><a href="<?= $file ?>" title="<?= htmlspecialchars($description) ?>"<?= $file === $anaCurrentPage ? ' class="active"' : '' ?>><?= htmlspecialchars($label) ?></a></li>
<?php endforeach; ?>
    </ul>
</nav>
