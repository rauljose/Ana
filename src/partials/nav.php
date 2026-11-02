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

    <span class="ana-brand"><img src="ana-icon.svg" style="width:1.6em;height:auto;border:none;margin-right:0;" alt="Logo">Ana</span>
    <span class="ana-project" title="Analyzed <?= htmlspecialchars($anaAnalyzedAt) ?>">
        Project: <strong><?= htmlspecialchars($anaProjectName !== '' ? $anaProjectName : 'unknown') ?></strong>
    </span>
    <ul class="ana-menu">
<?php foreach ($anaPagesIn('') as $file => [$label, $description]): ?>
        <li><a href="<?= $file ?>" title="<?= htmlspecialchars($description) ?>"<?= $file === $anaCurrentPage ? ' class="active"' : '' ?>><?= htmlspecialchars($label) ?></a></li>
<?php endforeach; ?>
<?php foreach ($anaGroups as $group): ?>
        <li>
            <details class="ana-menu-group<?= isset($anaPagesIn($group)[$anaCurrentPage]) ? ' active' : '' ?>">
                <summary><?= htmlspecialchars($group) ?></summary>
                <ul>
<?php foreach ($anaPagesIn($group) as $file => [$label, $description]): ?>
                    <li><a href="<?= $file ?>" title="<?= htmlspecialchars($description) ?>"<?= $file === $anaCurrentPage ? ' class="active"' : '' ?>><?= htmlspecialchars($label) ?></a></li>
<?php endforeach; ?>
                </ul>
            </details>
        </li>
<?php endforeach; ?>
    </ul>
</nav>
<script>
    // One drop-down open at a time; a click elsewhere or Escape closes it
    (() => {
        const groups = [...document.querySelectorAll('.ana-menu-group')];
        groups.forEach(group => group.addEventListener('toggle', () => {
            if (group.open) groups.forEach(other => { if (other !== group) other.open = false; });
        }));
        document.addEventListener('click', (event) => groups.forEach(group => { if (!group.contains(event.target)) group.open = false; }));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') groups.forEach(group => { group.open = false; }); });
    })();
</script>
