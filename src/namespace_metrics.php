<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Namespace Metrics</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
    <script src="js/ana_graph.js"></script>
</head>
<body class="ana-table-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-toolbar">
    <h1>Namespace Metrics</h1>
    <button id="anaClear">Clear filters</button>
    <button id="anaCsv">Export CSV</button>
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-presets" id="anaPresets"></div>
<div class="ana-preset-note" id="anaPresetNote"></div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Hover a header for the metric definition.</div>

<div class="ana-table-wrap"><div id="namespaceTable"></div></div>

<script>
    const table = new Tabulator('#namespaceTable', {
        ajaxURL: 'api/namespaceSummary.php',
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'total_cyclo', dir: 'desc' }],
        // Derived column: namespaces that are part of a dependency cycle
        ajaxResponse: (url, params, response) => {
            const pairs = [];
            response.forEach(row => {
                if (row.namespaces_used === '') return;
                row.namespaces_used.split(', ').forEach(target => pairs.push([row.namespace, target]));
            });
            const inCycle = anaCycleNodes(pairs);
            return response.map(row => ({ ...row, in_cycle: inCycle.has(row.namespace) }));
        },
        columns: [
            anaCol.text('Namespace', 'namespace', 'Namespace. Classes are rolled up by their exact namespace, sub namespaces are separate rows', { frozen: true }),
            {
                title: 'Types', columns: [
                    anaCol.num('Classes', 'classes', 'Concrete classes'),
                    anaCol.num('Abstract', 'abstract_classes', 'Abstract classes'),
                    anaCol.num('Interfaces', 'interfaces', 'Interfaces'),
                    anaCol.num('Traits', 'traits', 'Traits'),
                    anaCol.num('Total types', 'total_types', 'Classes, abstract classes, interfaces and traits'),
                    anaCol.num('Methods', 'methods', 'Methods of all classes in the namespace'),
                ]
            },
            {
                title: 'Complexity', columns: [
                    anaCol.num('Cyclo total', 'total_cyclo', 'Sum of cyclomatic complexity of all methods'),
                    anaCol.num('Cyclo avg', 'avg_cyclo', 'Average cyclomatic complexity per method'),
                    anaCol.num('Cyclo max', 'max_cyclo', 'Most complex method'),
                    anaCol.num('Cognitive total', 'total_cog', 'Sum of cognitive complexity of all methods'),
                    anaCol.num('Pure %', 'pure_methods_pct', 'Percentage of pure methods'),
                    anaCol.num('Hotspot score', 'hotspot_score', 'Cyclomatic total x Ca. Complex namespaces many namespaces depend on'),
                ]
            },
            {
                title: 'Coupling', columns: [
                    anaCol.num('Ca', 'afferent_ca', 'Afferent coupling: namespaces that call this one'),
                    anaCol.num('Ce', 'efferent_ce', 'Efferent coupling: namespaces this one calls'),
                    anaCol.num('Instability', 'instability', 'Ce / (Ca + Ce). 0 = stable, 1 = unstable'),
                    anaCol.num('Abstractness', 'abstractness', '(Interfaces + abstract classes) / total types'),
                    anaCol.num('Main seq. distance', 'main_seq_distance', '|Abstractness + Instability - 1|. Near 0 is balanced; near 1 is rigid or useless'),
                    anaCol.num('Calls out', 'calls_out', 'Calls to other namespaces'),
                    anaCol.num('Calls in', 'calls_in', 'Calls received from other namespaces'),
                    anaCol.num('Calls internal', 'calls_internal', 'Calls between classes of the same namespace'),
                    anaCol.num('Internal ratio', 'internal_call_ratio', 'Internal calls / (internal + calls out). High means cohesive, low means it mostly talks to other namespaces'),
                    anaCol.flag('In cycle', 'in_cycle', 'Part of a dependency cycle between namespaces'),
                    anaCol.text('Namespaces used', 'namespaces_used', 'Namespaces this one calls'),
                    anaCol.text('Namespaces using this', 'namespaces_using_this', 'Namespaces that call this one'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'namespace_metrics.csv');
    anaInitPresets(table, [
        { label: 'Hotspots', tip: 'Complex namespaces many namespaces depend on, by cyclomatic total x Ca', filter: row => row.hotspot_score > 0, sort: [{ column: 'hotspot_score', dir: 'desc' }] },
        { label: 'In cycles', tip: 'Namespaces in a dependency cycle. Open the Call Graph at namespace level to see them', filter: row => row.in_cycle, sort: [{ column: 'efferent_ce', dir: 'desc' }] },
        { label: 'Main sequence', tip: 'Namespaces furthest from the balance between abstractness and instability', filter: row => row.main_seq_distance >= 0.7, sort: [{ column: 'main_seq_distance', dir: 'desc' }] },
        { label: 'Most coupled', tip: 'Namespaces depending on the most other namespaces', filter: row => row.efferent_ce > 0, sort: [{ column: 'efferent_ce', dir: 'desc' }] },
        { label: 'Least cohesive', tip: 'Namespaces that mostly call other namespaces instead of themselves', filter: row => row.calls_out + row.calls_internal > 0 && row.internal_call_ratio < 0.5, sort: [{ column: 'internal_call_ratio', dir: 'asc' }] },
    ]);
</script>
</body>
</html>
