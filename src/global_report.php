<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Globals Report</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
</head>
<body class="ana-table-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-toolbar">
    <h1>Globals Report</h1>
    <button id="anaClear">Clear filters</button>
    <button id="anaCsv">Export CSV</button>
    <button id="anaLayout">Layout...</button>
    <input type="search" id="anaFind" placeholder="Find a column...">
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <label><input type="checkbox" id="chkGroup"> Group by symbol</label>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-presets" id="anaPresets"></div>
<div class="ana-preset-note" id="anaPresetNote"></div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Group by symbol to see everything that uses one global. Hover a header for the metric definition.</div>

<div class="ana-table-wrap"><div id="globalsTable"></div></div>

<section class="ana-guide">
    <h2>Column guide</h2>
    <div id="anaGuide"></div>
</section>

<section class="ana-guide">
    <h2>Not available in the analysis data</h2>
    <dl>
        <dt>Objects held in a property</dt>
        <dd>Calling a method on a property (<code>$this-&gt;list-&gt;add()</code>) or assigning to a member of it (<code>$this-&gt;obj-&gt;x = 1</code>) counts as a Read of the property: the object may change, the property itself does not.</dd>
        <dt>By-reference arguments</dt>
        <dd>Only arguments passed to by-reference parameters of PHP built-in functions (<code>sort($this-&gt;items)</code>, <code>array_push()</code>, <code>preg_match()</code>...) count as writes, shown as Read/Write. By-reference parameters of project functions and methods are not followed.</dd>
        <dt>Unused global declarations</dt>
        <dd>A <code>global $x</code> that the method never uses is shown as Read.</dd>
        <dt>Data from before this change</dt>
        <dd>Databases analysed with an older Ana have no Read/Write and no access for globals. Analyse again.</dd>
        <dt>Other objects</dt>
        <dd>Only <code>$this-&gt;prop</code>, <code>self::</code> and <code>static::</code> members of the method's own class are recorded. <code>$other-&gt;prop</code>, <code>Other::$prop</code>, <code>Other::CONST</code> and <code>parent::</code> are not.</dd>
        <dt>Inherited members</dt>
        <dd>Members declared in a parent class or trait are not found in the class's own declarations, so their type is shown as Inherited member.</dd>
        <dt>Native</dt>
        <dd>Not stored by the analysis. Superglobals are native, global variables are not, and constants are native when the web server's PHP defines them as built-in. Properties are never native.</dd>
        <dt>Keys and definitions</dt>
        <dd>The key of <code>$_GET['id']</code> and <code>$GLOBALS['x']</code>, and where project constants are defined (<code>define()</code>, <code>const</code>), are not stored.</dd>
        <dt>Script level code</dt>
        <dd>Only code inside methods and functions is analysed. Globals used by top level code of a script are missing.</dd>
        <dt>Where</dt>
        <dd>File and line of each use are not stored, and neither is the number of uses within a method.</dd>
    </dl>
</section>

<script>
    const table = new Tabulator('#globalsTable', {
        ajaxURL: 'api/globalsReport.php',
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'symbol_fqn', dir: 'asc' }],
        groupStartOpen: true,
        groupToggleElement: 'header',
        groupHeader: (value, count, data) => {
            const classes = new Set(data.map(row => row.class_name)).size;
            const writers = data.filter(row => row.access !== 'Read').length;
            return `${value} <span style="font-weight:normal;color:#495057">&mdash; used by ${count} methods in ${classes} classes, ${writers} writing</span>`;
        },
        columns: [
            anaCol.rowNumber(),
            anaCol.text('Symbol', 'symbol', 'Name of the variable, constant or property as written in code, without the class.', { frozen: true }),
            {
                title: 'Global', columns: [
                    anaCol.list('Type', 'symbol_type', 'Superglobal variable ($_GET...), Global variable (global keyword), Constant, Property, Static property, Class constant, or Inherited member (declared in a parent class or trait). Static properties and globals are shared state: the riskiest kinds.'),
                    anaCol.flag('Native', 'is_native', 'Defined by PHP itself: superglobals and built-in constants. Native globals are expected; project ones are the dependencies to question.'),
                    anaCol.text('FQN', 'symbol_fqn', 'Fully qualified name: Class::$property, Class::CONSTANT, or the global name.'),
                    anaCol.list('Access', 'access', 'Read, Write or Read/Write (for example ++, .=, array_push). Assigning to an element, as in $this->items[] = $x, is a Write of the property. Constants are always Read.'),
                ]
            },
            {
                title: 'Used by', columns: [
                    anaCol.text('Namespace', 'namespace', 'Namespace of the class or function that uses the symbol.'),
                    anaCol.detail('Class', 'class_short_name', 'Class that uses the symbol. Click for the class detail page. Empty for functions.', 'class', 'class_name'),
                    anaCol.text('Class FQN', 'class_name', 'Fully qualified name of the class that uses the symbol.'),
                    anaCol.detail('Method', 'method_short_name', 'Method or function that uses the symbol. Click for the method detail page.', 'method', 'method_fqn'),
                    anaCol.text('Method FQN', 'method_fqn', 'Fully qualified Class::method, or the function name.'),
                    anaCol.list('Kind', 'kind', 'Method or Function.'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'globals_report.csv');
    anaInitGuide(table);
    anaInitFind(table);
    anaInitLayout(table, 'global_report', [{ column: 'symbol_fqn', dir: 'asc' }]);
    document.getElementById('chkGroup').addEventListener('change', (event) => {
        table.setGroupBy(event.target.checked ? 'symbol_fqn' : false);
    });
    anaInitPresets(table, [
        { label: 'Superglobals', tip: 'Methods reading $_GET, $_POST, $_SESSION and similar: hidden input that makes them hard to test', filter: row => row.symbol_type === 'Superglobal variable', sort: [{ column: 'symbol', dir: 'asc' }] },
        { label: 'Global variables', tip: 'Methods using the global keyword: hidden dependencies on state defined elsewhere', filter: row => row.symbol_type === 'Global variable', sort: [{ column: 'symbol', dir: 'asc' }] },
        { label: 'Project constants', tip: 'Constants that PHP does not define itself: the project configuration read all over the code', filter: row => row.symbol_type === 'Constant' && !row.is_native, sort: [{ column: 'symbol', dir: 'asc' }] },
        { label: 'Static properties', tip: 'Shared state that lives as long as the process: any method can change it for everyone', filter: row => row.symbol_type === 'Static property', sort: [{ column: 'symbol_fqn', dir: 'asc' }] },
        { label: 'Writes', tip: 'Symbols a method assigns or modifies. Group by symbol to see who changes the same one', filter: row => row.access === 'Write' || row.access === 'Read/Write', sort: [{ column: 'symbol_fqn', dir: 'asc' }] },
    ]);
</script>
</body>
</html>
