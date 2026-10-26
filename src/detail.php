<?php
$type = $_GET['type'] ?? 'class';
$name = $_GET['name'] ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail</title>
    <link rel="stylesheet" href="css/ana.css">
</head>
<body class="ana-index-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-detail">
    <h1 id="title"></h1>
    <p class="ana-subtitle" id="subtitle"></p>
    <div class="ana-links" id="links"></div>
    <div class="ana-stats" id="cards"></div>
    <div id="sections"></div>
</div>

<script>
    // Variables hydrated directly from PHP GET parameters
    const entityType = <?= json_encode($type) ?>;
    const entityName = <?= json_encode($name) ?>;

    const el = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    const link = (href, text) => {
        const a = el('a', text);
        a.href = href;
        return a;
    };
    const classLink = (className) => link(`detail.php?type=class&name=${encodeURIComponent(className)}`, className);
    const methodLink = (className, methodName, isNative) => {
        const label = className === '' ? methodName + '()' : className + '::' + methodName + '()';
        if (isNative) return el('span', label);
        const fullName = className === '' ? methodName : className + '::' + methodName;
        return link(`detail.php?type=method&name=${encodeURIComponent(fullName)}`, label);
    };

    const addCard = (label, value) => {
        const card = el('div', undefined, 'ana-stat');
        card.append(el('div', String(value), 'value'), el('div', label, 'label'));
        document.getElementById('cards').appendChild(card);
    };

    // columns: [{ title, cell: row => string | Node, num: bool }]
    const addSection = (title, columns, rows) => {
        const box = document.getElementById('sections');
        box.appendChild(el('h2', `${title} (${rows.length})`));
        if (rows.length === 0) {
            box.appendChild(el('p', 'None', 'ana-empty'));
            return;
        }
        const table = el('table');
        const head = el('tr');
        columns.forEach(column => head.appendChild(el('th', column.title, column.num ? 'num' : '')));
        table.appendChild(head);
        rows.forEach(row => {
            const tr = el('tr');
            columns.forEach(column => {
                const td = el('td', undefined, column.num ? 'num' : '');
                td.append(column.cell(row));
                tr.appendChild(td);
            });
            table.appendChild(tr);
        });
        box.appendChild(table);
    };

    const accessLabel = (access) => ({ READ: 'Read', WRITE: 'Write', READ_WRITE: 'Read/Write' })[access] || access;
    const yesNo = (value) => (value === true || value === 1 || value === '1') ? 'yes' : 'no';

    async function showClass() {
        const [summary, methods, extra] = await Promise.all([
            fetch(`api/classSummary.php?class=${encodeURIComponent(entityName)}`).then(r => r.json()),
            fetch(`api/methodSummary.php?classes=${encodeURIComponent(entityName)}`).then(r => r.json()),
            fetch(`api/detail.php?type=class&name=${encodeURIComponent(entityName)}`).then(r => r.json())
        ]);
        const info = summary[0];
        if (!info) {
            document.getElementById('title').textContent = entityName;
            document.getElementById('subtitle').textContent = 'Class not found in the analysis.';
            return;
        }

        document.getElementById('title').textContent = info.class_name;
        document.getElementById('subtitle').textContent = `${info.type} in ${info.namespace}`;
        const links = document.getElementById('links');
        links.append(
            link(`call_graph.php?level=class&classname=${encodeURIComponent(info.class_name)}`, 'Call graph of this class'),
            link(`method_graph.php?classname=${encodeURIComponent(info.class_name)}`, 'Method graphs of this class')
        );
        if (info.doc_comment) links.before(el('pre', info.doc_comment));
        const badges = el('p');
        [['is_god_class', 'God class'], ['is_data_class', 'Data class'], ['is_stateless_service', 'Stateless service']]
            .filter(([field]) => info[field])
            .forEach(([field, label]) => badges.appendChild(el('span', label, 'ana-badge' + (field === 'is_god_class' ? '' : ' info'))));
        if (badges.children.length > 0) links.after(badges);

        [['Methods', info.total_methods], ['Cyclomatic total', info.total_cyclo], ['Cyclomatic avg', info.avg_cyclo],
         [`Cyclomatic max (${info.max_cyclo_method})`, info.max_cyclo_val], ['Cognitive total', info.total_cog],
         ['Pure %', info.pure_methods_pct], ['Ca (used by)', info.afferent_ca], ['Ce (uses)', info.efferent_ce],
         ['Instability', info.instability], ['LCOM', info.lcom], ['Mutable properties', info.mutable_properties],
         ['Children', info.children_count]].forEach(([label, value]) => addCard(label, value));

        addSection('Methods', [
            { title: 'Method', cell: row => methodLink(row.class_name === '[Global Scope]' ? '' : row.class_name, row.method_name, false) },
            { title: 'Visibility', cell: row => row.visibility },
            { title: 'Cyclomatic', cell: row => row.cyclo_complexity, num: true },
            { title: 'Cognitive', cell: row => row.cognitive_complexity, num: true },
            { title: 'Params', cell: row => row.num_parameters, num: true },
            { title: 'Calls out', cell: row => row.calls_out_total, num: true },
            { title: 'Calls in', cell: row => row.calls_in_total, num: true },
            { title: 'Props written', cell: row => row.props_written, num: true },
        ], methods.sort((a, b) => b.cyclo_complexity - a.cyclo_complexity));

        const relation = (row) => row.relation_type;
        addSection('Extends, implements, uses', [
            { title: 'Class', cell: row => classLink(row.class_name) }, { title: 'Relation', cell: relation }
        ], extra.parents);
        addSection('Extended, implemented, used by', [
            { title: 'Class', cell: row => classLink(row.class_name) }, { title: 'Relation', cell: relation }
        ], extra.children);
        addSection('Properties and constants', [
            { title: 'Name', cell: row => row.property_name },
            { title: 'Visibility', cell: row => row.visibility },
            { title: 'Type', cell: row => row.type_hint || '' },
            { title: 'Static', cell: row => yesNo(row.is_static) },
            { title: 'Readonly', cell: row => yesNo(row.is_readonly) },
            { title: 'Constant', cell: row => yesNo(row.is_constant) },
            { title: 'Doc comment', cell: row => row.doc_comment || '' },
        ], extra.properties);
        addSection('Classes this class uses', [
            { title: 'Class', cell: row => classLink(row.class_name) }, { title: 'Calls', cell: row => row.calls, num: true }
        ], extra.calls_out);
        addSection('Classes using this class', [
            { title: 'Class', cell: row => classLink(row.class_name) }, { title: 'Calls', cell: row => row.calls, num: true }
        ], extra.calls_in);
        addSection('Property access by method', [
            { title: 'Method', cell: row => methodLink(info.class_name, row.method_name, false) },
            { title: 'Property', cell: row => row.property_name },
            { title: 'Access', cell: row => accessLabel(row.access_type) },
        ], extra.prop_access);
        addSection('Global state by method', [
            { title: 'Method', cell: row => methodLink(info.class_name, row.method_name, false) },
            { title: 'Type', cell: row => row.global_type },
            { title: 'Name', cell: row => row.global_name },
            { title: 'Access', cell: row => accessLabel(row.access) },
        ], extra.globals);
    }

    async function showMethod() {
        const extra = await fetch(`api/detail.php?type=method&name=${encodeURIComponent(entityName)}`).then(r => r.json());
        const info = extra.method;
        document.getElementById('title').textContent = entityName;
        if (!info) {
            document.getElementById('subtitle').textContent = 'Method not found in the analysis.';
            return;
        }

        const isFunction = extra.class_name === '';
        document.getElementById('subtitle').textContent = isFunction ? 'Function' : `${info.visibility}${info.is_static === '1' ? ' static' : ''} method`;
        const links = document.getElementById('links');
        if (!isFunction) links.append(el('span', 'Class: '), classLink(extra.class_name), el('span', ' '));
        links.appendChild(link(`call_graph.php?level=method&method=${encodeURIComponent(entityName)}&depth=2`, 'Call graph of this method'));

        [['Cyclomatic', info.cyclo_complexity], ['Cognitive', info.cognitive_complexity], ['Parameters', info.num_parameters],
         ['By reference', info.num_reference_parameters], ['Calls out', extra.calls_out.length], ['Calls in', extra.calls_in.length],
         ['Pure', yesNo(info.is_pure)], ['Ends script', yesNo(info.is_ends)], ['Return type', info.return_type || 'none']
        ].forEach(([label, value]) => addCard(label, value));

        const box = document.getElementById('sections');
        box.appendChild(el('h2', 'Signature'));
        box.appendChild(el('pre', info.signature));
        if (info.doc_comment) {
            box.appendChild(el('h2', 'Doc comment'));
            box.appendChild(el('pre', info.doc_comment));
        }

        addSection('Calls out', [
            { title: 'Callee', cell: row => methodLink(row.to_class, row.to_method, row.is_builtin === '1') },
            { title: 'Calls', cell: row => row.calls, num: true }
        ], extra.calls_out);
        addSection('Called by', [
            { title: 'Caller', cell: row => methodLink(row.from_class, row.from_method, false) },
            { title: 'Calls', cell: row => row.calls, num: true }
        ], extra.calls_in);
        addSection('Property access', [
            { title: 'Property', cell: row => row.property_name }, { title: 'Access', cell: row => accessLabel(row.access_type) }
        ], extra.prop_access);
        addSection('Global state', [
            { title: 'Type', cell: row => row.global_type }, { title: 'Name', cell: row => row.global_name },
            { title: 'Access', cell: row => accessLabel(row.access) }
        ], extra.globals);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.title = `${entityName} - Detail`;
        const load = entityType === 'method' ? showMethod : showClass;
        load().catch(err => {
            console.error(err);
            document.getElementById('subtitle').textContent = 'Failed to load the detail data.';
        });
    });
</script>
</body>
</html>
