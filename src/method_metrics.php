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
    <input type="search" id="anaFind" placeholder="Find a column...">
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

<section class="ana-guide">
    <h2>Column guide</h2>
    <div id="anaGuide"></div>
</section>

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
            anaCol.detail('Method', 'method_short_name', 'Method or function name without class or namespace. Click it to open the method detail page.', 'method', 'fqn', { frozen: true }),
            {
                title: 'Identity', columns: [
                    anaCol.detail('Namespace', 'namespace', 'Namespace of the class, or of the function. Click it to open the namespace detail page.', 'namespace', 'namespace'),
                    anaCol.detail('Class', 'class_short_name', 'Class name without namespace. Empty for functions. Click it to open the class detail page.', 'class', 'class_name'),
                    anaCol.detail('Class FQN', 'class_name', 'Fully qualified class name. [Global Scope] for functions. Click it to open the class detail page.', 'class', 'class_name'),
                    anaCol.detail('FQN', 'fqn', 'Fully qualified name: Class::method for methods, the namespaced name for functions. The unique key. Click it to open the method detail page.', 'method', 'fqn'),
                    anaCol.list('Kind', 'kind', 'Method, Function (defined in the project) or Native Function (PHP built-in, nothing to measure).'),
                    anaCol.list('Visibility', 'visibility', 'public, protected or private. Public methods are the contract other code relies on.'),
                    anaCol.flag('Static', 'is_static', 'Static method: called on the class, cannot use instance state.'),
                    anaCol.flag('Built-in', 'is_builtin', 'PHP built-in function. Only present because project code calls it.'),
                ]
            },
            {
                title: 'Complexity', columns: [
                    anaCol.num('Cyclomatic', 'cyclo_complexity', 'Number of independent paths through the method, the minimum number of tests. 1 to 5 is simple, over 10 should be split, over 20 is very risky.'),
                    anaCol.num('Cognitive', 'cognitive_complexity', 'How hard the method is to read; nesting costs extra. Up to about 8 is comfortable, 15 or more is hard to follow.'),
                    anaCol.num('Hotspot score', 'hotspot_score', 'Cyclomatic x Calls in. Complex methods that many methods call are the riskiest to change: sort descending to find them.'),
                    anaCol.flag('Pure', 'is_pure', 'No side effects: same input gives the same output, no writes to state or globals. Pure methods are easy to test and reuse.'),
                    anaCol.flag('Ends script', 'is_ends', 'Ends the script (exit, die or never return type). Cannot be tested or reused normally, ideally only in entry points.'),
                ]
            },
            {
                title: 'Signature', columns: [
                    anaCol.num('Params', 'num_parameters', 'Number of parameters. More than 4 or 5 is hard to call correctly and often hides a missing object.'),
                    anaCol.num('Defaults', 'num_default_parameters', 'Parameters with a default value. Many optional parameters suggest a method doing several jobs.'),
                    anaCol.num('By reference', 'num_reference_parameters', 'Parameters passed by reference. The method changes the callers variables silently, ideally 0.'),
                    anaCol.num('Object params', 'num_object_parameters', 'Parameters typed with a class: how many other classes this method depends on through its signature.'),
                    anaCol.flag('Sensitive param', 'has_sensitive_parameter', 'Has a #[SensitiveParameter]: a secret passes through. Informational.'),
                    anaCol.text('Return type', 'return_type', 'Declared or inferred return type. mixed means the type is unknown to callers.'),
                    anaCol.text('Signature', 'signature', 'Full signature, without the doc block.'),
                    anaCol.text('Doc comment', 'doc_comment', 'Doc block of the method. Empty means undocumented.'),
                ]
            },
            {
                title: 'State', columns: [
                    anaCol.num('Props read', 'props_read', 'Number of properties the method reads. Together with written ones, shows how tied it is to object state.'),
                    anaCol.num('Props written', 'props_written', 'Number of properties the method writes. Writers change object state: more of them means more places to check when state is wrong.'),
                    anaCol.num('Globals', 'uses_globals', 'Superglobals, global keyword and constants used. Each one is a hidden input, ideally 0 outside entry points.'),
                    anaCol.text('Props read list', 'props_read_list', 'The properties this method reads.'),
                    anaCol.text('Props written list', 'props_written_list', 'The properties this method writes. Look here first when a property has an unexpected value.'),
                    anaCol.text('Globals list', 'globals_list', 'The globals used: $_GET style superglobals, global $name variables and constants.'),
                ]
            },
            {
                title: 'Calls', columns: [
                    anaCol.num('Calls out', 'calls_out_total', 'Calls the method makes (fan-out). High means it depends on a lot of code and breaks easily.'),
                    anaCol.num('Calls out native', 'calls_out_native', 'Of the calls made, how many go to native or global functions.'),
                    anaCol.num('Calls in', 'calls_in_total', 'Calls made to this method (fan-in). High means many places depend on it: change it with care.'),
                    anaCol.detailList('Calls out list', 'calls_out_list', 'What this method calls. Click a method to open its detail page.', 'method', { itemName: anaCallListName }),
                    anaCol.detailList('Calls in list', 'calls_in_list', 'Who calls this method. Check these before changing it.', 'method', { itemName: anaCallListName }),
                    anaCol.flag('Unused candidate', 'is_unused_candidate', 'Nothing calls it. Entry points, callbacks, interface methods and dynamic calls also look unused; private ones are almost certainly dead.'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'method_metrics.csv');
    anaInitGuide(table);
    anaInitFind(table);
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
