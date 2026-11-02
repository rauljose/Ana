<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firebase Report</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
    <style>
        .fb-destroy { color: #b00020; font-weight: 700; }
        .fb-children { color: #8a4b00; font-weight: 700; }
        .fb-append { color: #1b6b2f; font-weight: 700; }
        .fb-read { color: #1f4e8c; }
        .fb-many { color: #b00020; font-weight: 800; text-decoration: underline; }
        .fb-muted { font-weight: normal; color: #495057; }
        /* Tabulator colors the text of a group header red: here red is kept for what needs attention */
        .tabulator-row.tabulator-group span.fb-muted { color: #495057; }
        .tabulator-row.tabulator-group span.fb-many { color: #b00020; }
    </style>
</head>
<body class="ana-table-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-toolbar">
    <h1>Firebase by Method and Path</h1>
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
            <option value="path">Path</option>
            <option value="method">Method</option>
            <option value="">Nothing</option>
        </select>
    </label>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-presets" id="anaPresets"></div>
<div class="ana-preset-note" id="anaPresetNote"></div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Red overwrites or deletes the path and everything under it, amber changes only the children it names, green appends a new child, blue reads. A path changed from two or more classes is underlined in red. {?} is a part of the path only known at run time: <code>users/{?}</code> is any user.</div>

<div class="ana-table-wrap"><div id="firebaseTable"></div></div>

<section class="ana-guide">
    <h2>Column guide</h2>
    <div id="anaGuide"></div>
</section>

<section class="ana-guide">
    <h2>What is recorded, and what is not</h2>
    <dl>
        <dt>Services</dt>
        <dd>The Realtime Database (references: <code>getReference()</code>, <code>getChild()</code>, <code>set()</code>, <code>update()</code>, <code>push()</code>, <code>remove()</code>, queries, transactions), Firestore (collections and documents of the Google client behind <code>createFirestore()</code>) and Cloud Storage (objects and folders of the bucket), all through kreait/firebase-php.</dd>
        <dt>Effect</dt>
        <dd><strong>Overwrites</strong>: Realtime <code>set()</code>, Firestore <code>set()</code> without merge, a Storage <code>upload()</code> or copy to a name that exists. <strong>Changes only the children it names</strong>: <code>update()</code>, Firestore <code>set()</code> with merge, metadata updates. <strong>Appends</strong>: <code>push()</code>, Firestore <code>add()</code> and <code>create()</code>. <strong>Deletes</strong>: <code>remove()</code> or <code>set(null)</code>, Firestore <code>delete()</code> (which leaves the document's subcollections), Storage <code>delete()</code>. Reads: everything under the path, a query, only the names of the children, or only metadata.</dd>
        <dt>Where the path is found</dt>
        <dd>In the call chain (<code>getReference('a')-&gt;getChild('b')-&gt;set(...)</code>), in a variable or <code>$this</code> property that was given the reference in the same method, in the reference passed to a transaction, bulk writer or batch, and in typed parameters (<code>Reference $ref</code>, a path not known there).</dd>
        <dt>Which method</dt>
        <dd>The method that makes the call. A reference built in one method and used in another is not followed, and a path kept in a property set in the constructor shows as {?}.</dd>
        <dt>Not recorded</dt>
        <dd>Security rules, the Realtime Database REST calls made without the SDK, <code>getReferenceFromUrl()</code>, listeners, Firestore field level operations (which fields are written), and calls on objects whose type cannot be told from the code. Storage calls whose names other objects share (<code>delete()</code>, <code>exists()</code>) count only when the object comes from a bucket named bucket, or from <code>getBucket()</code>.</dd>
        <dt>Look at the Code column</dt>
        <dd>It shows the call each row was read from. Calls of other classes that happen to share these method names can be taken for Firebase.</dd>
    </dl>
</section>

<script>
    const classOf = (row) => row.class_name !== '' ? row.class_name : (row.namespace === '[Global]' ? '' : row.namespace + '\\') + '{functions}';
    const isWrite = (row) => row.access === 'Write';
    // Writers that can break each other: overwriting, deleting or changing children. Appending a new child collides with nobody
    const isCollision = (row) => ['OVERWRITE', 'REMOVE', 'CHILDREN'].includes(row.mode);
    const modeClass = { OVERWRITE: 'fb-destroy', REMOVE: 'fb-destroy', CHILDREN: 'fb-children', APPEND: 'fb-append' };
    const groupBys = { path: 'path_key', method: 'method_fqn', '': false };

    const table = new Tabulator('#firebaseTable', {
        ajaxURL: 'api/firebaseReport.php',
        // How many classes change and use each path: a path overwritten from several classes is where changes collide
        ajaxResponse: (url, params, response) => {
            const writers = new Map();
            const users = new Map();
            response.forEach(row => {
                const key = row.service_code + ' ' + row.path;
                if (!users.has(key)) users.set(key, new Set());
                users.get(key).add(classOf(row));
                if (!isCollision(row)) return;
                if (!writers.has(key)) writers.set(key, new Set());
                writers.get(key).add(classOf(row));
            });
            return response.map(row => {
                const key = row.service_code + ' ' + row.path;
                return { ...row, path_key: row.service + ': ' + row.path, writer_classes: writers.get(key)?.size ?? 0, user_classes: users.get(key)?.size ?? 0 };
            });
        },
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'path_key', dir: 'asc' }],
        groupBy: 'path_key',
        groupStartOpen: true,
        groupToggleElement: 'header',
        groupHeader: (value, count, data) => {
            const countOf = (mode) => data.filter(row => row.mode === mode).length;
            const reads = data.filter(row => !isWrite(row)).length;
            const writes = ['OVERWRITE', 'CHILDREN', 'APPEND', 'REMOVE'].map(mode => [mode, countOf(mode)]).filter(([, n]) => n > 0)
                .map(([mode, n]) => `${n} ${{ OVERWRITE: 'overwrite', CHILDREN: 'children', APPEND: 'append', REMOVE: 'remove' }[mode]}`).join(', ');
            const classes = new Set(data.map(classOf)).size;
            const writerClasses = new Set(data.filter(isCollision).map(classOf)).size;
            const verdict = writerClasses >= 2 ? `<span class="fb-many">changed from ${writerClasses} classes</span>` : '';
            return `${value} <span class="fb-muted">&mdash; ${count} uses from ${classes} class${classes === 1 ? '' : 'es'}: ${reads} reads${writes ? ', ' + writes : ''}</span> ${verdict}`;
        },
        columns: [
            anaCol.rowNumber(),
            {
                title: 'Firebase', columns: [
                    anaCol.list('Service', 'service', 'Realtime Database, Firestore or Storage.'),
                    anaCol.text('Path', 'path', 'The path as written in the code. {?} is a part only known at run time (a variable), / is the root. Firestore paths alternate collection and document (users/{?}/orders), Storage paths are object names.', {
                        formatter: (cell) => {
                            const box = document.createElement('span');
                            box.textContent = cell.getValue();
                            if (cell.getRow().getData().writer_classes >= 2) {
                                box.className = 'fb-many';
                                box.title = 'Changed from ' + cell.getRow().getData().writer_classes + ' different classes';
                            }
                            return box;
                        },
                        frozen: true,
                    }),
                    anaCol.list('Type', 'object_type', 'path (Realtime Database), collection or document (Firestore), object or folder (Storage).'),
                    anaCol.list('Operation', 'operation', 'The call: SET, UPDATE, PUSH, REMOVE, GET, GET_KEYS, ADD, CREATE, DELETE, UPLOAD, DOWNLOAD, LIST, COPY, RENAME and so on.'),
                    anaCol.list('Access', 'access', 'Read or Write.'),
                    anaCol.list('Effect', 'effect', 'What the call does to the path. Overwrites and deletes take everything under the path with them; changing children touches only the ones it names; appending adds a new child with a generated key. Reads give everything under the path, a query, only the names of the children, or only metadata.', {
                        formatter: (cell) => {
                            const box = document.createElement('span');
                            box.textContent = cell.getValue();
                            box.className = modeClass[cell.getRow().getData().mode] ?? 'fb-read';
                            return box;
                        },
                    }),
                    anaCol.list('Mode', 'mode', 'The effect as a code: OVERWRITE, CHILDREN, APPEND, REMOVE for writes; ALL, QUERY, KEYS, METADATA for reads.', { visible: false }),
                    anaCol.flag('Path has {?}', 'is_dynamic', 'Part of the path is built from a variable and is not known: users/{?} is any user. The call is still right.'),
                    anaCol.text('Code', 'snippet', 'The call the row was read from, up to 255 characters; {?} is a part only known at run time. Check it when a row looks wrong.', { width: 380 }),
                ]
            },
            {
                title: 'Across the project', columns: [
                    anaCol.num('Classes changing it', 'writer_classes', 'How many different classes overwrite, delete or change children of this path. Two or more is the risk: the classes can break each other. Appending is not counted.', {
                        formatter: (cell) => {
                            const box = document.createElement('span');
                            box.textContent = cell.getValue();
                            if (cell.getValue() >= 2) box.className = 'fb-many';
                            return box;
                        },
                    }),
                    anaCol.num('Classes using it', 'user_classes', 'How many different classes read or write this path. Functions count as one class per namespace.'),
                ]
            },
            {
                title: 'Used by', columns: [
                    anaCol.detail('Method', 'method_short_name', 'Method or function that makes the call. Click for the method detail page.', 'method', 'method_fqn'),
                    anaCol.detail('Namespace', 'namespace', 'Namespace of the class or function. Click it to open the namespace detail page.', 'namespace', 'namespace'),
                    anaCol.detail('Class', 'class_short_name', 'Class of the method. Click for the class detail page. Empty for functions.', 'class', 'class_name'),
                    anaCol.detail('Class FQN', 'class_name', 'Fully qualified class name. Click it to open the class detail page.', 'class', 'class_name'),
                    anaCol.detail('Method FQN', 'method_fqn', 'Fully qualified Class::method, or the function name. Click it to open the method detail page.', 'method', 'method_fqn'),
                    anaCol.list('Kind', 'kind', 'Method or Function.'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'firebase_report.csv');
    anaInitGuide(table);
    anaInitFind(table);
    anaInitLayout(table, 'firebase_report', [{ column: 'path_key', dir: 'asc' }]);
    document.getElementById('selGroup').addEventListener('change', (event) => table.setGroupBy(groupBys[event.target.value]));
    const byPath = [{ column: 'path_key', dir: 'asc' }];
    anaInitPresets(table, [
        { label: 'Writes', tip: 'Everything that changes Firebase data', filter: row => isWrite(row), sort: byPath },
        { label: 'Overwrites', tip: 'Replaces the path and everything under it: set() in the Realtime Database, set() without merge in Firestore, upload() in Storage', filter: row => row.mode === 'OVERWRITE', sort: byPath },
        { label: 'Deletes', tip: 'Deletes the path and everything under it: remove(), set(null), delete()', filter: row => row.mode === 'REMOVE', sort: byPath },
        { label: 'Changes children', tip: 'Changes only the children it names: update(), set() with merge', filter: row => row.mode === 'CHILDREN', sort: byPath },
        { label: 'Appends', tip: 'Adds a new child with a generated key: push(), add(), create()', filter: row => row.mode === 'APPEND', sort: byPath },
        { label: 'Changed from 2+ classes', tip: 'Paths overwritten, deleted or changed from two or more classes: the riskiest, any of them can break the others', filter: row => row.writer_classes >= 2 && isCollision(row), sort: [{ column: 'writer_classes', dir: 'desc' }, { column: 'path_key', dir: 'asc' }] },
        { label: 'Reads everything', tip: 'Reads the path and everything under it: getValue(), getSnapshot(), documents() of a whole collection, a download', filter: row => row.mode === 'ALL', sort: byPath },
        { label: 'Queries', tip: 'Reads some of the children: orderBy, limit, where', filter: row => row.mode === 'QUERY', sort: byPath },
        { label: 'Path has {?}', tip: 'Part of the path is built at run time: check these by hand', filter: row => row.is_dynamic, sort: [{ column: 'method_fqn', dir: 'asc' }] },
    ]);
</script>
</body>
</html>
