<?php
// maltese_cross.php and firebase_cross.php set the mode: the same matrix, fixed to the SQL tables or to the Firebase paths
// and the methods that read and write them
$dsmMode ??= 'matrix';
$crossLevel = ['maltese' => 'table', 'firebase' => 'firebase'][$dsmMode] ?? null;
$isCross = $crossLevel !== null;
$crossTitle = ['maltese' => 'Maltese Cross', 'firebase' => 'Firebase Cross'][$dsmMode] ?? 'Dependency Matrix';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $crossTitle ?></title>

    <link rel="stylesheet" href="css/ana.css">
    <script src="js/ana_graph.js"></script>

    <style>
        :root {
            /* Font colors, all above 4.5:1 on white. Red = act on it, amber = watch it, blue = normal, grey = minor */
            --dsm-bad: #b00020;
            --dsm-hot: #8a4b00;
            --dsm-heavy: #0a3d8f;
            --dsm-normal: #1f4e8c;
            --dsm-light: #5b6b80;
            --dsm-ok: #1b6b2f;
            --dsm-muted: #5c636a;
            --dsm-bad-bg: #fdecee;
            --dsm-diag-bg: #e9ecef;
            --dsm-line: #ced4da;
            --dsm-group-line: #6c757d;
            --dsm-hover: #e7f0ff;
            --dsm-cell: 30px;
        }
        body.dsm-page { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

        .dsm-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 22px; padding: 10px 20px; background: var(--panel-bg); border-bottom: 1px solid var(--border-color); flex-shrink: 0; font-size: 0.9rem; }
        .dsm-bar label { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; white-space: nowrap; }
        .dsm-bar select, .dsm-bar input[type=text] { padding: 4px 6px; font-size: 0.9rem; }
        .dsm-bar button { padding: 5px 12px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--panel-bg); cursor: pointer; font-size: 0.9rem; }
        .dsm-bar button:hover { background: var(--bg-color); }
        .dsm-bar label[hidden] { display: none; }
        body.dsm-cross { --dsm-cell: 38px; }
        .dsm-group { display: inline-flex; align-items: center; gap: 14px; }
        .dsm-group[hidden] { display: none; }
        .dsm-focus { flex: 1 1 520px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px 8px; }
        .dsm-focus input[type=text] { width: 300px; }
        .dsm-chip { display: inline-flex; align-items: center; gap: 6px; padding: 2px 4px 2px 10px; border: 1px solid var(--accent); border-radius: 12px; background: #eef4ff; color: #0a3d8f; font-weight: 600; }
        .dsm-chip button { border: 0; background: transparent; padding: 0 6px; font-size: 1rem; line-height: 1; color: #0a3d8f; }
        .dsm-chip.nomatch { border-color: var(--dsm-hot); background: #fff4e0; color: var(--dsm-hot); }

        .dsm-summary { display: flex; flex-wrap: wrap; gap: 4px 22px; padding: 6px 20px; background: var(--bg-color); border-bottom: 1px solid var(--border-color); font-size: 0.88rem; color: var(--dsm-muted); flex-shrink: 0; }
        .dsm-summary b { color: var(--text-main); }
        .dsm-summary .bad, .dsm-legend .bad { color: var(--dsm-bad); font-weight: 700; }
        .dsm-summary .hot, .dsm-legend .hot { color: var(--dsm-hot); font-weight: 700; }
        .dsm-summary .worst, .dsm-legend .worst, .dsm-detail .worst { color: var(--dsm-bad); font-weight: 800; background: var(--dsm-bad-bg); text-decoration: underline; padding: 0 4px; }
        .dsm-legend .ok { color: var(--dsm-ok); font-weight: 700; }
        table.dsm .L-O, table.dsm .L-X { color: var(--dsm-bad); font-weight: 800; }
        table.dsm .L-C { color: var(--dsm-hot); font-weight: 700; }
        table.dsm .L-A { color: var(--dsm-ok); font-weight: 700; }
        table.dsm .L-R { color: var(--dsm-normal); font-weight: 400; }
        .dsm-summary .ok { color: var(--dsm-ok); font-weight: 700; }
        .dsm-summary #hoverInfo { margin-left: auto; color: var(--text-main); }
        .dsm-legend { display: flex; flex-wrap: wrap; gap: 4px 20px; padding: 5px 20px; font-size: 0.85rem; color: var(--dsm-muted); border-bottom: 1px solid var(--border-color); background: var(--panel-bg); flex-shrink: 0; }
        .dsm-legend .heavy { color: var(--dsm-heavy); font-weight: 700; }
        .dsm-legend .normal { color: var(--dsm-normal); }
        .dsm-legend .light { color: var(--dsm-light); }

        .dsm-scroll { flex: 1 1 auto; overflow: auto; background: var(--panel-bg); min-height: 120px; position: relative; }
        .dsm-message { padding: 30px 24px; font-size: 1rem; color: var(--text-main); }
        .dsm-message button { margin-left: 10px; padding: 5px 12px; cursor: pointer; }

        table.dsm { border-collapse: separate; border-spacing: 0; font-size: 12px; }
        table.dsm td, table.dsm th { border-right: 1px solid var(--dsm-line); border-bottom: 1px solid var(--dsm-line); padding: 0; }
        table.dsm td { min-width: var(--dsm-cell); width: var(--dsm-cell); height: 24px; text-align: center; color: var(--dsm-normal); cursor: default; font-variant-numeric: tabular-nums; }
        table.dsm td.n { cursor: pointer; }
        table.dsm tbody tr:hover td { background: var(--dsm-hover); }
        table.dsm tbody tr:hover td.n.m, table.dsm tbody tr:hover td.n.x, table.dsm tbody tr:hover td.n.w { background: var(--dsm-bad-bg); }
        table.dsm td.l { color: var(--dsm-light); }
        table.dsm td.h { color: var(--dsm-heavy); font-weight: 700; }
        table.dsm td.c { color: var(--dsm-bad); }
        table.dsm td.m { color: var(--dsm-bad); font-weight: 800; background: var(--dsm-bad-bg); }
        table.dsm td.w { color: var(--dsm-bad); font-weight: 800; }
        table.dsm td.x { color: var(--dsm-bad); font-weight: 800; background: var(--dsm-bad-bg); text-decoration: underline; }
        table.dsm td.u { color: var(--dsm-hot); font-weight: 700; }
        table.dsm td.r { color: var(--dsm-normal); }
        table.dsm td.d { background: var(--dsm-diag-bg); color: var(--dsm-muted); }
        table.dsm td.sum { background: var(--bg-color); color: var(--text-main); font-weight: 600; min-width: 40px; }
        table.dsm td.sum.hot { color: var(--dsm-hot); font-weight: 800; }
        table.dsm td.gs, table.dsm th.gs { border-left: 2px solid var(--dsm-group-line); }
        table.dsm tr.gs td, table.dsm tr.gs th { border-top: 2px solid var(--dsm-group-line); }

        table.dsm th { background: #f1f3f5; color: var(--text-main); font-weight: 500; position: sticky; z-index: 2; }
        table.dsm thead th { top: 0; height: 190px; vertical-align: bottom; padding: 4px 0; }
        table.dsm thead th.col div { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; max-height: 182px; overflow: hidden; text-overflow: ellipsis; margin: 0 auto; }
        table.dsm tbody th.row { left: 0; text-align: left; white-space: nowrap; padding: 0 8px; min-width: 300px; max-width: 460px; overflow: hidden; text-overflow: ellipsis; cursor: pointer; z-index: 1; }
        table.dsm thead th.corner { left: 0; z-index: 4; text-align: left; padding: 6px 8px; vertical-align: bottom; color: var(--dsm-muted); font-weight: 400; white-space: normal; }
        table.dsm tfoot td, table.dsm tfoot th { position: sticky; bottom: 0; z-index: 2; background: var(--bg-color); font-weight: 600; border-top: 2px solid var(--dsm-group-line); }
        table.dsm tfoot th { left: 0; z-index: 3; text-align: left; padding: 0 8px; }
        table.dsm th.col { cursor: pointer; }
        table.dsm .idx { color: var(--dsm-muted); font-weight: 400; margin-right: 4px; }
        table.dsm .ctx { color: var(--dsm-muted); font-weight: 400; margin-left: 8px; font-size: 11px; }
        table.dsm th.bad, table.dsm th.bad .ctx { color: var(--dsm-bad); }
        table.dsm th.bad { font-weight: 800; }
        table.dsm th.worst, table.dsm th.worst .ctx { color: var(--dsm-bad); }
        table.dsm th.worst { font-weight: 800; background: var(--dsm-bad-bg); text-decoration: underline; }
        table.dsm th.hot, table.dsm th.hot .ctx { color: var(--dsm-hot); }
        table.dsm th.hot { font-weight: 800; }
        table.dsm th.hl { background: #ffe8a3; color: #000; }
        table.dsm th.hl .ctx, table.dsm th.hl .idx { color: #212529; }

        .dsm-detail { flex: 0 0 auto; max-height: 30vh; min-height: 56px; overflow: auto; padding: 8px 20px; background: var(--panel-bg); border-top: 2px solid var(--border-color); font-size: 0.9rem; }
        .dsm-detail h3 { margin: 0 0 6px; font-size: 0.98rem; }
        .dsm-detail .hint { color: var(--dsm-muted); }
        .dsm-detail table { border-collapse: collapse; font-size: 0.85rem; }
        .dsm-detail td, .dsm-detail th { padding: 2px 14px 2px 0; text-align: left; vertical-align: top; border-bottom: 1px solid #edf0f2; }
        .dsm-detail .bad { color: var(--dsm-bad); font-weight: 700; }
        .dsm-detail .hot { color: var(--dsm-hot); font-weight: 700; }
        .dsm-detail .lists { display: flex; gap: 40px; flex-wrap: wrap; }
        .dsm-detail button { padding: 3px 10px; margin-right: 8px; cursor: pointer; }
    </style>
</head>
<body class="ana-table-page dsm-page<?= $isCross ? ' dsm-cross' : '' ?>">
<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="dsm-bar">
    <label id="lblLevel">Level
        <select id="selLevel">
            <option value="namespace">Namespace</option>
            <option value="class" selected>Class</option>
            <option value="method">Method / function</option>
            <option value="property">Public &amp; global properties and variables</option>
            <option value="constant">Constants</option>
            <option value="table">Database tables and procedures</option>
            <option value="firebase">Firebase paths</option>
        </select>
    </label>

    <span class="dsm-group" id="grpDependencies">
        <label title="Method calls rolled up to the level"><input type="checkbox" id="chkCalls" checked> Calls</label>
        <label title="extends, implements and trait use"><input type="checkbox" id="chkInherit" checked> Inheritance</label>
        <label title="Classes named in parameter and return types"><input type="checkbox" id="chkTypeHint" checked> Type hints</label>
        <label title="Classes and methods the analysis did not see: vendor code, PHP classes"><input type="checkbox" id="chkExternal"> External</label>
    </span>

    <span class="dsm-group" id="grpState" hidden>
        <label id="lblAccess" title="Write also shows the cells that read and write the table (RW)">Access
            <select id="selAccess">
                <option value="all">All</option>
            </select>
        </label>
        <label>Used by
            <select id="selUsedBy">
                <option value="namespace">Namespaces</option>
                <option value="class" selected>Classes</option>
                <option value="method">Methods</option>
            </select>
        </label>
        <label title="Show protected and private members too"><input type="checkbox" id="chkNonPublic"> Non-public</label>
        <label title="Superglobals and constants defined by PHP"><input type="checkbox" id="chkNative"> PHP built-ins</label>
        <label title="Leave out what only one class uses: no dependency between classes"><input type="checkbox" id="chkShared"> Only used by 2+ classes</label>
    </span>

    <label>Order
        <select id="selOrder">
            <option value="name">Name</option>
            <option value="layered">Layers (cycles in blocks)</option>
            <option value="coupling">Most coupled first</option>
        </select>
    </label>
    <label title="Leave out rows and columns with nothing in them"><input type="checkbox" id="chkHideEmpty" checked> Hide empty</label>
    <button id="btnCsv" title="Download the matrix as shown">CSV</button>
</div>

<div class="dsm-bar">
    <div class="dsm-focus">
        <label>Focus
            <input type="text" id="focusInput" list="focusOptions" placeholder="namespace, class, method or symbol, Enter to add" autocomplete="off">
        </label>
        <datalist id="focusOptions"></datalist>
        <button id="btnFocusAdd">Add</button>
        <span id="chips"></span>
        <label>Focus is the
            <select id="selSide"></select>
        </label>
        <button id="btnFocusClear">Clear focus</button>
    </div>
</div>

<div class="dsm-summary" id="summary">Loading the analysis…</div>
<div class="dsm-legend" id="legend"></div>
<div class="dsm-scroll" id="scroll"></div>
<div class="dsm-detail" id="detail"><span class="hint">Click a cell for the evidence behind it, or a row or column name for what it uses and what uses it.</span></div>

<script>
    const CROSS = <?= json_encode($isCross) ?>;
    const CROSS_LEVEL = <?= json_encode($crossLevel) ?>;
    // Levels where each use is its own letter in the cell, and the Access filter works
    const LETTER_LEVELS = ['table', 'firebase'];
    const MAX_CELLS = 250000;
    const SQUARE_LEVELS = ['namespace', 'class', 'method'];
    const RELATION_KIND = { EXTENDS: 'inherit', IMPLEMENTS: 'inherit', USES: 'inherit', TYPE_HINT: 'typehint' };
    const KIND_LABEL = { call: 'calls', extends: 'extends', implements: 'implements', uses: 'uses trait', type_hint: 'type hint', state: 'state' };

    let data = null;            // the three API answers
    let model = null;           // the full matrix of the current level, before any focus
    let modelKey = '';
    let view = null;            // what is on screen: rows, cols, order
    let focus = [];             // names typed by the user
    let forceRender = false;

    const $ = (id) => document.getElementById(id);
    const isSquare = (level) => SQUARE_LEVELS.includes(level);
    const escapeHtml = (text) => String(text).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const getOrSet = (map, key, make) => { if (!map.has(key)) map.set(key, make()); return map.get(key); };
    const percentile = (values, share) => {
        const sorted = [...values].sort((a, b) => a - b);
        return sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor(share * sorted.length))] : 0;
    };
    const shortName = (name) => name.substring(name.lastIndexOf('\\') + 1);
    const methodId = (cls, method) => cls === '' ? method : cls + '::' + method;
    // Functions have no class: one pseudo class per namespace keeps them from piling up in a single node
    const functionClassOf = (name) => { const ns = anaNamespaceOf(name); return ns === '[Global]' ? '{functions}' : ns + '\\{functions}'; };

    // ---------- Elements: what a row or a column is at a level ----------

    // cls is the raw class name ('' for a function), method the id from methodId()
    function describe(level, cls, method) {
        const classId = cls !== '' ? cls : functionClassOf(method);
        const ns = anaNamespaceOf(classId);
        if (level === 'namespace') return { id: ns, name: ns, context: '', ns, cls: '' };
        if (level === 'class') {
            const kind = data.classKinds.get(classId);
            return { id: classId, name: shortName(classId), context: ns + (kind && kind !== 'class' ? ' · ' + kind : ''), ns, cls: classId };
        }
        const isFunction = cls === '';
        return { id: method, name: (isFunction ? shortName(method) : shortName(cls) + '::' + method.substring(cls.length + 2)) + '()', context: ns, ns, cls: classId };
    }

    function addCell(m, rowId, colId, kind, weight, evidence) {
        const cell = getOrSet(getOrSet(m.cells, rowId, () => new Map()), colId, () => ({ n: 0, by: {}, items: [], write: false }));
        cell.n += weight;
        cell.by[kind] = (cell.by[kind] ?? 0) + weight;
        cell.items.push(evidence);
        getOrSet(m.byCol, colId, () => new Map()).set(rowId, cell);
        return cell;
    }

    const newModel = (square) => ({ square, rowEls: new Map(), colEls: new Map(), cells: new Map(), byCol: new Map(), diag: new Map(), rowMeta: new Map() });

    // ---------- Square matrix: namespace, class, method. Cell = row depends on column ----------

    function buildSquare(level, options) {
        const m = newModel(true);
        const element = (cls, method) => {
            const el = describe(level, cls, method);
            return getOrSet(m.rowEls, el.id, () => el);
        };
        const addDependency = (from, to, kind, weight, evidence) => {
            if (from.id === to.id) {
                m.diag.set(from.id, (m.diag.get(from.id) ?? 0) + weight);
                return;
            }
            addCell(m, from.id, to.id, kind, weight, evidence);
        };
        // A method inherited from a parent is not in the methods table, but its class is analyzed
        const isAnalyzed = (cls, method) => options.external || data.methodIds.has(method) || data.classKinds.has(cls);

        // The empty rows and columns: everything the analysis found
        if (level !== 'method') data.classes.forEach(c => element(c.class_name, c.class_name + '::'));
        data.methods.forEach(row => element(row.class_name, methodId(row.class_name, row.method_name)));

        if (options.calls) {
            data.calls.forEach(call => {
                if (call.is_builtin === 1 || call.from_method === '' || call.to_method === '') return;
                const fromId = methodId(call.from_class, call.from_method);
                const toId = methodId(call.to_class, call.to_method);
                if (!isAnalyzed(call.from_class, fromId) || !isAnalyzed(call.to_class, toId)) return;
                addDependency(element(call.from_class, fromId), element(call.to_class, toId), 'call', call.weight,
                    { kind: 'call', from: fromId, to: toId, weight: call.weight });
            });
        }

        if (level !== 'method') {
            data.relations.forEach(relation => {
                if (!options[RELATION_KIND[relation.relation_type]]) return;
                if (!options.external && !data.classKinds.has(relation.target_class)) return;
                addDependency(element(relation.source_class, relation.source_class + '::'), element(relation.target_class, relation.target_class + '::'),
                    RELATION_KIND[relation.relation_type], 1,
                    { kind: relation.relation_type.toLowerCase(), from: relation.source_class, to: relation.target_class, weight: 1 });
            });
        }

        m.colEls = m.rowEls;
        return m;
    }

    // ---------- Rectangular matrix: state or constants (rows) used by namespaces, classes or methods (columns) ----------

    const isConstantSymbol = (s) => s.symbol_type === 'Constant' || s.symbol_type === 'Class constant' || (s.symbol_type === 'Inherited member' && s.is_declared_constant);
    // One id for a member whether it was reached from its own class or inherited
    const symbolId = (s) => {
        if (!s.declared_in) return s.symbol_fqn;
        return s.declared_in + '::' + (s.is_declared_constant ? s.symbol : '$' + s.symbol.replace(/^\$/, ''));
    };

    // A SQL use in the shape of a state use, so tables and procedures go through the same matrix
    const sqlAsSymbol = (row) => ({
        symbol_type: row.object_type === 'procedure' ? 'Procedure' : 'Table',
        symbol: row.object_name,
        symbol_fqn: row.object_name,
        access: row.access,
        operation: row.operation,
        snippet: row.snippet,
        class_name: row.class_name,
        method_fqn: row.method_fqn,
    });
    // A Firebase use in the shape of a state use. The letter says what the call does to the path:
    // O overwrites it and everything under it, X deletes it, C changes only the children it names, A appends a new child, R reads
    const MODE_LETTER = { OVERWRITE: 'O', CHILDREN: 'C', APPEND: 'A', REMOVE: 'X' };
    const firebaseAsSymbol = (row) => ({
        symbol_type: row.service,
        symbol: row.path,
        symbol_fqn: row.service_code + ':' + row.path,
        label: row.path,
        context: row.service + ' · ' + row.object_type,
        access: row.access,
        letters: row.access === 'Write' ? ['W', MODE_LETTER[row.mode]] : ['R'],
        operation: row.operation + ' · ' + row.effect,
        snippet: row.snippet,
        class_name: row.class_name,
        method_fqn: row.method_fqn,
        // Appending a child collides with nobody; the rest can break what another class wrote
        collides: ['OVERWRITE', 'REMOVE', 'CHILDREN'].includes(row.mode),
        unknownRow: row.path === '{?}',
    });
    // What belongs to no class: its rows are not filed under the class of the first one that uses it
    const OWNERLESS_TYPES = ['Global variable', 'Superglobal variable', 'Constant', 'Table', 'Procedure', 'Realtime Database', 'Firestore', 'Storage'];
    const ACCESS_LETTERS = { Read: ['R'], Write: ['W'], 'Read/Write': ['R', 'W'], Call: ['C'], DDL: ['D'] };
    const LETTER_ORDER = { table: 'RWDC', firebase: 'ROCAX' };
    const ACCESS_FILTER_LETTER = { read: 'R', write: 'W', call: 'C', ddl: 'D', overwrite: 'O', children: 'C', append: 'A', remove: 'X' };
    const ACCESS_FILTERS = {
        table: [['all', 'All'], ['read', 'Read (R and RW)'], ['write', 'Write (W and RW)'], ['call', 'Call (C)'], ['ddl', 'DDL (D)']],
        firebase: [['all', 'All'], ['read', 'Read (R)'], ['write', 'Write (O, C, A, X)'], ['overwrite', 'Overwrite (O)'], ['children', 'Children (C)'], ['append', 'Append (A)'], ['remove', 'Remove (X)']],
    };

    function buildRect(level, options) {
        const m = newModel(false);
        m.level = level;
        const wantConstants = level === 'constant';
        const symbols = level === 'table' ? data.sql.map(sqlAsSymbol)
            : (level === 'firebase' ? data.firebase.map(firebaseAsSymbol) : data.state.filter(s => isConstantSymbol(s) === wantConstants));
        symbols.forEach(s => {
            if (s.is_native && !options.native) return;
            if (s.visibility && s.visibility !== 'public' && !options.nonPublic) return;

            const id = symbolId(s);
            const declaredClass = s.declared_in || (OWNERLESS_TYPES.includes(s.symbol_type) ? '' : s.class_name);
            const row = getOrSet(m.rowEls, id, () => ({
                id,
                name: s.label ?? (id.includes('::') ? id.substring(id.indexOf('::') + 2) : id),
                context: s.context ?? ((declaredClass ? declaredClass + ' · ' : '') + s.symbol_type),
                ns: declaredClass ? anaNamespaceOf(declaredClass) : '[Global]',
                cls: declaredClass,
            }));
            const consumer = describe(options.usedBy, s.class_name, s.method_fqn);
            getOrSet(m.colEls, consumer.id, () => consumer);

            const meta = getOrSet(m.rowMeta, id, () => ({ classes: new Set(), written: false, writers: new Set(), unknown: s.unknownRow ?? id.includes('{?}') }));
            const userClass = s.class_name !== '' ? s.class_name : functionClassOf(s.method_fqn);
            meta.classes.add(userClass);
            const isWrite = ['Write', 'Read/Write', 'DDL'].includes(s.access);
            meta.written ||= isWrite;
            if (isWrite && (s.collides ?? true)) meta.writers.add(userClass);

            const cell = addCell(m, id, consumer.id, 'state', 0, { kind: 'state', who: s.method_fqn, access: s.operation ?? s.access, snippet: s.snippet ?? '' });
            cell.methods ??= new Set();
            cell.letters ??= new Set();
            (s.letters ?? ACCESS_LETTERS[s.access] ?? []).forEach(letter => cell.letters.add(letter));
            cell.call ||= s.access === 'Call';
            cell.methods.add(s.method_fqn);
            cell.n = cell.methods.size;
            cell.write ||= isWrite;
        });

        if (options.shared) {
            m.rowMeta.forEach((meta, id) => {
                if (meta.classes.size >= 2) return;
                m.cells.get(id).forEach((cell, colId) => m.byCol.get(colId).delete(id));
                m.cells.delete(id);
                m.rowEls.delete(id);
            });
        }

        // Filtering by write keeps the cells that read and write (RW) too: a cell stays whole or goes
        if (options.access !== 'all') {
            const letter = ACCESS_FILTER_LETTER[options.access];
            m.cells.forEach((cols, rowId) => cols.forEach((cell, colId) => {
                if (cell.letters.has(letter)) return;
                cols.delete(colId);
                m.byCol.get(colId).delete(rowId);
            }));
        }
        return m;
    }

    // ---------- Analysis of the full matrix: cycles, hubs, layers ----------

    function analyze(m) {
        const a = { compOf: new Map(), cycleCount: 0, mutualPairs: 0, fanIn: new Map(), fanOut: new Map(), hubFloor: 3, rank: new Map(), written: 0, severe: 0 };
        m.cells.forEach((cols, rowId) => a.fanOut.set(rowId, cols.size));
        m.byCol.forEach((rows, colId) => a.fanIn.set(colId, rows.size));
        a.hubFloor = Math.max(3, percentile([...a.fanIn.values()], 0.9));

        if (!m.square) {
            m.rowMeta.forEach(meta => {
                if (meta.unknown) return;
                if (meta.writers.size >= 2) a.severe++;
                else if (meta.written && meta.classes.size >= 2) a.written++;
            });
            return a;
        }

        const pairs = [];
        m.cells.forEach((cols, rowId) => cols.forEach((cell, colId) => {
            pairs.push([rowId, colId]);
            if (rowId < colId && m.cells.get(colId)?.has(rowId)) a.mutualPairs++;
        }));
        a.compOf = anaCycleNodes(pairs);
        a.cycleCount = new Set(a.compOf.values()).size;

        // Layers: a component sits one layer below everything that depends on it, so dependencies end up under their dependents
        const componentOf = (id) => a.compOf.has(id) ? 'c' + a.compOf.get(id) : 'n' + id;
        const out = new Map();
        const incoming = new Map();
        m.rowEls.forEach((el, id) => { const key = componentOf(id); getOrSet(out, key, () => new Set()); incoming.set(key, incoming.get(key) ?? 0); });
        pairs.forEach(([rowId, colId]) => {
            const from = componentOf(rowId);
            const to = componentOf(colId);
            if (from === to || out.get(from).has(to)) return;
            out.get(from).add(to);
            incoming.set(to, incoming.get(to) + 1);
        });
        const layerOf = new Map();
        const queue = [...incoming].filter(([, count]) => count === 0).map(([key]) => key);
        queue.forEach(key => layerOf.set(key, 0));
        while (queue.length > 0) {
            const key = queue.shift();
            out.get(key).forEach(next => {
                layerOf.set(next, Math.max(layerOf.get(next) ?? 0, layerOf.get(key) + 1));
                incoming.set(next, incoming.get(next) - 1);
                if (incoming.get(next) === 0) queue.push(next);
            });
        }
        m.rowEls.forEach((el, id) => a.rank.set(id, { layer: layerOf.get(componentOf(id)) ?? 0, component: componentOf(id) }));
        return a;
    }

    // ---------- Deciders: what deserves attention. Pure: code drives the CSS class, reason the tooltip ----------

    function decideCell(m, a, rowId, colId, cell, heavyFloor) {
        if (!m.square) {
            if (cell.write && m.rowMeta.get(rowId)?.unknown) return { code: 'u', reason: 'Written to a table that is not known: the name is built at run time' };
            if (cell.write) {
                const writers = m.rowMeta.get(rowId)?.writers.size ?? 0;
                if (writers >= 2) return { code: 'x', reason: `Written from ${writers} different classes: they can break each other` };
                const shared = (m.rowMeta.get(rowId)?.classes.size ?? 0) >= 2;
                return shared
                    ? { code: 'w', reason: 'Written, and used from more than one class: hidden coupling' }
                    : { code: 'u', reason: 'Written, but only one class uses it' };
            }
            if (cell.call) return { code: 'u', reason: 'Calls a stored procedure: it can change anything' };
            return cell.n >= heavyFloor ? { code: 'h', reason: 'Read by many methods' } : { code: 'r', reason: 'Read' };
        }
        if (m.cells.get(colId)?.has(rowId)) return { code: 'm', reason: 'Mutual dependency: each one depends on the other' };
        if (a.compOf.has(rowId) && a.compOf.get(rowId) === a.compOf.get(colId)) return { code: 'c', reason: 'Part of a dependency cycle' };
        if (cell.n >= heavyFloor) return { code: 'h', reason: 'Heavy dependency' };
        return { code: cell.n === 1 ? 'l' : '', reason: '' };
    }

    function decideHeader(m, a, side, id) {
        if (m.square && a.compOf.has(id)) return { code: 'bad', reason: 'In a dependency cycle' };
        const fanIn = a.fanIn.get(id) ?? 0;
        if (m.square) return fanIn >= a.hubFloor ? { code: 'hot', reason: `Depended on by ${fanIn}: a change here spreads` } : { code: '', reason: '' };
        if (side === 'rows') {
            const meta = m.rowMeta.get(id);
            if (meta.unknown) return { code: 'hot', reason: 'Table not known: the name is built at run time, so these are not one table' };
            if (meta.writers.size >= 2) return { code: 'worst', reason: `Written from ${meta.writers.size} different classes: they can break each other` };
            if (meta.classes.size < 2) return { code: '', reason: 'Used from one class only' };
            return meta.written
                ? { code: 'bad', reason: `Written, and used from ${meta.classes.size} classes: hidden coupling` }
                : { code: 'hot', reason: `Read from ${meta.classes.size} classes` };
        }
        const symbols = m.byCol.get(id)?.size ?? 0;
        return symbols >= a.hubFloor ? { code: 'hot', reason: `Uses ${symbols} of these` } : { code: '', reason: '' };
    }

    // ---------- Focus: one dimension is limited to the focus, the other to what interacts with it ----------

    const matchesFocus = (el, names) => names.some(name => name === el.id || name === el.cls || el.ns === name || el.ns.startsWith(name + '\\'));

    function selectVisible(m, names, side, hideEmpty) {
        const hasDeps = (id) => (m.cells.get(id)?.size ?? 0) + (m.byCol.get(id)?.size ?? 0) > 0;
        const hasRowDeps = (id) => (m.cells.get(id)?.size ?? 0) > 0;
        const hasColDeps = (id) => (m.byCol.get(id)?.size ?? 0) > 0;
        const rows = [...m.rowEls.values()];
        const cols = [...m.colEls.values()];

        if (names.length === 0) {
            return m.square
                ? { rows: hideEmpty ? rows.filter(el => hasDeps(el.id)) : rows, cols: hideEmpty ? cols.filter(el => hasDeps(el.id)) : cols }
                : { rows: hideEmpty ? rows.filter(el => hasRowDeps(el.id)) : rows, cols: hideEmpty ? cols.filter(el => hasColDeps(el.id)) : cols };
        }

        if (side === 'rows') {
            const focusRows = rows.filter(el => matchesFocus(el, names) && (!hideEmpty || hasRowDeps(el.id)));
            const wanted = new Set();
            focusRows.forEach(el => m.cells.get(el.id)?.forEach((cell, colId) => wanted.add(colId)));
            return { rows: focusRows, cols: cols.filter(el => wanted.has(el.id)) };
        }
        const focusCols = cols.filter(el => matchesFocus(el, names) && (!hideEmpty || hasColDeps(el.id)));
        const wanted = new Set();
        focusCols.forEach(el => m.byCol.get(el.id)?.forEach((cell, rowId) => wanted.add(rowId)));
        return { rows: rows.filter(el => wanted.has(el.id)), cols: focusCols };
    }

    // ---------- Order ----------

    function sortElements(list, order, m, a, side) {
        const byName = (x, y) => x.ns.localeCompare(y.ns) || x.cls.localeCompare(y.cls) || x.id.localeCompare(y.id);
        const sorted = [...list];
        if (order === 'layered' && m.square) {
            return sorted.sort((x, y) => a.rank.get(x.id).layer - a.rank.get(y.id).layer
                || a.rank.get(x.id).component.localeCompare(a.rank.get(y.id).component) || byName(x, y));
        }
        if (order === 'coupling') {
            const weight = (el) => m.square
                ? (a.fanIn.get(el.id) ?? 0) + (a.fanOut.get(el.id) ?? 0)
                : (side === 'rows' ? (a.fanOut.get(el.id) ?? 0) : (a.fanIn.get(el.id) ?? 0));
            return sorted.sort((x, y) => weight(y) - weight(x) || byName(x, y));
        }
        return sorted.sort(byName);
    }

    // ---------- Render ----------

    const cellText = (m, cell) => {
        if (m.square) return cell.n > 999 ? '1k+' : String(cell.n);
        const order = LETTER_ORDER[m.level] ?? 'RWDC';
        // The W of a Firebase use only serves the filter: its letter says what it does
        const letters = [...cell.letters].filter(letter => (CROSS || letter !== 'R') && !(m.level === 'firebase' && letter === 'W'))
            .sort((x, y) => order.indexOf(x) - order.indexOf(y)).join('');
        return CROSS ? letters + (cell.n > 1 ? '×' + cell.n : '') : cell.n + letters;
    };
    // The same for the page: a Firebase cell colors each letter by what it does
    const cellHtml = (m, cell) => m.level === 'firebase'
        ? cellText(m, cell).replace(/[ROCAX]/g, letter => `<span class="L-${letter}">${letter}</span>`)
        : escapeHtml(cellText(m, cell));

    function render() {
        const level = $('selLevel').value;
        const side = $('selSide').value;
        const order = $('selOrder').value;
        const square = isSquare(level);
        const a = model.analysis;

        const visible = selectVisible(model, focus, side, $('chkHideEmpty').checked);
        const rows = sortElements(visible.rows, order, model, a, 'rows');
        const cols = sortElements(visible.cols, order, model, a, 'cols');
        view = { rows, cols, level, side };
        updateChips();
        updateSuggestions();

        if (rows.length * cols.length > MAX_CELLS && !forceRender) {
            $('scroll').innerHTML = `<div class="dsm-message">${rows.length} rows × ${cols.length} columns is ${(rows.length * cols.length).toLocaleString()} cells. Narrow it with a focus or Hide empty, or
                <button id="btnForce">Draw it anyway</button></div>`;
            $('btnForce').addEventListener('click', () => { forceRender = true; render(); });
            renderSummary(0, 0);
            return;
        }
        if (rows.length === 0 || cols.length === 0) {
            $('scroll').innerHTML = `<div class="dsm-message">Nothing to show. ${focus.length ? 'The focus matches no row here, or what it matches has no dependencies. Check the level and the side of the focus.' : (LETTER_LEVELS.includes(level) && ({ table: data.sql, firebase: data.firebase }[level]).length === 0 ? 'Nothing was recorded for this. Run the analysis again.' : 'The analysis has no dependencies at this level.')}</div>`;
            renderSummary(0, 0);
            return;
        }

        // The heavy line for this view: the top tenth of the cells on screen, never below 3
        const weights = [];
        rows.forEach(row => cols.forEach(col => { const cell = model.cells.get(row.id)?.get(col.id); if (cell) weights.push(cell.n); }));
        const heavyFloor = Math.max(3, percentile(weights, 0.9));

        const colSums = new Map(cols.map(col => [col.id, 0]));
        let html = '<table class="dsm"><thead><tr><th class="corner">Read across: the row ' + (square ? 'depends on' : 'is used by') + ' the column<br>' + escapeHtml(headerCaption(level)) + '</th>';
        cols.forEach((col, i) => {
            const flag = decideHeader(model, a, 'cols', col.id);
            const previous = cols[i - 1];
            const group = level !== 'namespace' && previous && previous.ns !== col.ns;
            html += `<th class="col ${flag.code}${group ? ' gs' : ''}" data-side="cols" data-i="${i}" title="${escapeHtml(col.id + (col.context ? ' · ' + col.context : '') + (flag.reason ? '\n' + flag.reason : ''))}"><div><span class="idx">${i + 1}</span>${escapeHtml(col.name)}</div></th>`;
        });
        html += '<th class="col">Σ</th></tr></thead><tbody>';

        rows.forEach((row, r) => {
            const flag = decideHeader(model, a, 'rows', row.id);
            const previous = rows[r - 1];
            const group = level !== 'namespace' && previous && previous.ns !== row.ns;
            const rowCells = model.cells.get(row.id);
            let rowSum = 0;
            let rowHtml = '';
            cols.forEach((col, c) => {
                const cell = rowCells?.get(col.id);
                const groupCol = level !== 'namespace' && cols[c - 1] && cols[c - 1].ns !== col.ns;
                const gs = groupCol ? ' gs' : '';
                if (square && row.id === col.id) {
                    rowHtml += `<td class="d${gs}">${model.diag.get(row.id) ?? ''}</td>`;
                    return;
                }
                if (!cell) {
                    rowHtml += `<td class="${gs.trim()}"></td>`;
                    return;
                }
                const verdict = decideCell(model, a, row.id, col.id, cell, heavyFloor);
                rowSum += cell.n;
                colSums.set(col.id, colSums.get(col.id) + cell.n);
                rowHtml += `<td class="n ${verdict.code}${gs}">${cellHtml(model, cell)}</td>`;
            });
            html += `<tr class="${group ? 'gs' : ''}"><th class="row ${flag.code}" data-side="rows" data-i="${r}" title="${escapeHtml(row.id + (row.context ? ' · ' + row.context : '') + (flag.reason ? '\n' + flag.reason : ''))}"><span class="idx">${r + 1}</span>${escapeHtml(row.name)}<span class="ctx">${escapeHtml(row.context)}</span></th>${rowHtml}<td class="sum">${rowSum}</td></tr>`;
        });

        html += '</tbody><tfoot><tr><th>Σ</th>';
        let total = 0;
        cols.forEach(col => {
            const sum = colSums.get(col.id);
            total += sum;
            html += `<td class="sum${model.square && (a.fanIn.get(col.id) ?? 0) >= a.hubFloor ? ' hot' : ''}">${sum}</td>`;
        });
        html += `<td class="sum">${total}</td></tr></tfoot></table>`;

        $('scroll').innerHTML = html;
        renderSummary(rows.length, cols.length, total);
        saveUrl();
    }

    const headerCaption = (level) => ({
        namespace: 'Namespaces', class: 'Classes', method: 'Methods and functions',
        property: 'Properties and variables (rows) · ' + $('selUsedBy').selectedOptions[0].text.toLowerCase() + ' using them (columns)',
        constant: 'Constants (rows) · ' + $('selUsedBy').selectedOptions[0].text.toLowerCase() + ' using them (columns)',
        firebase: 'Firebase paths (rows) · ' + $('selUsedBy').selectedOptions[0].text.toLowerCase() + ' using them (columns)',
        table: 'Tables and procedures (rows) · ' + $('selUsedBy').selectedOptions[0].text.toLowerCase() + ' using them (columns)',
    }[level]);

    function renderSummary(rowCount, colCount, total = 0) {
        const a = model.analysis;
        const parts = [`<span><b>${rowCount}</b> rows × <b>${colCount}</b> columns</span>`];
        if (model.square) {
            parts.push(a.cycleCount > 0
                ? `<span class="bad">${a.cycleCount} dependency cycle${a.cycleCount === 1 ? '' : 's'}, ${a.compOf.size} elements involved</span>`
                : '<span class="ok">No dependency cycles</span>');
            parts.push(a.mutualPairs > 0 ? `<span class="bad">${a.mutualPairs} mutual pair${a.mutualPairs === 1 ? '' : 's'}</span>` : '<span class="ok">No mutual pairs</span>');
            const hubs = [...a.fanIn.values()].filter(n => n >= a.hubFloor).length;
            if (hubs > 0) parts.push(`<span class="hot">${hubs} most depended on (${a.hubFloor}+ dependents)</span>`);
        } else {
            if (a.severe > 0) parts.push(`<span class="worst">${a.severe} written from 2+ classes</span>`);
            if (a.written > 0) parts.push(`<span class="bad">${a.written} with one writer, used from 2+ classes</span>`);
            if (a.written + a.severe === 0) parts.push('<span class="ok">Nothing written and shared between classes</span>');
        }
        const unmatched = focus.filter(name => ![...model.rowEls.values(), ...model.colEls.values()].some(el => matchesFocus(el, [name])));
        if (unmatched.length > 0) parts.push(`<span class="hot">No match at this level: ${escapeHtml(unmatched.join(', '))}</span>`);
        parts.push('<span id="hoverInfo"></span>');
        $('summary').innerHTML = parts.join('');
    }

    function renderLegend(square) {
        if (CROSS_LEVEL === 'firebase') {
            $('legend').innerHTML = '<span class="worst">pink: several classes overwrite, delete or change it</span><span class="bad">O: overwrites the path and everything under it</span><span class="bad">X: deletes it and everything under it</span><span class="hot">C: changes only the children it names</span><span class="ok">A: appends a new child</span><span class="normal">R: reads it</span><span class="hot">amber {?}: path not known</span>';
            return;
        }
        if (CROSS) {
            $('legend').innerHTML = '<span class="worst">pink: several classes write the table</span><span class="bad">W: writes it, RW: reads it and writes it</span><span class="normal">R: only reads it</span><span class="bad" style="font-weight:400">D: DDL</span><span class="hot">C: calls a procedure</span><span class="hot">amber {?}: table not known, the name is built at run time</span>';
            return;
        }
        $('legend').innerHTML = square
            ? '<span class="bad">red bold on pink: mutual dependency</span><span class="bad" style="font-weight:400">red: same cycle</span><span class="heavy">blue bold: heavy</span><span class="normal">blue: dependency</span><span class="light">grey-blue: a single one</span><span>grey cell: inside itself</span><span class="bad">red name: in a cycle</span><span class="hot">amber name: many depend on it</span>'
            : '<span class="worst">pink W: several classes write it</span><span class="bad">red W: written, shared by 2+ classes</span><span class="hot">amber W: written, one class only</span><span class="bad" style="font-weight:400">D: DDL, counts as a write</span><span class="hot">amber C: calls a procedure</span><span class="heavy">blue bold: read by many methods</span><span class="normal">blue: read</span><span class="worst">pink name: several writers</span><span class="bad">red name: written and shared</span><span class="hot">amber name: read from 2+ classes</span>';
    }

    // ---------- Detail: evidence of a cell, neighbours of a header ----------

    const evidenceLine = (item) => item.kind === 'state'
        ? `<tr><td>${escapeHtml(item.access)}</td><td>${escapeHtml(item.who)}</td><td class="hint">${escapeHtml(item.snippet ?? '')}</td></tr>`
        : `<tr><td>${escapeHtml(KIND_LABEL[item.kind] ?? item.kind)}</td><td>${escapeHtml(item.from)}</td><td>→ ${escapeHtml(item.to)}</td><td>${item.weight > 1 ? '×' + item.weight : ''}</td></tr>`;

    function showCell(r, c) {
        const row = view.rows[r];
        const col = view.cols[c];
        const cell = model.cells.get(row.id)?.get(col.id);
        if (!cell) return;
        const kinds = Object.entries(cell.by).filter(([, n]) => n > 0).map(([kind, n]) => `${n} ${KIND_LABEL[kind] ?? kind}`).join(', ');
        const mutual = model.square && model.cells.get(col.id)?.has(row.id);
        $('detail').innerHTML = `<h3>${escapeHtml(row.id)} → ${escapeHtml(col.id)}${mutual ? ' <span class="bad">(and back: mutual dependency)</span>' : ''}</h3>
            <div>${model.square ? kinds : cell.n + ' method' + (cell.n === 1 ? '' : 's') + (cell.write ? ', <span class="bad">writes</span>' : ', reads only')}</div>
            <table>${cell.items.slice(0, 300).map(evidenceLine).join('')}</table>${cell.items.length > 300 ? `<div class="hint">…and ${cell.items.length - 300} more</div>` : ''}`;
    }

    function neighbourList(title, entries, nameOf) {
        const top = [...entries].sort((x, y) => y[1].n - x[1].n).slice(0, 15);
        return `<div><b>${title} (${entries.size})</b><table>${top.map(([id, cell]) => `<tr><td>${escapeHtml(nameOf(id))}</td><td>${cell.n}${cell.write ? 'W' : ''}</td></tr>`).join('')}</table>${entries.size > 15 ? `<div class="hint">…and ${entries.size - 15} more</div>` : ''}</div>`;
    }

    function showHeader(headerSide, i) {
        const el = (headerSide === 'rows' ? view.rows : view.cols)[i];
        const outgoing = headerSide === 'rows' || model.square ? (model.cells.get(el.id) ?? new Map()) : new Map();
        const incoming = headerSide === 'cols' || model.square ? (model.byCol.get(el.id) ?? new Map()) : new Map();
        const flag = decideHeader(model, model.analysis, headerSide, el.id);
        const lists = model.square
            ? neighbourList('Depends on', outgoing, id => id) + neighbourList('Used by', incoming, id => id)
            : (headerSide === 'rows' ? neighbourList('Used by', outgoing, id => id) : neighbourList('Uses', incoming, id => id));
        $('detail').innerHTML = `<h3>${escapeHtml(el.id)} ${flag.reason ? `<span class="${flag.code}">${escapeHtml(flag.reason)}</span>` : ''}</h3>
            <div><button id="btnUseFocus">Focus on this</button><span class="hint">${escapeHtml(el.context)}</span></div>
            <div class="lists">${lists}</div>`;
        $('btnUseFocus').addEventListener('click', () => {
            $('selSide').value = headerSide;
            addFocus(el.id);
        });
    }

    // ---------- Controls ----------

    const sideLabels = (level) => isSquare(level)
        ? { rows: 'dependent (rows): shows what it uses', cols: 'dependency (columns): shows who uses it' }
        : { rows: 'symbol (rows): shows who uses it', cols: 'user (columns): shows what it uses' };

    function syncControls() {
        const level = $('selLevel').value;
        const square = isSquare(level);
        $('grpDependencies').hidden = !square;
        $('grpState').hidden = square;
        $('lblLevel').hidden = CROSS;
        const isLetterLevel = LETTER_LEVELS.includes(level);
        $('lblAccess').hidden = !isLetterLevel;
        $('chkNonPublic').disabled = isLetterLevel;
        $('chkNative').disabled = isLetterLevel;
        if (isLetterLevel) {
            const chosen = $('selAccess').value;
            $('selAccess').innerHTML = ACCESS_FILTERS[level].map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
            if (ACCESS_FILTERS[level].some(([value]) => value === chosen)) $('selAccess').value = chosen;
        }
        $('chkInherit').disabled = level === 'method';
        $('chkTypeHint').disabled = level === 'method';
        const layered = [...$('selOrder').options].find(option => option.value === 'layered');
        layered.disabled = !square;
        if (!square && $('selOrder').value === 'layered') $('selOrder').value = 'name';
        const selected = $('selSide').value || 'rows';
        $('selSide').innerHTML = Object.entries(sideLabels(level)).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
        $('selSide').value = selected;
        renderLegend(square);
    }

    function currentOptions() {
        return {
            calls: $('chkCalls').checked,
            inherit: $('chkInherit').checked && $('selLevel').value !== 'method',
            typehint: $('chkTypeHint').checked && $('selLevel').value !== 'method',
            external: $('chkExternal').checked,
            usedBy: $('selUsedBy').value,
            nonPublic: $('chkNonPublic').checked,
            native: $('chkNative').checked,
            shared: $('chkShared').checked,
            access: LETTER_LEVELS.includes($('selLevel').value) ? $('selAccess').value : 'all',
        };
    }

    // The matrix before any focus. Cached: focus, order and hide empty only change what is drawn
    function refresh() {
        const level = $('selLevel').value;
        const options = currentOptions();
        const key = JSON.stringify([level, options]);
        if (key !== modelKey) {
            model = isSquare(level) ? buildSquare(level, options) : buildRect(level, options);
            model.analysis = analyze(model);
            modelKey = key;
            forceRender = false;
        }
        render();
    }

    function addFocus(name) {
        const cleaned = name.trim();
        if (cleaned === '' || focus.includes(cleaned)) return;
        focus.push(cleaned);
        $('focusInput').value = '';
        render();
    }

    function updateChips() {
        const all = [...model.rowEls.values(), ...model.colEls.values()];
        $('chips').innerHTML = focus.map((name, i) => `<span class="dsm-chip${all.some(el => matchesFocus(el, [name])) ? '' : ' nomatch'}">${escapeHtml(name)}<button data-remove="${i}" title="Remove">×</button></span>`).join('');
    }

    function updateSuggestions() {
        const names = new Set();
        [...model.rowEls.values(), ...model.colEls.values()].forEach(el => { names.add(el.ns); if (el.cls !== '') names.add(el.cls); names.add(el.id); });
        $('focusOptions').innerHTML = [...names].sort().map(name => `<option value="${escapeHtml(name)}">`).join('');
    }

    function saveUrl() {
        const params = new URLSearchParams();
        params.set('level', $('selLevel').value);
        if (!isSquare($('selLevel').value)) params.set('usedBy', $('selUsedBy').value);
        if (LETTER_LEVELS.includes($('selLevel').value)) params.set('access', $('selAccess').value);
        if (focus.length) params.set('focus', focus.join(','));
        params.set('side', $('selSide').value);
        params.set('order', $('selOrder').value);
        try { history.replaceState(null, '', '?' + params); } catch (e) { /* the address is a convenience only */ }
    }

    function downloadCsv() {
        if (!view) return;
        const quote = (text) => '"' + String(text).replace(/"/g, '""') + '"';
        const lines = [['', ...view.cols.map(col => col.id)].map(quote).join(',')];
        view.rows.forEach(row => lines.push([quote(row.id), ...view.cols.map(col => { const cell = model.cells.get(row.id)?.get(col.id); return cell ? quote(cellText(model, cell)) : ''; })].join(',')));
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([lines.join('\n')], { type: 'text/csv' }));
        link.download = CROSS_LEVEL === 'firebase' ? 'firebase_cross.csv' : (CROSS ? 'maltese_cross.csv' : 'dependency_matrix.csv');
        link.click();
        URL.revokeObjectURL(link.href);
    }

    // ---------- Wiring ----------

    let hoverHeaders = [];
    function onHover(event) {
        hoverHeaders.forEach(th => th.classList.remove('hl'));
        hoverHeaders = [];
        const info = $('hoverInfo');
        if (info) info.textContent = '';
        const td = event.target.closest('tbody td');
        if (!td || !view) return;
        const r = td.parentElement.sectionRowIndex;
        const c = td.cellIndex - 1;
        if (r >= view.rows.length || c >= view.cols.length) return;
        const table = td.closest('table');
        hoverHeaders = [table.tBodies[0].rows[r].cells[0], table.tHead.rows[0].cells[c + 1]];
        hoverHeaders.forEach(th => th.classList.add('hl'));
        const cell = model.cells.get(view.rows[r].id)?.get(view.cols[c].id);
        if (info) info.textContent = `${view.rows[r].name} → ${view.cols[c].name}${cell ? ': ' + cell.n : ''}`;
    }

    function init() {
        const params = new URLSearchParams(location.search);
        if (CROSS) {
            $('selLevel').value = CROSS_LEVEL;
            $('selUsedBy').value = 'method';
        }
        if (params.get('level') && !CROSS) $('selLevel').value = params.get('level');
        if (params.get('usedBy')) $('selUsedBy').value = params.get('usedBy');
        if (params.get('order')) $('selOrder').value = params.get('order');
        focus = (params.get('focus') ?? '').split(',').map(name => name.trim()).filter(Boolean);
        syncControls();
        if (params.get('access')) $('selAccess').value = params.get('access');
        if (params.get('side')) $('selSide').value = params.get('side');

        ['selLevel', 'selUsedBy', 'selAccess', 'chkCalls', 'chkInherit', 'chkTypeHint', 'chkExternal', 'chkNonPublic', 'chkNative', 'chkShared']
            .forEach(id => $(id).addEventListener('change', () => { syncControls(); refresh(); }));
        ['selOrder', 'selSide', 'chkHideEmpty'].forEach(id => $(id).addEventListener('change', render));
        $('btnFocusAdd').addEventListener('click', () => addFocus($('focusInput').value));
        $('focusInput').addEventListener('keydown', (event) => { if (event.key === 'Enter') addFocus(event.target.value); });
        $('btnFocusClear').addEventListener('click', () => { focus = []; render(); });
        $('chips').addEventListener('click', (event) => {
            const button = event.target.closest('button[data-remove]');
            if (!button) return;
            focus.splice(Number(button.dataset.remove), 1);
            render();
        });
        $('btnCsv').addEventListener('click', downloadCsv);

        const scroll = $('scroll');
        scroll.addEventListener('mouseover', onHover);
        scroll.addEventListener('click', (event) => {
            const header = event.target.closest('th[data-side]');
            if (header) return showHeader(header.dataset.side, Number(header.dataset.i));
            const td = event.target.closest('td.n');
            if (td) showCell(td.parentElement.sectionRowIndex, td.cellIndex - 1);
        });

        const read = (url) => fetch(url).then(res => { if (!res.ok) throw new Error(url + ': ' + res.status); return res.json(); });
        // The SQL is optional: a database analyzed before it was recorded has none
        Promise.all([read('api/callGraph.php'), read('api/globalsReport.php'), read('api/dsm.php'), read('api/sqlReport.php').catch(() => []), read('api/firebaseReport.php').catch(() => [])])
            .then(([calls, state, structure, sql, firebase]) => {
                data = {
                    calls, state, sql, firebase,
                    classes: structure.classes,
                    methods: structure.methods,
                    relations: structure.relations,
                    classKinds: new Map(structure.classes.map(c => [c.class_name, c.kind])),
                    methodIds: new Set(structure.methods.map(row => methodId(row.class_name, row.method_name))),
                };
                refresh();
            })
            .catch(error => { $('summary').textContent = 'Could not load the analysis: ' + error.message; });
    }

    document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
