<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis Reports · Ana</title>
    <link rel="stylesheet" href="css/ana.css">
    <link rel="stylesheet" href="css/analysis_reports.css">
</head>
<body class="analysis-report-page">
<?php require __DIR__ . '/partials/nav.php'; ?>
<main class="analysis-page">
    <h1>Analysis Reports</h1>
    <p>Explore maintenance impact, consumers, shared state and cycles using the existing analysis data.</p>
    <div class="analysis-cards">
        <a href="change_impact.php"><h2>Change impact</h2><p>Choose a method or function. Find recorded direct and indirect callers, with a shortest call path for each.</p></a>
        <a href="shared_state.php"><h2>Shared-state dependencies</h2><p>See which methods write and read each global symbol or static property, across namespaces.</p></a>
        <a href="api_consumers.php"><h2>Public-method consumers</h2><p>Separate call sites from distinct consumers. Find public methods used inside their class, elsewhere in the project, or with no recorded calls.</p></a>
        <a href="dependency_cycles.php"><h2>Cycle groups</h2><p>Inspect groups of mutually dependent methods, classes or namespaces, with an example cycle and the recorded edges.</p></a>
    </div>
    <section class="analysis-note"><h2>How to use these reports</h2>
        <p>Use them to plan a package upgrade, investigate a regression, choose regression-test coverage, or understand a development boundary. They do not prescribe splitting classes or changing an installed package's API.</p>
        <p>All findings describe recorded static relationships. Call sites are source occurrences, not runtime frequency. Missing calls do not prove a symbol is unused. File locations, runtime behavior, historical comparisons and unresolved-call coverage are not stored in the current schema.</p>
    </section>
</main>
</body>
</html>
