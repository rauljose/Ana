<?php declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/pages.php';

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
<?php foreach ($anaPages as $file => $page): ?>
<?php [$label, $description] = $page; ?>
<?php if (($page[2] ?? true) === false) continue; ?>
        <li><a href="<?= $file ?>" title="<?= htmlspecialchars($description) ?>"<?= $file === $anaCurrentPage ? ' class="active"' : '' ?>><?= htmlspecialchars($label) ?></a></li>
<?php endforeach; ?>
    </ul>
</nav>
