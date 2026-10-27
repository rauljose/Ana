<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
</head>
<body class="ana-review-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-review">
    <h1>Review</h1>
    <p class="ana-subtitle">The short list of things worth a look first. Change a threshold and every list updates; the values are saved.</p>

    <div class="ana-thresholds" id="reviewInputs">
        <button id="reviewReset" title="Put every threshold back to its default">Defaults</button>
        <span class="ana-status" id="reviewStatus">Loading...</span>
    </div>

    <div class="ana-summary" id="reviewSummary"></div>

    <section id="sharedVariables">
        <h2>Global variables and superglobals written to</h2>
        <p class="ana-why">Variables declared with <code>global</code> that a method writes and a method of another namespace also uses: shared mutable state that no namespace owns. Every written superglobal ($_SESSION, $_POST...) is listed, whatever the namespace.</p>
        <div class="ana-thresholds" id="sharedVariablesInputs"></div>
        <div id="sharedVariablesTable"></div>
    </section>

    <section id="sharedReads">
        <h2>Constants, defines, global variables and superglobals read outside their namespace</h2>
        <p class="ana-why">Project constants (<code>define()</code> and <code>const</code>; native ones are left out), <code>global</code> variables and superglobals that methods of more than one namespace read: configuration and state the whole project depends on. Where a constant or variable is defined is not stored, so check that its definition is in analyzed code.</p>
        <div class="ana-thresholds" id="sharedReadsInputs"></div>
        <div id="sharedReadsTable"></div>
    </section>

    <section id="staticWrites">
        <h2>Static properties written</h2>
        <p class="ana-why">Static properties that a method writes: state shared by the whole process, changed by whoever runs. A property written through a subclass or a using class is attributed to the class that declares it. Filter the "Written in own class" and "Written in other classes" columns: tick shows only those, cross excludes them.</p>
        <div class="ana-thresholds" id="staticWritesInputs"></div>
        <div id="staticWritesTable"></div>
    </section>

    <section id="sensitiveMethods">
        <h2>Methods and functions with sensitive parameters</h2>
        <p class="ana-why">Parameters marked <code>#[SensitiveParameter]</code>: a secret passes through. The parameter name and type are read from the stored signature.</p>
        <div class="ana-thresholds" id="sensitiveMethodsInputs"></div>
        <div id="sensitiveMethodsTable"></div>
    </section>

    <section id="hardMethods">
        <h2>Hard to change methods</h2>
        <p class="ana-why">Cyclomatic complexity, cognitive complexity, or the hotspot score (cyclomatic complexity x calls in) at or above the thresholds. The Why column says which one tripped.</p>
        <div class="ana-thresholds" id="hardMethodsInputs"></div>
        <div id="hardMethodsTable"></div>
    </section>

    <section id="usedMethods">
        <h2>Most used methods</h2>
        <p class="ana-why">Methods called at least the minimum number of times, optionally in one namespace: the contract the rest of the code depends on, so changes there cost the most.</p>
        <div class="ana-thresholds" id="usedMethodsInputs"></div>
        <div id="usedMethodsTable"></div>
    </section>

    <section id="usedNamespaces">
        <h2>Namespaces by use</h2>
        <p class="ana-why">Project namespaces sorted by how many other namespaces use them, then by calls in. The top ones are the foundation.</p>
        <div class="ana-thresholds" id="usedNamespacesInputs"></div>
        <div id="usedNamespacesTable"></div>
    </section>
</div>

