<?php declare(strict_types=1);
require_once __DIR__ . '/pages.php';
if (!isset($analysisReport) || !in_array($analysisReport, ['impact', 'state', 'consumers', 'cycles'], true)) { http_response_code(404); exit; }
$analysisTitle = $anaPages[basename($_SERVER['SCRIPT_NAME'])][0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($analysisTitle) ?> · Ana</title>
    <link rel="stylesheet" href="css/ana.css">
    <link rel="stylesheet" href="css/analysis_reports.css">
    <?php require __DIR__ . '/tabulator_head.php'; ?>
    <script src="js/ana_graph.js"></script>
    <script src="js/analysis_report_data.js"></script>
</head>
<body class="analysis-report-page" data-analysis-report="<?= $analysisReport ?>">
<?php require __DIR__ . '/nav.php'; ?>
<main class="analysis-page">
    <h1><?= htmlspecialchars($analysisTitle) ?></h1>
    <p id="reportIntro"></p>
    <div id="reportControls" class="analysis-controls"></div>
    <p id="reportStatus" role="status" aria-live="polite">Loading analysis data…</p>
    <div class="analysis-controls">
        <button id="reportClear" disabled>Clear table filters</button>
        <button id="reportCsv" disabled>Export filtered CSV</button>
    </div>
    <p class="analysis-hint">Sort by clicking a column heading. Text filters match part of a value; numeric filters accept comparisons such as &gt;5. Click a row for its evidence below.</p>
    <div id="reportTable"></div>
    <section class="analysis-evidence" id="reportEvidence" aria-live="polite"><h2>Evidence</h2><p>Select a row to inspect its recorded relationships.</p></section>
    <section class="analysis-note"><h2>Interpretation</h2><div id="reportNotes"></div>
        <p>Everything here comes from the stored analysis, not from running the code. Calls are places in the source that call something, not how often they run. Dynamic calls (<code>$object->$name()</code>, callbacks by name) and code outside any method or function are not recorded. If you analyzed again without re-creating the database, classes and methods deleted since may still be listed.</p>
    </section>
</main>
<script src="js/analysis_reports.js"></script>
</body>
</html>
