<?php declare(strict_types=1);
$analysisTitles = ['impact' => 'Change Impact', 'state' => 'Shared-State Dependencies', 'consumers' => 'Public-Method Consumers', 'cycles' => 'Cycle Groups'];
if (!isset($analysisReport, $analysisTitles[$analysisReport])) { http_response_code(404); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($analysisTitles[$analysisReport]) ?> · Ana</title>
    <link rel="stylesheet" href="css/ana.css">
    <link rel="stylesheet" href="css/analysis_reports.css">
    <?php require __DIR__ . '/tabulator_head.php'; ?>
    <script src="js/ana_graph.js"></script>
    <script src="js/analysis_report_data.js"></script>
</head>
<body class="analysis-report-page" data-analysis-report="<?= $analysisReport ?>">
<?php require __DIR__ . '/nav.php'; ?>
<main class="analysis-page">
    <nav class="analysis-links" aria-label="Analysis reports">
        <a href="analysis_reports.php">Reports home</a>
        <a href="change_impact.php">Change impact</a>
        <a href="shared_state.php">Shared state</a>
        <a href="method_public_consumers.php">Public-method consumers</a>
        <a href="dependency_cycles.php">Cycle groups</a>
    </nav>
    <h1><?= htmlspecialchars($analysisTitles[$analysisReport]) ?></h1>
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
        <p>Counts describe the current stored analysis. Unrecorded dynamic calls and entry points are outside these reports. Calls are static call sites, not execution counts. The existing exporter can retain old class and method definitions between runs; these pages do not repair or change analysis data.</p>
    </section>
</main>
<script src="js/analysis_reports.js"></script>
</body>
</html>
