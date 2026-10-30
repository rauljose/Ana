/* Standalone report UI. Existing JSON endpoints are read-only inputs. */
(async () => {
    'use strict';
    const mode = document.body.dataset.analysisReport;
    const $ = name => document.getElementById(name);
    const D = AnaReportData;
    const descriptions = {
        impact: 'Explore potential change impact through recorded callers. Each row includes one shortest path from a caller to the selected method or function.',
        state: 'Inspect shared variables and static members by their recorded readers and writers. Select a symbol to see the methods and access modes behind the counts.',
        consumers: 'Understand the observed consumers of each public method before maintenance or an upgrade. Distinct callers and call sites are counted separately.',
        cycles: 'Inspect mutually reachable groups in the recorded call graph. A group may contain several overlapping cycles; its example is one path, not every possible cycle.',
    };
    const notes = {
        impact: 'A path indicates potential impact, not proof that a change breaks the caller. Paths follow calls only, not type hints, inheritance or shared state. The selected method is excluded from its own caller list, including recursive paths. Targets without a stored definition can still be explored.',
        state: 'Instance properties and explicitly classified constants are excluded. Static members are grouped by their known declaring class, including inherited accesses. The existing endpoint does not distinguish inherited static properties from inherited constants, so read-only inherited members may include constants in the All view. Reads and writes may overlap. Array keys and aliases are not stored: $_SESSION is one symbol, and $GLOBALS access cannot be equated with individual global variables. A recorded global declaration may appear as a read. No execution order or data flow is inferred.',
        consumers: 'Consumers outside the class are observed in the analyzed data; this is not a count of downstream package installations. Function callers count as separate owners. No recorded calls is not a recommendation to remove or privatize an API. Interface dispatch, framework entry points and dynamic invocations can be absent.',
        cycles: 'Groups use static call edges only. Internal calls are excluded at class and namespace levels. Direct recursion is included at method level. Functions remain separate nodes at class level. Cycles can guide regression testing and maintenance; they do not require splitting or changing classes.',
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
    function mount(rows, columns, sort) {
        allRows = rows;
        table = new Tabulator('#reportTable', {
            data: rows, columns, layout: mode === 'impact' ? 'fitColumns' : 'fitDataFill', maxHeight: '520px', movableColumns: true,
            placeholder: 'No matching recorded relationships.', initialSort: sort,
        });
        const status = () => { $('reportStatus').textContent = `${table.getDataCount('active')} of ${allRows.length} rows shown. Select a row for evidence.`; };
        table.on('tableBuilt', status);
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
                { ...linkedColumn('Example path', 'path', 'A shortest recorded caller-to-target path.', ' → '), maxWidth: undefined, minWidth: 240, widthGrow: 1 }], [{ column: 'distance', dir: 'asc' }]);
            button.onclick = async () => {
                if (!names.includes(input.value)) { $('reportStatus').textContent = 'Choose an exact name from the available analysis symbols.'; return; }
                await replace(D.impact(calls, input.value, depth.value === 'all' ? Infinity : Number(depth.value)));
                if (!allRows.length) $('reportStatus').textContent = 'No other callers recorded within this distance. This does not prove the target is unused.';
            };
            input.addEventListener('keydown', e => { if (e.key === 'Enter') button.click(); });
        } else if (mode === 'state') {
            const rows = D.sharedState(await get('api/globalsReport.php'));
            const scope = select('Symbols', [['all', 'All tracked shared variables/static members'], ['written', 'With recorded writes'], ['cross', 'Writer and reader in different namespaces']]);
            const sharing = select('Class sharing', [['all', 'All — within and across classes, including functions'], ['same', 'Within one class only'], ['multiple', 'Across more than one class']]);
            const selected = () => rows.filter(r => (scope.value === 'all' || (scope.value === 'cross' ? r.crossNamespace : r.writers > 0)) &&
                (sharing.value === 'all' || (sharing.value === 'same' ? r.classes === 1 && r.functions === 0 : r.classes > 1)));
            mount(selected(), [{ ...text('Symbol', 'symbol', 'Known declaration for static members; recorded name for globals.'), formatter: cell => {
                const name = cell.getValue();
                if (cell.getRow().getData().kind !== 'Static member') return document.createTextNode(name);
                const link = entityLink(name.split('::')[0], 'class'); link.textContent = name; return link;
            } }, { ...anaCol.list('Kind', 'kind', 'Static member, Global variable, or Superglobal variable.'), formatter: 'plaintext' },
                text('Class sharing', 'sharing', 'Based on distinct classes of all recorded readers and writers. Standalone functions are identified separately.'),
                num('Classes', 'classes', 'Distinct classes reading or writing this symbol.'), num('Functions', 'functions', 'Distinct standalone functions reading or writing this symbol.'),
                num('Writers', 'writers', 'Distinct methods with Write or Read/Write access.'), num('Readers', 'readers', 'Distinct methods with Read or Read/Write access.'),
                num('Namespaces', 'namespaces', 'Distinct namespaces accessing the symbol.'), anaCol.flag('Cross-namespace write/read', 'crossNamespace', 'At least one writer and reader belong to different namespaces.'),
                linkedColumn('Writer methods', 'writerList', 'Distinct recorded writers.'), linkedColumn('Reader methods', 'readerList', 'Distinct recorded readers.')], [{ column: 'writers', dir: 'desc' }]);
            scope.onchange = () => replace(selected());
            sharing.onchange = () => replace(selected());
        } else if (mode === 'consumers') {
            const [methods, raw] = await Promise.all([get('api/methodSummary.php'), get('api/callGraph.php')]);
            mount(D.consumers(methods, D.edges(raw)), [method(), text('Namespace', 'namespace', 'Declaring class namespace.'), text('Observed use', 'usage', 'Call-based classification, not an API removal recommendation.'),
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
