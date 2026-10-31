<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Method Globals</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
</head>
<body class="ana-table-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-toolbar">
    <h1>Globals Used by Each Method</h1>
    <button id="anaClear">Clear filters</button>
    <button id="anaCsv">Export CSV</button>
    <button id="anaLayout">Layout...</button>
    <input type="search" id="anaFind" placeholder="Find a column...">
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <label><input type="checkbox" id="chkGroup" checked> Group by method</label>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-presets" id="anaPresets"></div>
<div class="ana-preset-note" id="anaPresetNote"></div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Grouped by method, each header summarises the globals that method uses. Untick Group by method for a flat list. Hover a header for the metric definition.</div>

<div class="ana-table-wrap"><div id="globalsTable"></div></div>

<section class="ana-guide">
    <h2>Column guide</h2>
    <div id="anaGuide"></div>
</section>

<section class="ana-guide">
    <h2>Not available in the analysis data</h2>
    <dl>
        <dt>Which key or name</dt>
        <dd>The key of <code>$_GET['id']</code> or <code>$GLOBALS['x']</code> is not stored: the row says <code>$_GET</code>, not which parameter.</dd>
        <dt>By-reference arguments</dt>
        <dd>Only arguments passed to by-reference parameters of PHP built-in functions count as writes, shown as Read/Write. Project functions and methods are not followed.</dd>
        <dt>Unused global declarations</dt>
        <dd>A <code>global $x</code> that the method never uses is shown as Read.</dd>
        <dt>Native</dt>
        <dd>Not stored by the analysis. Superglobals are native, global variables are not, and a constant is native when the web server's PHP defines it as built-in.</dd>
        <dt>Script level code</dt>
        <dd>Only code inside methods and functions is analysed. Globals used by top level code of a script are missing.</dd>
        <dt>Where</dt>
        <dd>File and line of each use are not stored, and neither is the number of uses within a method.</dd>
    </dl>
    <p>Properties and static properties are in the <a href="global_report.php">Globals Report</a>.</p>
</section>

<script>
    const table = new Tabulator('#globalsTable', {
        ajaxURL: 'api/globalsReport.php?only=globals',
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'method_fqn', dir: 'asc' }],
        groupBy: 'method_fqn',
        groupStartOpen: true,
        groupToggleElement: 'header',
        groupHeader: (value, count, data) => {
            const countOf = (type) => data.filter(row => row.symbol_type === type).length;
            const projectOnes = data.filter(row => !row.is_native).length;
            const writes = data.filter(row => row.access !== 'Read').length;
            return `${value} <span style="font-weight:normal;color:#495057">&mdash; ${count} globals: ` +
                `${countOf('Constant')} constants, ${countOf('Superglobal variable')} superglobals, ${countOf('Global variable')} variables; ` +
                `${projectOnes} not native, ${writes} written</span>`;
        },
        columns: [
            anaCol.rowNumber(),
            anaCol.detail('Method', 'method_short_name', 'Method or function that uses the global. Click for the method detail page.', 'method', 'method_fqn', { frozen: true }),
            {
                title: 'Global', columns: [
                    anaCol.text('Global', 'symbol', 'Name of the constant or variable as written in code ($_GET, $db, APP_ROOT).'),
                    anaCol.list('Type', 'symbol_type', 'Constant, Superglobal variable ($_GET, $_POST, $_SESSION...) or Global variable (declared with the global keyword). All three are hidden inputs: the method result depends on something its caller did not pass in.'),
                    anaCol.flag('Native', 'is_native', 'Defined by PHP itself. Native globals are expected; the non native ones are project configuration and shared state: the dependencies to question.'),
                    anaCol.flag('Own class', 'is_own_class', 'The symbol is declared by the same class that uses it (its own property, static property or class constant). Untick to see only what crosses a class boundary: global variables, superglobals, project constants and inherited members. Always false for globals, which belong to no class.'),
                    anaCol.list('Access', 'access', 'Read, Write or Read/Write (for example .=, ++, array_push). Writing a global changes state for every other method that uses it, the riskiest case. Constants are always Read.'),
                ]
            },
            {
                title: 'Used by', columns: [
                    anaCol.detail('Namespace', 'namespace', 'Namespace of the class or function. Click it to open the namespace detail page.', 'namespace', 'namespace'),
                    anaCol.detail('Class', 'class_short_name', 'Class of the method. Click for the class detail page. Empty for functions.', 'class', 'class_name'),
                    anaCol.detail('Class FQN', 'class_name', 'Fully qualified class name. Click it to open the class detail page.', 'class', 'class_name'),
                    anaCol.detail('Method FQN', 'method_fqn', 'Fully qualified Class::method, or the function name. Click it to open the method detail page.', 'method', 'method_fqn'),
                    anaCol.list('Kind', 'kind', 'Method or Function.'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'method_globals.csv');
    anaInitGuide(table);
    anaInitFind(table);
    anaInitLayout(table, 'method_globals', [{ column: 'method_fqn', dir: 'asc' }]);
    document.getElementById('chkGroup').addEventListener('change', (event) => {
        table.setGroupBy(event.target.checked ? 'method_fqn' : false);
    });
    anaInitPresets(table, [
        { label: 'Writes', tip: 'Methods that assign or modify a global: they change state for everyone', filter: row => row.access !== 'Read', sort: [{ column: 'method_fqn', dir: 'asc' }] },
        { label: 'Not native', tip: 'Project constants and global variables: configuration and shared state defined by the project itself', filter: row => !row.is_native, sort: [{ column: 'method_fqn', dir: 'asc' }] },
        { label: 'Superglobals', tip: 'Methods reading $_GET, $_POST, $_SESSION and similar: hidden input that makes them hard to test', filter: row => row.symbol_type === 'Superglobal variable', sort: [{ column: 'symbol', dir: 'asc' }] },
        { label: 'Global variables', tip: 'Methods using the global keyword: hidden dependencies on state defined elsewhere', filter: row => row.symbol_type === 'Global variable', sort: [{ column: 'symbol', dir: 'asc' }] },
        { label: 'Project constants', tip: 'Constants that PHP does not define itself', filter: row => row.symbol_type === 'Constant' && !row.is_native, sort: [{ column: 'symbol', dir: 'asc' }] },
    ]);
</script>
</body>
</html>
