/* Standalone report UI. Existing JSON endpoints are read-only inputs. */
(async () => {
    'use strict';
    const mode = document.body.dataset.analysisReport;
    const $ = name => document.getElementById(name);
    const D = AnaReportData;
    const descriptions = {
        impact: 'Choose a method or function and press Trace callers to list everything that calls it, directly or through other methods. These are the places to retest if you change it. Each row shows one shortest chain of calls from the caller down to your choice.',
        state: 'Global variables, superglobals and static properties are state shared by the whole request. For each one this lists the methods that read it and the methods that write it. Many writers, or readers and writers in different namespaces, mean hidden coupling. Select a row to see who touches it and how.',
        consumers: 'For each public method: how many places call it, how many different callers that is, and whether they are inside its own class or outside it. Use it before changing a method signature or upgrading a package to see who depends on it.',
        cycles: 'A group is a set of namespaces, classes or methods that depend on each other in a loop: from any member you can reach every other member by following calls. Choose the level, then select a group to see an example loop and the calls behind it.',
    };
    const notes = {
        impact: 'A caller is a place that could be affected, not proof that the change breaks it. Only calls are followed, not shared state. A call is recorded against the class of the variable it was made on, so a method called through a subclass or an interface is recorded under that class and its callers can be missing here. A method is never listed as its own caller. Calls inside the file a script runs from, outside any method or function, are not recorded.',
        state: 'Only static properties, global variables and superglobals are listed; instance properties and class constants cannot be shared and changed this way. A static property is recorded only when accessed as $this, self:: or static::, so a property used from another class as Registry::$items is not seen: cross-class sharing of static properties is understated. $_SESSION is one symbol, not one per key, and aliases are not followed. A method that only declares a variable with global is counted as a reader. Reads and writes can overlap: a method that does $count++ is both. Nothing here says in which order things happen.',
        consumers: 'Only public methods are listed. Callers are counted in the analyzed code, not in other projects that install it. No recorded calls does not mean unused: calls through an interface or a parent class are recorded under that interface or class, and framework entry points, magic methods and dynamic calls are not seen. Do not remove or hide a method on this alone. Protected methods, which subclasses depend on, are not listed.',
        cycles: 'Groups are built from recorded calls. Calls inside one class or one namespace are ignored at the class and namespace levels, so a group is always a loop between different things. At the method level a method that calls itself is shown as a group of one. A group can hold several overlapping loops; the example is one of them. A loop is a reason to test the group together, not an order to split it.',
    };
    $('reportIntro').textContent = descriptions[mode];
    $('reportNotes').textContent = notes[mode];
    let table, allRows = [];
    const text = (title, field, tip) => anaCol.text(title, field, tip, { formatter: 'plaintext', maxWidth: 440 });
    const num = (title, field, tip) => anaCol.num(title, field, tip);
    const method = () => anaCol.detail('Method', 'method', 'Open the stored method detail.', 'method', 'method');
    function entityLink(name, level = 'method') {
        if (level === 'namespace') return document.createTextNode(name);
        const link = document.createElement('a');
        const isFunction = name.startsWith('[function] ');
        link.textContent = name;
        link.href = `detail.php?type=${level === 'class' && !isFunction ? 'class' : 'method'}&name=${encodeURIComponent(isFunction ? name.slice(11) : name)}`;
        return link;
    }
    function linkedNames(names, separator = ', ', level = 'method') {
        const span = document.createElement('span');
        names.forEach((name, index) => {
            if (index) span.append(document.createTextNode(separator));
            span.append(entityLink(name, level));
        });
        return span;
    }
    const linkedColumn = (title, field, tip, separator = ', ', level = () => 'method') => ({
        ...text(title, field, tip), variableHeight: true,
        formatter: cell => {
            const content = linkedNames(String(cell.getValue() || '').split(separator).filter(Boolean), separator, level());
            content.className = 'analysis-method-links';
            return content;
        },
    });
    function control(label, element) {
        const wrapper = document.createElement('label');
        wrapper.append(document.createTextNode(label), element);
        $('reportControls').append(wrapper);
        return element;
    }
    function select(label, options) {
        const element = document.createElement('select');
        options.forEach(([value, title]) => element.add(new Option(title, value)));
        return control(label, element);
    }
    function evidence(title, description, entries) {
        const section = $('reportEvidence');
        section.replaceChildren();
        const heading = document.createElement('h2');
        heading.append(title instanceof Node ? title : document.createTextNode(title));
        const p = document.createElement('p');
        p.append(description instanceof Node ? description : document.createTextNode(description));
        const list = document.createElement('ul');
        entries.forEach(entry => {
            const li = document.createElement('li');
            if (entry instanceof Node) li.append(entry);
            else if (typeof entry === 'string') li.textContent = entry;
            else {
                const a = document.createElement('a'); a.textContent = entry.name;
                a.href = `detail.php?type=method&name=${encodeURIComponent(entry.name)}`;
                li.append(a, document.createTextNode(entry.suffix || ''));
                if (entry.indent) li.style.marginInlineStart = `${entry.indent * 24}px`;
            }
            list.append(li);
        });
        section.append(heading, p, list);
    }
    function showRow(row) {
        if (mode === 'impact') {
            evidence(entityLink(row.method), `${row.distance} call edge(s) to the selected target. One shortest recorded path:`,
                row.pathNodes.map((name, i) => ({ name, indent: i, suffix: i < row.pathNodes.length - 1 ? ' → calls the next method' : ' — selected target' })));
        } else if (mode === 'state') {
            evidence(row.symbol, `${row.writers} distinct writers; ${row.readers} distinct readers.`,
                row.evidence.map(e => ({ name: e.method_fqn, suffix: ` — ${e.access}; ${e.namespace}` })));
        } else if (mode === 'consumers') {
            evidence(entityLink(row.method), row.signature || 'No stored signature.', row.evidence.length ?
                row.evidence.map(e => ({ name: e.from, suffix: ` — ${e.weight} call site(s)` })) : ['No callers recorded in this analysis.']);
        } else {
            const level = $('reportControls').querySelector('select').value;
            const title = document.createElement('span'); title.append('Cycle group: ', entityLink(row.group, level));
            const example = document.createElement('span'); example.append('Example cycle: ', linkedNames(row.example.split(' → '), ' → ', level));
            evidence(title, example, row.evidence.flatMap(e => {
                const summary = linkedNames([e.from, e.to], ' → ', level);
                summary.append(` — ${e.weight} call site(s)`);
                return [summary, ...e.evidence.map(call => {
                    const detail = linkedNames([call.from, call.to], ' → ');
                    detail.className = 'analysis-edge-detail'; detail.append(` (${call.weight})`);
                    return detail;
                })];
            }));
        }
    }
    function mount(rows, columns, sort, placeholder = 'No matching recorded relationships.') {
        allRows = rows;
        table = new Tabulator('#reportTable', {
            data: rows, columns, layout: mode === 'impact' ? 'fitColumns' : 'fitDataFill', maxHeight: '520px', movableColumns: true,
            placeholder, initialSort: sort,
        });
        const status = () => { $('reportStatus').textContent = `${table.getDataCount('active')} of ${allRows.length} rows shown. Select a row for evidence.`; };
        if (mode !== 'impact') table.on('tableBuilt', status);
        table.on('dataFiltered', status);
        table.on('rowClick', (event, row) => showRow(row.getData()));
        $('reportClear').disabled = false; $('reportCsv').disabled = false;
        $('reportClear').onclick = () => table.clearHeaderFilter();
        $('reportCsv').onclick = () => table.download('csv', `ana_${mode}.csv`, {}, 'active');
    }
    async function replace(rows) {
        allRows = rows;
        await table.replaceData(rows);
        $('reportStatus').textContent = `${table.getDataCount('active')} of ${rows.length} rows shown. Select a row for evidence.`;
        evidence('Evidence', 'Select a row to inspect its recorded relationships.', []);
    }
    async function get(url) {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const rows = await response.json();
        if (!Array.isArray(rows)) throw new Error('Expected an array of analysis rows');
        return rows;
    }
    try {
        if (typeof Tabulator === 'undefined') throw new Error('Table library did not load');
        if (mode === 'impact') {
            const [raw, methods] = await Promise.all([get('api/callGraph.php'), get('api/methodSummary.php')]);
            const calls = D.edges(raw);
            const names = [...new Set([...methods.filter(m => m.kind !== 'Native Function').map(m => m.fqn),
                ...calls.flatMap(e => e.native ? [e.from] : [e.from, e.to])])].sort();
            const input = document.createElement('input'); input.type = 'search'; input.setAttribute('list', 'reportMethods');
            input.placeholder = 'Choose or type an exact method/function name';
            control('Target', input);
            const choices = document.createElement('datalist'); choices.id = 'reportMethods';
            names.forEach(name => choices.append(new Option(name, name))); $('reportControls').append(choices);
            const depth = select('Maximum distance', [['all', 'All reachable callers'], ['1', '1 — direct callers'], ['2', '2'], ['3', '3'], ['5', '5']]);
            const button = document.createElement('button'); button.textContent = 'Trace callers'; $('reportControls').append(button);
            mount([], [{ ...method(), width: 280 }, { ...text('Namespace', 'namespace', 'Namespace of the caller.'), width: 180 },
                { ...num('Distance', 'distance', 'Shortest number of call edges to the target.'), width: 100 },
                { ...text('Relationship', 'relationship', 'Direct or indirect caller.'), width: 140 },
                { ...linkedColumn('Example path', 'path', 'A shortest recorded caller-to-target path.', ' → '), maxWidth: undefined, minWidth: 240, widthGrow: 1 }], [{ column: 'distance', dir: 'asc' }], 'Choose a target and press Trace callers.');
            $('reportStatus').textContent = 'Choose a method or function above and press Trace callers.';
            button.onclick = async () => {
                if (!names.includes(input.value)) { $('reportStatus').textContent = 'Choose an exact name from the available analysis symbols.'; return; }
                await replace(D.impact(calls, input.value, depth.value === 'all' ? Infinity : Number(depth.value)));
                if (!allRows.length) $('reportStatus').textContent = 'No other callers recorded within this distance. This does not prove the target is unused.';
            };
            input.addEventListener('keydown', e => { if (e.key === 'Enter') button.click(); });
        } else if (mode === 'state') {
            const rows = D.sharedState(await get('api/globalsReport.php'));
            const scope = select('Symbols', [['all', 'All global variables and static properties'], ['written', 'With recorded writes'], ['cross', 'Writer and reader in different namespaces']]);
            const sharing = select('Class sharing', [['all', 'All — within and across classes, including functions'], ['same', 'Within one class only'], ['multiple', 'Across more than one class']]);
            const selected = () => rows.filter(r => (scope.value === 'all' || (scope.value === 'cross' ? r.crossNamespace : r.writers > 0)) &&
                (sharing.value === 'all' || (sharing.value === 'same' ? r.classes === 1 && r.functions === 0 : r.classes > 1)));
            mount(selected(), [{ ...text('Symbol', 'symbol', 'Class::$property for static properties; the variable name for globals.'), formatter: cell => {
                const name = cell.getValue();
                if (cell.getRow().getData().kind !== 'Static property') return document.createTextNode(name);
                const link = entityLink(name.split('::')[0], 'class'); link.textContent = name; return link;
            } }, { ...anaCol.list('Kind', 'kind', 'Static property, Global variable or Superglobal variable.'), formatter: 'plaintext' },
                text('Class sharing', 'sharing', 'Based on distinct classes of all recorded readers and writers. Standalone functions are identified separately.'),
                num('Classes', 'classes', 'Distinct classes reading or writing this symbol.'), num('Functions', 'functions', 'Distinct standalone functions reading or writing this symbol.'),
                num('Writers', 'writers', 'Distinct methods with Write or Read/Write access.'), num('Readers', 'readers', 'Distinct methods with Read or Read/Write access.'),
                num('Namespaces', 'namespaces', 'Distinct namespaces accessing the symbol.'), anaCol.flag('Written and read across namespaces', 'crossNamespace', 'A writer and a reader belong to different namespaces.'),
                linkedColumn('Writer methods', 'writerList', 'Distinct recorded writers.'), linkedColumn('Reader methods', 'readerList', 'Distinct recorded readers.')], [{ column: 'writers', dir: 'desc' }]);
            scope.onchange = () => replace(selected());
            sharing.onchange = () => replace(selected());
        } else if (mode === 'consumers') {
            const [methods, raw] = await Promise.all([get('api/methodSummary.php'), get('api/callGraph.php')]);
            mount(D.consumers(methods, D.edges(raw)), [method(), text('Namespace', 'namespace', 'Declaring class namespace.'), anaCol.list('Observed use', 'usage', 'Used outside class, Calls only within class, or No recorded calls. Based on recorded calls only; not a reason to remove a method.'),
                num('Call sites', 'sites', 'Static occurrences, not runtime frequency.'), num('Distinct callers', 'callers', 'Distinct caller methods/functions.'),
                num('Outside-class callers', 'externalCallers', 'Distinct callers outside this class.'), num('Outside-class owners', 'externalOwners', 'Distinct external classes or individual function callers.'),
                num('Other namespaces', 'externalNamespaces', 'Distinct consuming namespaces other than the target namespace.')], [{ column: 'externalCallers', dir: 'desc' }]);
        } else if (mode === 'cycles') {
            const calls = D.edges(await get('api/callGraph.php'));
            const level = select('Level', [['namespace', 'Namespaces'], ['class', 'Classes / functions'], ['method', 'Methods / functions']]);
            mount(D.cycles(calls, level.value, anaCycleNodes), [linkedColumn('Group (first member)', 'group', 'Alphabetically first member labels the group.', ', ', () => level.value),
                num('Members', 'members', 'Number of mutually reachable nodes.'), num('Edges', 'edges', 'Distinct directed edges within the group at the selected level.'),
                num('Call sites', 'sites', 'Total recorded call sites on the internal edges.'), linkedColumn('Members', 'memberList', 'All group members.', ', ', () => level.value),
                linkedColumn('Example cycle', 'example', 'One closed directed path; a group can contain several cycles.', ' → ', () => level.value)], [{ column: 'members', dir: 'desc' }]);
            level.onchange = () => replace(D.cycles(calls, level.value, anaCycleNodes));
        }
    } catch (error) {
        $('reportStatus').textContent = `Could not load this report: ${error.message}. Check the database and that the table library is reachable, then reload.`;
        $('reportStatus').classList.add('analysis-error');
    }
})();
