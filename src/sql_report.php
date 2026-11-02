<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Report</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
    <style>
        .sql-write { color: #b00020; font-weight: 700; }
        .sql-call { color: #8a4b00; font-weight: 700; }
        .sql-read { color: #1f4e8c; }
        .sql-many { color: #b00020; font-weight: 800; text-decoration: underline; }
        .sql-muted { font-weight: normal; color: #495057; }
    </style>
</head>
<body class="ana-table-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-toolbar">
    <h1>SQL by Method and Table</h1>
    <button id="anaClear">Clear filters</button>
    <button id="anaCsv">Export CSV</button>
    <button id="anaLayout">Layout...</button>
    <input type="search" id="anaFind" placeholder="Find a column...">
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <label>Group by
        <select id="selGroup">
            <option value="object">Table or procedure</option>
            <option value="method">Method</option>
            <option value="">Nothing</option>
        </select>
    </label>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-presets" id="anaPresets"></div>
<div class="ana-preset-note" id="anaPresetNote"></div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Red is what changes data, amber is a stored procedure call, blue is a read. A table changed from two or more classes is underlined in red.</div>

<div class="ana-table-wrap"><div id="sqlTable"></div></div>

<section class="ana-guide">
    <h2>Column guide</h2>
    <div id="anaGuide"></div>
</section>

<section class="ana-guide">
    <h2>What is recorded, and what is not</h2>
    <dl>
        <dt>Where the SQL is found</dt>
        <dd>String literals, concatenations of literals and class constants, heredocs and interpolated strings inside a method or function, whether they are passed straight to a query function or kept in a variable first. A table named by a variable or property is shown as <code>?</code> (tick the Unknown table preset).</dd>
        <dt>Sqler helpers</dt>
        <dd>Calls of <code>insert('table', ...)</code>, <code>update('table', ...)</code> and <code>junctionTable('table', ...)</code> with the table written in the call count as a change of that table. Source tells them from SQL text. Another method with one of those names and a literal first argument would be counted too: check the Source column. The names are in <code>PHPStan/SqlUsageFinder.php</code>.</dd>
        <dt>Which method</dt>
        <dd>The method that holds the SQL text, not the one that runs it. SQL kept in a class constant counts for the methods that read the constant when PHPStan knows its value, never for the constant itself.</dd>
        <dt>Joins and subqueries</dt>
        <dd>Every table after FROM or JOIN is a read. In a multi table UPDATE or DELETE only the first table is the one changed, the others are shown as read. <code>INSERT ... SELECT</code> and <code>CREATE TABLE ... SELECT</code> give a change and a read.</dd>
        <dt>Not recorded</dt>
        <dd>Queries built from several pieces over several statements (<code>.=</code>), SQL in files, stored procedure bodies, query builders and ORMs, and what a called procedure does.</dd>
        <dt>Look at the SQL column</dt>
        <dd>Text that only looks like SQL ("Select an option from the list") can be taken for a query. The SQL column shows what was read.</dd>
    </dl>
</section>

<script>
    const classOf = (row) => row.class_name !== '' ? row.class_name : (row.namespace === '[Global]' ? '' : row.namespace + '\\') + '{functions}';
    const isChange = (row) => row.access === 'Write' || row.access === 'DDL';
    const accessClass = { Write: 'sql-write', DDL: 'sql-write', Call: 'sql-call', Read: 'sql-read' };
    const groupBys = { object: 'object_name', method: 'method_fqn', '': false };

    const table = new Tabulator('#sqlTable', {
        ajaxURL: 'api/sqlReport.php',
        // How many classes change and use each table: a table changed from several classes is where changes collide
        ajaxResponse: (url, params, response) => {
            const writers = new Map();
            const users = new Map();
            response.forEach(row => {
                const key = row.object_type + ' ' + row.object_name;
                if (!users.has(key)) users.set(key, new Set());
                users.get(key).add(classOf(row));
                if (!isChange(row)) return;
                if (!writers.has(key)) writers.set(key, new Set());
                writers.get(key).add(classOf(row));
            });
            return response.map(row => {
                const key = row.object_type + ' ' + row.object_name;
                return { ...row, writer_classes: writers.get(key)?.size ?? 0, user_classes: users.get(key)?.size ?? 0 };
            });
        },
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'object_name', dir: 'asc' }],
        groupBy: 'object_name',
        groupStartOpen: true,
        groupToggleElement: 'header',
        groupHeader: (value, count, data) => {
            const countOf = (access) => data.filter(row => row.access === access).length;
            const changes = data.filter(isChange);
            const operations = [...new Set(changes.map(row => row.operation))].join(', ');
            const classes = new Set(data.map(classOf)).size;
            const writerClasses = new Set(changes.map(classOf)).size;
            const verdict = writerClasses >= 2
                ? `<span class="sql-many">changed from ${writerClasses} classes</span>`
                : (changes.length > 0 ? '<span class="sql-write">changed</span>' : '');
            return `${value} <span class="sql-muted">&mdash; ${count} uses from ${classes} class${classes === 1 ? '' : 'es'}: ` +
                `${countOf('Read')} reads, ${changes.length} changes${operations ? ' (' + operations + ')' : ''}, ${countOf('Call')} calls</span> ${verdict}`;
        },
        columns: [
            anaCol.rowNumber(),
            {
                title: 'SQL', columns: [
                    anaCol.text('Table or procedure', 'object_name', 'The table, view or stored procedure the SQL names. ? means the name is built from a variable and is not known.', {
                        formatter: (cell) => {
                            const box = document.createElement('span');
                            box.textContent = cell.getValue();
                            if (cell.getRow().getData().writer_classes >= 2) {
                                box.className = 'sql-many';
                                box.title = 'Changed from ' + cell.getRow().getData().writer_classes + ' different classes';
                            }
                            return box;
                        },
                        frozen: true,
                    }),
                    anaCol.list('Type', 'object_type', 'table, procedure, view, function, trigger, event or database.'),
                    anaCol.list('Operation', 'operation', 'SELECT, INSERT, UPDATE, DELETE, REPLACE, TRUNCATE, CALL, or CREATE, ALTER, DROP, RENAME for DDL.'),
                    anaCol.list('Access', 'access', 'Read: SELECT. Write: INSERT, UPDATE, DELETE, REPLACE, TRUNCATE. Call: a stored procedure. DDL: CREATE, ALTER, DROP, RENAME. Writes and DDL change data or structure for everyone.', {
                        formatter: (cell) => {
                            const box = document.createElement('span');
                            box.textContent = cell.getValue();
                            box.className = accessClass[cell.getValue()] ?? '';
                            return box;
                        },
                    }),
                    anaCol.flag('Unknown table', 'is_dynamic', 'The table or procedure name is built from a variable or property, so it is not known. The operation is still right.'),
                    anaCol.list('Source', 'source', 'SQL text: a string in the code that reads as SQL. Sqler helper: a call of insert(), update() or junctionTable() with the table written in it.'),
                    anaCol.text('SQL', 'snippet', 'The text the row was read from, up to 255 characters; {?} is a part only known at run time. Check it when a row looks wrong.', { width: 380 }),
                ]
            },
            {
                title: 'Across the project', columns: [
                    anaCol.num('Classes changing it', 'writer_classes', 'How many different classes write to this table or change its structure. Two or more is the risk: the classes can break each other.', {
                        formatter: (cell) => {
                            const box = document.createElement('span');
                            box.textContent = cell.getValue();
                            if (cell.getValue() >= 2) box.className = 'sql-many';
                            return box;
                        },
                    }),
                    anaCol.num('Classes using it', 'user_classes', 'How many different classes read, change or call it. Functions count as one class per namespace.'),
                ]
            },
            {
                title: 'Used by', columns: [
                    anaCol.detail('Method', 'method_short_name', 'Method or function that holds the SQL. Click for the method detail page.', 'method', 'method_fqn'),
                    anaCol.detail('Namespace', 'namespace', 'Namespace of the class or function. Click it to open the namespace detail page.', 'namespace', 'namespace'),
                    anaCol.detail('Class', 'class_short_name', 'Class of the method. Click for the class detail page. Empty for functions.', 'class', 'class_name'),
                    anaCol.detail('Class FQN', 'class_name', 'Fully qualified class name. Click it to open the class detail page.', 'class', 'class_name'),
                    anaCol.detail('Method FQN', 'method_fqn', 'Fully qualified Class::method, or the function name. Click it to open the method detail page.', 'method', 'method_fqn'),
                    anaCol.list('Kind', 'kind', 'Method or Function.'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'sql_report.csv');
    anaInitGuide(table);
    anaInitFind(table);
    anaInitLayout(table, 'sql_report', [{ column: 'object_name', dir: 'asc' }]);
    document.getElementById('selGroup').addEventListener('change', (event) => table.setGroupBy(groupBys[event.target.value]));
    anaInitPresets(table, [
        { label: 'Data changes', tip: 'INSERT, UPDATE, DELETE, REPLACE and TRUNCATE: the methods that change data, by table', filter: row => row.access === 'Write', sort: [{ column: 'object_name', dir: 'asc' }] },
        { label: 'Changed from 2+ classes', tip: 'Tables changed or restructured by two or more classes: the riskiest, any of them can break the others', filter: row => row.writer_classes >= 2 && isChange(row), sort: [{ column: 'writer_classes', dir: 'desc' }, { column: 'object_name', dir: 'asc' }] },
        { label: 'Calls', tip: 'Methods that CALL a stored procedure: what the procedure changes is not visible here', filter: row => row.access === 'Call', sort: [{ column: 'object_name', dir: 'asc' }] },
        { label: 'Reads', tip: 'SELECTs, and the tables an INSERT, UPDATE or DELETE reads', filter: row => row.access === 'Read', sort: [{ column: 'object_name', dir: 'asc' }] },
        { label: 'DDL', tip: 'CREATE, ALTER, DROP and RENAME issued by the code', filter: row => row.access === 'DDL', sort: [{ column: 'object_name', dir: 'asc' }] },
        { label: 'Unknown table', tip: 'The table name is built at run time and could not be read: check these by hand', filter: row => row.is_dynamic, sort: [{ column: 'method_fqn', dir: 'asc' }] },
        { label: 'Sqler helpers', tip: 'Found through insert(), update() or junctionTable(), not through SQL text', filter: row => row.source === 'Sqler helper', sort: [{ column: 'object_name', dir: 'asc' }] },
    ]);
</script>
</body>
</html>
