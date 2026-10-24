<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Method Metrics</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
</head>
<body class="ana-table-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-toolbar">
    <h1>Method &amp; Function Metrics</h1>
    <button id="anaClear">Clear filters</button>
    <button id="anaCsv">Export CSV</button>
    <button id="anaLayout">Layout...</button>
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-presets" id="anaPresets"></div>
<div class="ana-preset-note" id="anaPresetNote"></div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Filter Kind to separate methods, functions and native (built-in) functions. Hover a header for the metric definition.</div>

<div class="ana-table-wrap"><div id="methodTable"></div></div>

<script>
    const table = new Tabulator('#methodTable', {
        ajaxURL: 'api/methodSummary.php',
        // Derived columns, computed here from the API fields
        ajaxResponse: (url, params, response) => response.map(row => ({
            ...row,
            hotspot_score: row.cyclo_complexity * row.calls_in_total,
            is_unused_candidate: row.kind !== 'Native Function' && parseInt(row.calls_in_total) === 0,
        })),
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'cyclo_complexity', dir: 'desc' }],
        columns: [
            anaCol.rowNumber(),
            anaCol.detail('Method', 'method_short_name', 'Method or function name without class or namespace. Click for the method detail page', 'method', 'fqn', { frozen: true }),
            {
                title: 'Identity', columns: [
                    anaCol.text('Namespace', 'namespace', 'Namespace of the class, or of the function'),
                    anaCol.text('Class', 'class_short_name', 'Class name without namespace. Empty for functions'),
                    anaCol.text('Class FQN', 'class_name', 'Fully qualified class name. [Global Scope] for functions'),
                    anaCol.text('FQN', 'fqn', 'Fully qualified method (Class::method) or function name'),
                    anaCol.list('Kind', 'kind', 'Method, Function (user defined) or Native Function (PHP built-in)'),
                    anaCol.list('Visibility', 'visibility', 'public, protected or private'),
                    anaCol.flag('Static', 'is_static', 'Static method'),
                    anaCol.flag('Built-in', 'is_builtin', 'PHP built-in function'),
                ]
            },
            {
                title: 'Complexity', columns: [
                    anaCol.num('Cyclomatic', 'cyclo_complexity', 'Number of independent execution paths'),
                    anaCol.num('Cognitive', 'cognitive_complexity', 'How hard the method is to understand; nesting weighs more'),
                    anaCol.num('Hotspot score', 'hotspot_score', 'Cyclomatic x Calls in. Complex methods that many methods call are the riskiest to change'),
                    anaCol.flag('Pure', 'is_pure', 'Has no side effects'),
                    anaCol.flag('Ends script', 'is_ends', 'Ends the script (exit / die)'),
                ]
            },
            {
                title: 'Signature', columns: [
                    anaCol.num('Params', 'num_parameters', 'Number of parameters'),
                    anaCol.num('Defaults', 'num_default_parameters', 'Parameters with a default value'),
                    anaCol.num('By reference', 'num_reference_parameters', 'Parameters passed by reference'),
                    anaCol.num('Object params', 'num_object_parameters', 'Parameters typed with a class'),
                    anaCol.flag('Sensitive param', 'has_sensitive_parameter', 'Has a #[SensitiveParameter] parameter'),
                    anaCol.text('Return type', 'return_type', 'Declared or inferred return type'),
                    anaCol.text('Signature', 'signature', 'Full signature, without the doc block'),
                    anaCol.text('Doc comment', 'doc_comment', 'Doc block of the method'),
                ]
            },
            {
                title: 'State', columns: [
                    anaCol.num('Props read', 'props_read', 'Properties read'),
                    anaCol.num('Props written', 'props_written', 'Properties written'),
                    anaCol.num('Globals', 'uses_globals', 'Superglobals, global keyword and constants used'),
                ]
            },
            {
                title: 'Calls', columns: [
                    anaCol.num('Calls out', 'calls_out_total', 'Calls this method makes (fan-out)'),
                    anaCol.num('Calls out native', 'calls_out_native', 'Of those, calls to native or global functions'),
                    anaCol.num('Calls in', 'calls_in_total', 'Calls made to this method (fan-in)'),
                    anaCol.text('Calls out list', 'calls_out_list', 'What this method calls'),
                    anaCol.text('Calls in list', 'calls_in_list', 'Who calls this method'),
                    anaCol.flag('Unused candidate', 'is_unused_candidate', 'Nothing calls it. Entry points, callbacks, interface implementations and dynamic calls also look unused'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'method_metrics.csv');
    anaInitLayout(table, 'method_metrics', [{ column: 'cyclo_complexity', dir: 'desc' }]);
    anaInitPresets(table, [
        { label: 'Hotspots', tip: 'Complex methods many methods call, by cyclomatic x calls in', filter: row => row.hotspot_score > 0, sort: [{ column: 'hotspot_score', dir: 'desc' }] },
        { label: 'Unused candidates', tip: 'Methods and functions nothing calls. Private ones are almost certainly dead; check entry points, callbacks and interface methods', filter: row => row.is_unused_candidate, sort: [{ column: 'cyclo_complexity', dir: 'desc' }] },
        { label: 'Hard to read', tip: 'Cognitive complexity of 15 or more', filter: row => row.kind !== 'Native Function' && parseInt(row.cognitive_complexity) >= 15, sort: [{ column: 'cognitive_complexity', dir: 'desc' }] },
        { label: 'Long signatures', tip: 'Five or more parameters, or any by reference', filter: row => parseInt(row.num_parameters) >= 5 || parseInt(row.num_reference_parameters) > 0, sort: [{ column: 'num_parameters', dir: 'desc' }] },
        { label: 'Global state & side effects', tip: 'Methods touching superglobals, the global keyword or constants, or ending the script', filter: row => parseInt(row.uses_globals) > 0 || parseInt(row.is_ends) === 1, sort: [{ column: 'uses_globals', dir: 'desc' }] },
        { label: 'Writes state', tip: 'Methods that write properties', filter: row => parseInt(row.props_written) > 0, sort: [{ column: 'props_written', dir: 'desc' }] },
    ]);
</script>
</body>
</html>