<script>
    // SSOT for the inputs: name (saved in user_settings), label, default and minimum
    const reviewSettings = [
        { name: 'max_cyclo', label: 'Cyclomatic complexity from', tip: 'McCabe cyclomatic complexity: independent paths through the method', default: 15, min: 1 },
        { name: 'max_cognitive', label: 'Cognitive complexity from', tip: 'How hard the method is to read; nesting costs extra', default: 15, min: 1 },
        { name: 'max_hotspot', label: 'Hotspot score from', tip: 'Hotspot score = Cyclomatic complexity x Calls in (fan-in)', default: 150, min: 1 },
        { name: 'min_calls_in', label: 'Calls in (fan-in) from', tip: 'Calls to the method found in the project', default: 10, min: 1 },
    ];
    const namespaceSetting = 'used_namespace';

    const inputs = {};
    const sections = {};
    let data = null;

    // Deciders: pure functions from the loaded data and the thresholds to the rows of a list
    const numberOf = (value) => parseInt(value) || 0;

    function sharedSymbols(globals, isWanted, isListed) {
        const bySymbol = new Map();
        globals.filter(isWanted).forEach(row => {
            const symbol = bySymbol.get(row.symbol_fqn) ?? { symbol: row.symbol_fqn, symbol_type: row.symbol_type, namespaces: new Set(), users: new Set(), writers: new Set(), writerClasses: new Set(), writtenInOwn: false, writtenInOther: false };
            symbol.namespaces.add(row.namespace);
            symbol.users.add(row.method_fqn);
            if (row.access !== 'Read') {
                symbol.writers.add(row.method_fqn);
                symbol.writerClasses.add(row.class_name || row.method_fqn);
                if (row.declared_in === row.class_name) symbol.writtenInOwn = true;
                else symbol.writtenInOther = true;
            }
            bySymbol.set(row.symbol_fqn, symbol);
        });
        return [...bySymbol.values()]
            .filter(isListed)
            .map(symbol => ({
                symbol: symbol.symbol,
                symbol_type: symbol.symbol_type,
                namespace_count: symbol.namespaces.size,
                namespace_list: [...symbol.namespaces].sort().join(', '),
                user_count: symbol.users.size,
                writer_count: symbol.writers.size,
                writer_list: [...symbol.writers].sort().join(', '),
                writer_class_count: symbol.writerClasses.size,
                written_in_own: symbol.writtenInOwn,
                written_in_other: symbol.writtenInOther,
            }));
    }

    // Static property rows, with inherited ones attributed to the class declaring the property
    function staticPropertyRows(globals) {
        return globals
            .filter(row => row.symbol_type === 'Static property' || (row.symbol_type === 'Inherited member' && row.is_declared_static))
            .map(row => ({ ...row, symbol_type: 'Static property', symbol_fqn: row.declared_in + '::$' + row.symbol.replace(/^\$/, '') }));
    }

    // Sensitive parameters read from the printed signature: #[SensitiveParameter] [modifiers] [type] [&|...]$name
    function sensitiveParameters(signature) {
        const found = [...String(signature ?? '').matchAll(/#\[\s*\\?SensitiveParameter\s*(?:\([^)]*\))?\s*\]\s*((?:(?:public|protected|private|readonly)\s+)*)([^$]*?)\s*(?:&|\.\.\.)?\s*\$(\w+)/g)];
        return found.map(match => `${match[2].trim() || '(no type)'} $${match[3]}`);
    }

    function sensitiveMethods(methods) {
        return methods
            .filter(row => row.kind !== 'Native Function' && numberOf(row.has_sensitive_parameter) === 1)
            .map(row => ({ ...row, sensitive_parameters: sensitiveParameters(row.signature).join('\n') || '(not found in the signature)' }));
    }

    function hardMethods(methods, limits) {
        return methods
            .filter(row => row.kind !== 'Native Function')
            .map(row => {
                const cyclo = numberOf(row.cyclo_complexity);
                const cognitive = numberOf(row.cognitive_complexity);
                const callsIn = numberOf(row.calls_in_total);
                const hotspot = cyclo * callsIn;
                const why = [];
                if (cyclo >= limits.max_cyclo) why.push('cyclomatic');
                if (cognitive >= limits.max_cognitive) why.push('cognitive');
                if (hotspot >= limits.max_hotspot) why.push('hotspot');
                return { ...row, cyclo_complexity: cyclo, cognitive_complexity: cognitive, calls_in_total: callsIn, hotspot_score: hotspot, why: why.join(', ') };
            })
            .filter(row => row.why !== '');
    }

    function usedMethods(methods, limits, namespace) {
        return methods
            .filter(row => row.kind !== 'Native Function' && numberOf(row.calls_in_total) >= limits.min_calls_in)
            .filter(row => namespace === '' || row.namespace === namespace)
                        .map(row => ({
                ...row,
                calls_in_total: numberOf(row.calls_in_total),
                cyclo_complexity: numberOf(row.cyclo_complexity),
                cognitive_complexity: numberOf(row.cognitive_complexity),
                hotspot_score: numberOf(row.cyclo_complexity) * numberOf(row.calls_in_total),
            }));
    }

    const settingsNow = () => Object.fromEntries(reviewSettings.map(setting => {
        const value = parseInt(inputs[setting.name].value);
        return [setting.name, isNaN(value) ? setting.default : Math.max(value, setting.min)];
    }));

    // Lists: id of the section, columns, and the rows now
    const listDefinitions = [
        {
            id: 'sharedVariables', label: 'Global variables and superglobals written to', sort: [{ column: 'writer_count', dir: 'desc' }], inputs: [],
            rows: () => sharedSymbols(
                data.globals,
                row => row.symbol_type === 'Global variable' || row.symbol_type === 'Superglobal variable',
                symbol => symbol.writers.size > 0 && (symbol.symbol_type === 'Superglobal variable' || symbol.namespaces.size > 1)
            ),
            columns: [
                anaCol.rowNumber(),
                anaCol.text('Variable', 'symbol', 'Name of the variable.'),
                anaCol.list('Type', 'symbol_type', 'Global variable (global keyword) or Superglobal variable ($_GET, $_SESSION...).'),
                anaCol.num('Namespaces', 'namespace_count', 'Different namespaces with a method using the variable.'),
                anaCol.num('Methods', 'user_count', 'Methods and functions using the variable.'),
                anaCol.num('Writers', 'writer_count', 'Methods and functions that write the variable.'),
                anaCol.text('Writing methods', 'writer_list', 'The methods and functions that write the variable.'),
                anaCol.text('Namespaces using it', 'namespace_list', 'The namespaces with a method using the variable.'),
            ],
        },
        {
            id: 'sharedReads', label: 'Constants, defines, global variables and superglobals read outside their namespace', sort: [{ column: 'namespace_count', dir: 'desc' }], inputs: [],
            rows: () => sharedSymbols(
                data.globals,
                row => ['Constant', 'Global variable', 'Superglobal variable'].includes(row.symbol_type) && !(row.symbol_type === 'Constant' && row.is_native) && row.access !== 'Write',
                symbol => symbol.namespaces.size > 1
            ),
            columns: [
                anaCol.rowNumber(),
                anaCol.text('Symbol', 'symbol', 'Name of the constant or variable as written in code.'),
                anaCol.list('Type', 'symbol_type', 'Constant (define or const), Global variable (global keyword) or Superglobal variable ($_GET, $_SESSION...).'),
                anaCol.num('Namespaces', 'namespace_count', 'Different namespaces with a method reading it.'),
                anaCol.num('Methods', 'user_count', 'Methods and functions reading it.'),
                anaCol.text('Namespaces reading it', 'namespace_list', 'The namespaces with a method reading it.'),
            ],
        },
        {
            id: 'staticWrites', label: 'Static properties written', sort: [{ column: 'writer_class_count', dir: 'desc' }, { column: 'writer_count', dir: 'desc' }], inputs: [],
            rows: () => sharedSymbols(
                staticPropertyRows(data.globals),
                () => true,
                symbol => symbol.writers.size > 0
            ),
            columns: [
                anaCol.rowNumber(),
                anaCol.text('Static property', 'symbol', 'Class::$property.'),
                anaCol.flag('Written in own class', 'written_in_own', 'A method of the class declaring the property writes it. Tick: only those, cross: leave those out, empty: all.'),
                anaCol.flag('Written in other classes', 'written_in_other', 'A method of another class (a subclass or a class using the trait) writes it. Tick: only those, cross: leave those out, empty: all.'),
                anaCol.num('Classes writing', 'writer_class_count', 'Different classes with a method writing the property.'),
                anaCol.num('Writers', 'writer_count', 'Methods that write the property.'),
                anaCol.text('Writing methods', 'writer_list', 'The methods that write the property.'),
                anaCol.num('Methods using it', 'user_count', 'Methods reading or writing the property.'),
            ],
        },
        {
            id: 'sensitiveMethods', label: 'Methods and functions with sensitive parameters', sort: [{ column: 'fqn', dir: 'asc' }], inputs: [],
            rows: () => sensitiveMethods(data.methods),
            columns: [
                anaCol.rowNumber(),
                anaCol.detail('Method', 'fqn', 'Method or function. Click for the method detail page.', 'method', 'fqn'),
                anaCol.text('Sensitive parameters', 'sensitive_parameters', 'Type and name of each parameter marked #[SensitiveParameter].', { formatter: 'textarea', width: 260 }),
                anaCol.text('Signature', 'signature', 'Signature as printed by the analysis.', { formatter: 'textarea', width: 520 }),
                anaCol.text('Docblock', 'doc_comment', 'Doc comment of the method.', { formatter: 'textarea', width: 520 }),
            ],
        },
        {
            id: 'hardMethods', label: 'Hard to change methods', sort: [{ column: 'hotspot_score', dir: 'desc' }], inputs: ['max_cyclo', 'max_cognitive', 'max_hotspot'],
            rows: (limits) => hardMethods(data.methods, limits),
            columns: [
                anaCol.rowNumber(),
                anaCol.detail('Method', 'fqn', 'Method or function. Click for the method detail page.', 'method', 'fqn'),
                anaCol.text('Why', 'why', 'The thresholds this method is at or above.'),
                anaCol.num('Cyclomatic', 'cyclo_complexity', 'Number of independent paths through the method, the minimum number of tests.'),
                anaCol.num('Cognitive', 'cognitive_complexity', 'How hard the method is to read; nesting costs extra.'),
                anaCol.num('Calls in (fan-in)', 'calls_in_total', 'Calls to this method found in the project.'),
                anaCol.num('Hotspot score', 'hotspot_score', 'Hotspot score = Cyclomatic complexity x Calls in (fan-in). Complex methods that many methods call are the riskiest to change.'),
            ],
        },
        {
            id: 'usedMethods', label: 'Most used methods', sort: [{ column: 'calls_in_total', dir: 'desc' }], inputs: ['min_calls_in'], hasNamespacePicker: true,
            rows: (limits) => usedMethods(data.methods, limits, namespacePicker.value),
            columns: [
                anaCol.rowNumber(),
                anaCol.detail('Method', 'fqn', 'Method or function. Click for the method detail page.', 'method', 'fqn'),
                anaCol.text('Namespace', 'namespace', 'Namespace of the class or function.'),
                anaCol.num('Calls in', 'calls_in_total', 'Calls to this method found in the project.'),
                anaCol.num('Cyclomatic', 'cyclo_complexity', 'Number of independent paths through the method. A complex method that is also widely used is a hotspot.'),
                anaCol.num('Cognitive', 'cognitive_complexity', 'How hard the method is to read; nesting costs extra.'),
                anaCol.num('Hotspot score', 'hotspot_score', 'Hotspot score = Cyclomatic complexity x Calls in (fan-in). Complex methods that many methods call are the riskiest to change.'),
            ],
        },
        {
            id: 'usedNamespaces', label: 'Namespaces by use', sort: [{ column: 'afferent_ca', dir: 'desc' }, { column: 'calls_in', dir: 'desc' }], inputs: [],
            rows: () => data.namespaces,
            columns: [
                anaCol.rowNumber(),
                anaCol.text('Namespace', 'namespace', 'Namespace of the analyzed code.'),
                anaCol.num('Used by (namespaces)', 'afferent_ca', 'Different namespaces with a method calling into this one.'),
                anaCol.num('Calls in', 'calls_in', 'Calls from other namespaces into this one.'),
                anaCol.num('Uses (namespaces)', 'efferent_ce', 'Different namespaces this one calls into.'),
                anaCol.text('Used by', 'namespaces_using_this', 'The namespaces calling into this one.'),
            ],
        },
    ];

    const namespacePicker = document.createElement('select');

    // Each input sits on top of the list it filters
    function buildInputs() {
        const makeLabel = (text, control, tip) => {
            const label = document.createElement('label');
            label.title = tip ?? '';
            label.append(text, control);
            return label;
        };
        listDefinitions.forEach(definition => {
            const holder = document.getElementById(definition.id + 'Inputs');
            definition.inputs.forEach(name => {
                const setting = reviewSettings.find(candidate => candidate.name === name);
                const input = document.createElement('input');
                input.type = 'number';
                input.min = setting.min;
                input.value = setting.default;
                input.addEventListener('input', changed);
                inputs[name] = input;
                holder.appendChild(makeLabel(setting.label, input, setting.tip));
            });
            if (definition.hasNamespacePicker) {
                namespacePicker.addEventListener('change', changed);
                holder.appendChild(makeLabel('Namespace', namespacePicker));
            }
            if (holder.children.length === 0) holder.remove();
        });
        document.getElementById('reviewReset').addEventListener('click', () => {
            reviewSettings.forEach(setting => { inputs[setting.name].value = setting.default; });
            namespacePicker.value = '';
            changed();
        });
    }

    function fillNamespacePicker() {
        const namespaces = [...new Set(data.methods.filter(row => row.kind !== 'Native Function').map(row => row.namespace))].sort();
        namespacePicker.replaceChildren(new Option('All namespaces', ''), ...namespaces.map(namespace => new Option(namespace, namespace)));
    }

    async function saveSettings() {
        const status = document.getElementById('reviewStatus');
        try {
            const response = await fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ page: 'review', values: { ...settingsNow(), [namespaceSetting]: namespacePicker.value } }),
            });
            if (!response.ok) throw new Error((await response.json()).error);
            status.textContent = 'Settings saved';
        } catch (error) {
            status.textContent = 'Settings not saved: ' + error.message;
        }
    }

    function changed() {
        refresh();
        saveSettings();
    }

    function refresh() {
        const limits = settingsNow();
        const summary = document.getElementById('reviewSummary');
        summary.replaceChildren();
        listDefinitions.forEach(definition => {
            const rows = definition.rows(limits);
            const section = sections[definition.id];
            if (section.built) section.table.replaceData(rows);
            else section.pending = rows;

            const card = document.createElement('a');
            card.href = '#' + definition.id;
            const label = document.createElement('span');
            label.className = 'label';
            label.textContent = definition.label + ':';
            const value = document.createElement('span');
            value.className = 'value' + (rows.length === 0 ? ' ana-clear' : '');
            value.textContent = rows.length;
            card.append(label, value);
            summary.appendChild(card);
        });
    }

    function buildTables() {
        listDefinitions.forEach(definition => {
            const section = { built: false, pending: null, table: null };
            sections[definition.id] = section;
            section.table = new Tabulator('#' + definition.id + 'Table', {
                data: [],
                layout: 'fitDataFill',
                maxHeight: '420px',
                movableColumns: true,
                placeholder: 'Nothing at or above the thresholds',
                initialSort: definition.sort,
                columns: definition.columns,
            });
            section.table.on('tableBuilt', () => {
                section.built = true;
                if (section.pending) section.table.replaceData(section.pending);
            });
        });
    }

    async function load() {
        const status = document.getElementById('reviewStatus');
        const [globals, methods, namespaces] = await Promise.all(
            ['api/globalsReport.php', 'api/methodSummary.php', 'api/namespaceSummary.php'].map(url => fetch(url).then(response => response.json()))
        );
        data = { globals, methods, namespaces };
        fillNamespacePicker();

        // Saved settings are optional: without the user_settings table the defaults stay
        try {
            const response = await fetch('api/settings.php?page=review');
            const saved = await response.json();
            if (!response.ok) throw new Error(saved.error);
            reviewSettings.forEach(setting => {
                if (saved[setting.name] !== undefined) inputs[setting.name].value = saved[setting.name];
            });
            if (saved[namespaceSetting] !== undefined) namespacePicker.value = saved[namespaceSetting];
            status.textContent = '';
        } catch (error) {
            status.textContent = 'Saved settings not available, defaults used: ' + error.message;
        }
        refresh();
    }

    buildInputs();
    buildTables();
    load().catch(error => { document.getElementById('reviewStatus').textContent = 'Failed to load data: ' + error.message; });
</script>
</body>
</html>
