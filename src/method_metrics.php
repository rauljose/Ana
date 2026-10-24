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
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Filter Kind to separate methods, functions and native (built-in) functions. Hover a header for the metric definition.</div>

<div class="ana-table-wrap"><div id="methodTable"></div></div>

<script>
    const table = new Tabulator('#methodTable', {
        ajaxURL: 'api/methodSummary.php',
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'cyclo_complexity', dir: 'desc' }],
        columns: [
            anaCol.text('Method', 'method_short_name', 'Method or function name without class or namespace', { frozen: true }),
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
                    anaCol.text('Signature', 'signature', 'Full signature'),
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
                ]
            },
        ],
    });

    anaInitToolbar(table, 'method_metrics.csv');
</script>
</body>
</html>
