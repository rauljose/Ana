<?php
$preSelectedNamespaces = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['namespace'] ?? ''))));
$preSelectedClasses = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['classname'] ?? ''))));
$preSelectedMethods = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['method'] ?? ''))));
$level = $_REQUEST['level'] ?? 'class';
$scope = $_REQUEST['scope'] ?? 'all';
$direction = $_REQUEST['direction'] ?? 'both';
$depth = (int) ($_REQUEST['depth'] ?? 1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Graph</title>

    <!-- External Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <link rel="stylesheet" href="css/ana.css">

    <!-- Reusable Dual Listbox Widget -->
    <link rel="stylesheet" href="js/DualListbox/DualListbox.css">
    <script src="js/DualListbox/DualListbox.js"></script>
    <script src="js/ana_graph.js"></script>

    <style>
        :root {
            --bg-color: #f8f9fa;
            --panel-bg: #ffffff;
            --border-color: #dee2e6;
            --text-main: #212529;
            --accent: #0d6efd;
            --accent-hover: #0b5ed7;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        header {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            background-color: var(--panel-bg);
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        header a {
            text-decoration: none;
            color: var(--accent);
            font-weight: bold;
            margin-right: 20px;
        }

        header h1 {
            margin: 0;
            font-size: 1.2rem;
        }

        /* --- Controls & Layout --- */
        .top-controls {
            padding: 15px 20px;
            background: var(--panel-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
        }

        .checkbox-group label {
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        button.primary-btn {
            padding: 10px 24px;
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: background-color 0.2s;
        }

        button.primary-btn:hover { background-color: var(--accent-hover); }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        /* --- Dual Listbox Grid Container --- */
        .filters-container {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .filter-block h3 {
            margin-top: 0;
            font-size: 1.05rem;
            margin-bottom: 10px;
        }

        /* --- Charts Grid --- */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            min-height: 500px;
            margin-bottom: 20px;
        }
        .charts-container.single-chart { grid-template-columns: 1fr; }
        .chart-wrapper {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            display: flex;
            flex-direction: column;
        }
        .chart-wrapper.hidden { display: none; }
        .chart-wrapper h2 { margin: 0 0 15px 0; font-size: 1.1rem; text-align: center; }
        .chart-canvas { flex: 1; width: 100%; min-height: 400px; }
        .sys-message { color: #dc3545; font-size: 0.9rem; text-align: center; }

        /* --- Definitions Layout --- */
        .definitions-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 20px;
        }
        .def-column h3 { margin-top: 0; border-bottom: 2px solid var(--accent); padding-bottom: 8px; font-size: 1.1rem; }
        .how-to-read { font-style: italic; font-size: 0.9rem; background: #f8f9fa; padding: 10px; border-left: 4px solid var(--accent); margin-bottom: 15px; }
        dt { font-weight: bold; color: #343a40; margin-top: 12px; }
        dd { margin-left: 0; margin-bottom: 5px; font-size: 0.95rem; color: #495057; }
    
        .top-controls { flex-wrap: wrap; gap: 12px; }
        .checkbox-group { flex-wrap: wrap; align-items: center; }
        .checkbox-group select { padding: 4px 6px; }
        .graph-stats { font-size: 0.9rem; color: #495057; margin-bottom: 10px; }
    </style>

</head>
<body>

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="top-controls">
    <div class="checkbox-group">
        <label>Level
            <select id="selLevel">
                <option value="namespace">Namespace</option>
                <option value="class">Class</option>
                <option value="method">Method</option>
            </select>
        </label>
        <label>Calls
            <select id="selScope">
                <option value="all">All</option>
                <option value="outsideClass">Only outside the class</option>
                <option value="outsideNamespace">Only outside the namespace</option>
                <option value="insideClass">Only inside the same class</option>
                <option value="insideNamespace">Only inside the same namespace</option>
            </select>
        </label>
        <label>Direction
            <select id="selDirection">
                <option value="both">Incoming and outgoing</option>
                <option value="callees">Outgoing only (calls)</option>
                <option value="callers">Incoming only (called by)</option>
            </select>
        </label>
        <label>Depth
            <select id="selDepth">
                <option value="1">1 (direct)</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
            </select>
        </label>
        <label><input type="checkbox" id="chkCycles" checked> Highlight cycles</label>
        <label><input type="checkbox" id="chkNative"> Include native PHP functions</label>
    </div>
    <button id="btnUpdate" class="primary-btn">Render Graph</button>
</div>

<div class="main-content">

    <!-- Dual Listbox Filters -->
    <div class="filters-container">
        <div class="filter-block">
            <h3>Filter by Namespace</h3>
            <div id="namespaceWidget"></div>
        </div>
        <div class="filter-block">
            <h3>Filter by Class</h3>
            <div id="classWidget"></div>
        </div>
        <div class="filter-block">
            <h3>Filter by Specific Method</h3>
            <div id="methodWidget"></div>
        </div>
    </div>

    <div class="graph-stats" id="graphStats"></div>

    <div class="charts-container single-chart">
        <div class="chart-wrapper">
            <h2>Call Graph</h2>
            <div id="radarMessage" class="sys-message"></div>
            <div id="graphChart" class="chart-canvas" style="min-height: 650px;"></div>
        </div>
    </div>

    <div class="definitions-container">
        <div class="def-column">
            <h3>Call Graph Reminder</h3>
            <div class="how-to-read">
                <strong>How to read:</strong> Choose the level, then pick namespaces, classes or methods to focus on. The focus is always resolved to methods: the graph shows those methods with all their incoming and outgoing calls, then groups them into the level you chose. With no filter the whole project is drawn. Nodes with a thick border are the focus. Arrows point from caller to callee, thicker arrows are more calls. Drag nodes, use the mouse wheel to zoom.
            </div>
            <dl>
                <dt>Calls</dt>
                <dd><strong>Outside the class / namespace:</strong> only calls that cross the boundary, the real dependencies.<br>
                    <strong>Inside the same class / namespace:</strong> only calls that stay within it, shown as a &#8635; count on the node.</dd>

                <dt>Depth</dt>
                <dd>1 shows the direct neighbors. Higher values keep following the calls: incoming calls only answers "what breaks if I change this?", outgoing only answers "what does this depend on?".</dd>
            </dl>
        </div>
        <div class="def-column">
            <h3>Cycles</h3>
            <div class="how-to-read">
                <strong>How to read:</strong> Red arrows are part of a dependency cycle: A reaches B and B reaches A again, directly or through other nodes. At namespace level a cycle means the namespaces cannot be understood, tested or released separately. Only the calls drawn are checked, so a narrow filter can hide a cycle.
            </div>
            <dl>
                <dt>Native PHP functions</dt>
                <dd>Hidden by default because calls such as strlen() or count() bury the project's own structure.</dd>
            </dl>
        </div>
    </div>
</div>

<script>
    const MAX_EDGES = 1500;

    let graphInstance = null;
    let edges = [];
    let methodInfo = new Map();
    let nsWidget = null;
    let clsWidget = null;
    let mthWidget = null;

    // Variables hydrated directly from PHP GET parameters
    const preSelectedNs = <?= json_encode($preSelectedNamespaces) ?>;
    const preSelectedCls = <?= json_encode($preSelectedClasses) ?>;
    const preSelectedMth = <?= json_encode($preSelectedMethods) ?>;

    const methodId = (cls, method) => cls === '' ? method : cls + '::' + method;
    const classId = (cls) => cls === '' ? '[Global Scope]' : cls;
    const namespaceId = (cls, method) => anaNamespaceOf(cls === '' ? method : cls);

    // Node id of an edge end at each level
    const levelKeys = {
        namespace: { from: e => e.fromNs, to: e => e.toNs },
        class: { from: e => e.fromClass, to: e => e.toClass },
        method: { from: e => e.from, to: e => e.to }
    };

    // Decider: which calls a scope keeps. Boundaries are evaluated on the method level call
    const scopes = {
        all: e => true,
        outsideClass: e => !e.sameClass,
        outsideNamespace: e => !e.sameNamespace,
        insideClass: e => e.sameClass,
        insideNamespace: e => e.sameNamespace
    };

    document.addEventListener('DOMContentLoaded', () => {
        graphInstance = echarts.init(document.getElementById('graphChart'));
        window.addEventListener('resize', () => graphInstance.resize());

        document.getElementById('selLevel').value = <?= json_encode($level) ?>;
        document.getElementById('selScope').value = <?= json_encode($scope) ?>;
        document.getElementById('selDirection').value = <?= json_encode($direction) ?>;
        document.getElementById('selDepth').value = <?= json_encode((string) $depth) ?>;
        document.getElementById('btnUpdate').addEventListener('click', renderGraph);

        fetch('api/callGraph.php')
            .then(res => res.json())
            .then(rows => {
                rows.forEach(row => {
                    const edge = {
                        from: methodId(row.from_class, row.from_method),
                        to: methodId(row.to_class, row.to_method),
                        fromClass: classId(row.from_class),
                        toClass: classId(row.to_class),
                        fromNs: namespaceId(row.from_class, row.from_method),
                        toNs: namespaceId(row.to_class, row.to_method),
                        weight: row.weight,
                        isNative: row.is_builtin === 1
                    };
                    edge.sameClass = edge.fromClass === edge.toClass && edge.fromClass !== '[Global Scope]';
                    edge.sameNamespace = edge.fromNs === edge.toNs;
                    edges.push(edge);

                    if (edge.isNative) return;
                    methodInfo.set(edge.from, { cls: edge.fromClass, ns: edge.fromNs });
                    methodInfo.set(edge.to, { cls: edge.toClass, ns: edge.toNs });
                });

                const infos = [...methodInfo.values()];
                const nsArray = [...new Set(infos.map(info => info.ns))].sort();
                const clsArray = [...new Set(infos.map(info => info.cls))].sort();
                const mthArray = [...methodInfo.keys()].sort();

                nsWidget = new DualListbox('namespaceWidget', {
                    available: nsArray.filter(n => !preSelectedNs.includes(n)),
                    selected: preSelectedNs,
                    titleAvailable: 'Available Namespaces',
                    titleSelected: 'Focus'
                });
                clsWidget = new DualListbox('classWidget', {
                    available: clsArray.filter(c => !preSelectedCls.includes(c)),
                    selected: preSelectedCls,
                    titleAvailable: 'Available Classes',
                    titleSelected: 'Focus'
                });
                mthWidget = new DualListbox('methodWidget', {
                    available: mthArray.filter(m => !preSelectedMth.includes(m)),
                    selected: preSelectedMth,
                    titleAvailable: 'Available Methods',
                    titleSelected: 'Focus'
                });

                renderGraph();
            })
            .catch(err => console.error("Failed to load baseline data:", err));
    });

    // Methods chosen directly, plus every method of the chosen classes and namespaces. null = no filter
    function resolveFocus() {
        const chosenNamespaces = nsWidget.getSelected();
        const chosenClasses = clsWidget.getSelected();
        const chosenMethods = mthWidget.getSelected();
        if (chosenNamespaces.length + chosenClasses.length + chosenMethods.length === 0) return null;

        const focus = new Set();
        methodInfo.forEach((info, id) => {
            if (chosenMethods.includes(id) ||
                chosenClasses.includes(info.cls) ||
                chosenNamespaces.some(ns => info.ns === ns || info.ns.startsWith(ns + '\\'))) {
                focus.add(id);
            }
        });
        return focus;
    }

    // Keeps the calls reachable from the focus within depth steps, following the chosen direction
    function expandFromFocus(pool, focus, direction, depth) {
        const followCallees = direction !== 'callers';
        const followCallers = direction !== 'callees';
        const outgoing = new Map();
        const incoming = new Map();
        pool.forEach(edge => {
            if (!outgoing.has(edge.from)) outgoing.set(edge.from, []);
            if (!incoming.has(edge.to)) incoming.set(edge.to, []);
            outgoing.get(edge.from).push(edge);
            incoming.get(edge.to).push(edge);
        });

        const kept = new Set();
        const seen = new Set(focus);
        let frontier = [...focus];

        for (let step = 0; step < depth && frontier.length > 0; step++) {
            const next = [];
            const reach = (edge, neighbor) => {
                kept.add(edge);
                if (!seen.has(neighbor)) {
                    seen.add(neighbor);
                    next.push(neighbor);
                }
            };
            frontier.forEach(id => {
                if (followCallees) (outgoing.get(id) || []).forEach(edge => reach(edge, edge.to));
                if (followCallers) (incoming.get(id) || []).forEach(edge => reach(edge, edge.from));
            });
            frontier = next;
        }
        return [...kept];
    }

    const shortLabel = (level, key) => {
        if (level !== 'method') return key.split('\\').pop();
        const [cls, method] = key.split('::');
        return method === undefined ? cls.split('\\').pop() : cls.split('\\').pop() + '::' + method;
    };

    function renderGraph() {
        const level = document.getElementById('selLevel').value;
        const scope = document.getElementById('selScope').value;
        const direction = document.getElementById('selDirection').value;
        const depth = parseInt(document.getElementById('selDepth').value);
        const showCycles = document.getElementById('chkCycles').checked;
        const includeNative = document.getElementById('chkNative').checked;
        const msgDiv = document.getElementById('radarMessage');
        const statsDiv = document.getElementById('graphStats');
        msgDiv.innerText = '';

        let shown = edges.filter(e => (includeNative || !e.isNative) && scopes[scope](e));
        const focus = resolveFocus();
        if (focus) shown = expandFromFocus(shown, focus, direction, depth);

        // Group the method level calls into the chosen level
        const keys = levelKeys[level];
        const links = new Map();
        const nodes = new Map();
        const ensureNode = (key, ns) => {
            if (!nodes.has(key)) nodes.set(key, { key: key, ns: ns, callsOut: 0, callsIn: 0, isFocus: false, selfCalls: 0 });
            return nodes.get(key);
        };
        shown.forEach(edge => {
            const from = keys.from(edge);
            const to = keys.to(edge);
            // ECharts draws no loops: calls that stay inside the node are shown on the node itself
            if (from === to) {
                ensureNode(from, edge.fromNs).selfCalls += edge.weight;
                return;
            }
            const linkKey = from + '\u0000' + to;
            if (!links.has(linkKey)) links.set(linkKey, { from: from, to: to, weight: 0, pairs: 0 });
            const link = links.get(linkKey);
            link.weight += edge.weight;
            link.pairs++;
            ensureNode(from, edge.fromNs).callsOut += edge.weight;
            ensureNode(to, edge.toNs).callsIn += edge.weight;
        });

        if (focus) {
            focus.forEach(id => {
                const info = methodInfo.get(id);
                const key = level === 'method' ? id : (level === 'class' ? info.cls : info.ns);
                if (nodes.has(key)) nodes.get(key).isFocus = true;
            });
        }

        const selfCalls = [...nodes.values()].reduce((sum, node) => sum + node.selfCalls, 0);
        statsDiv.innerText = `${nodes.size} nodes, ${links.size} links` + (selfCalls > 0 ? `, ${selfCalls} calls inside nodes (shown as \u21BB)` : '') + ` from ${shown.length} method level calls` +
            (focus ? ` (focus: ${focus.size} methods)` : ' (no filter: whole project)');

        if (nodes.size === 0) {
            msgDiv.innerText = 'No calls match the current filters.';
            graphInstance.clear();
            return;
        }
        if (links.size > MAX_EDGES) {
            msgDiv.innerText = `Too many links to draw (${links.size}, limit ${MAX_EDGES}). Pick a focus, a higher level or a narrower scope.`;
            graphInstance.clear();
            return;
        }

        const cycleMap = showCycles ? anaCycleNodes([...links.values()].map(link => [link.from, link.to])) : new Map();
        const isCycleLink = link => link.from !== link.to && cycleMap.has(link.from) && cycleMap.get(link.from) === cycleMap.get(link.to);

        const namespaces = [...new Set([...nodes.values()].map(node => node.ns))].sort();
        const nodeList = [...nodes.values()];

        const option = {
            toolbox: { feature: { saveAsImage: { name: 'call_graph', title: 'Download Image' } } },
            legend: { type: 'scroll', bottom: 0, data: namespaces },
            tooltip: {
                formatter: function (param) {
                    if (param.dataType === 'edge') {
                        const link = param.data.link;
                        return `${link.from}<br/>&rarr; ${link.to}<br/>${link.weight} calls (${link.pairs} method level links)` +
                            (isCycleLink(link) ? '<br/><strong style="color:#dc3545">part of a cycle</strong>' : '');
                    }
                    const node = param.data.node;
                    return `<strong>${node.key}</strong><br/>Calls made: ${node.callsOut}<br/>Calls received: ${node.callsIn}<br/>Calls inside the node: ${node.selfCalls}` +
                        (cycleMap.has(node.key) ? '<br/><strong style="color:#dc3545">in a cycle</strong>' : '');
                }
            },
            series: [{
                type: 'graph',
                layout: 'force',
                roam: true,
                draggable: true,
                edgeSymbol: ['none', 'arrow'],
                edgeSymbolSize: 8,
                categories: namespaces.map(ns => ({ name: ns })),
                force: { repulsion: 220, edgeLength: [60, 180], gravity: 0.08 },
                label: { show: true, position: 'right', formatter: param => shortLabel(level, param.data.node.key) + (param.data.node.selfCalls > 0 ? ' \u21BB' + param.data.node.selfCalls : ''), fontSize: 11 },
                emphasis: { focus: 'adjacency' },
                lineStyle: { opacity: 0.7, curveness: 0.15 },
                data: nodeList.map(node => ({
                    name: node.key,
                    node: node,
                    category: namespaces.indexOf(node.ns),
                    symbolSize: Math.min(10 + Math.sqrt(node.callsIn + node.callsOut + node.selfCalls) * 4, 50),
                    itemStyle: node.isFocus ? { borderColor: '#000', borderWidth: 3 } : {}
                })),
                links: [...links.values()].map(link => ({
                    source: link.from,
                    target: link.to,
                    link: link,
                    lineStyle: {
                        width: Math.min(1 + Math.log2(link.weight), 6),
                        color: isCycleLink(link) ? '#dc3545' : '#6c757d'
                    }
                }))
            }]
        };
        graphInstance.setOption(option, true);
    }
</script>
</body>
</html>
