<?php
$preSelectedNamespaces = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['namespace'] ?? ''))));
$preSelectedClasses = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['classname'] ?? ''))));
$preSelectedMethods = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['method'] ?? ''))));
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Method Metrics Graphs</title>

    <!-- External Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <link rel="stylesheet" href="css/ana.css">

    <!-- Reusable Dual Listbox Widget -->
    <link rel="stylesheet" href="js/DualListbox/DualListbox.css">
    <script src="js/DualListbox/DualListbox.js"></script>

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
    </style>
</head>
<body>

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="top-controls">
    <div class="checkbox-group">
        <label><input type="checkbox" id="chkScatter" checked> Show Method Hotspots (Scatter)</label>
        <label><input type="checkbox" id="chkRadar" checked> Show Method Health Radar</label>
    </div>
    <button id="btnUpdate" class="primary-btn">Render Graphs</button>
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

    <!-- Charts -->
    <div class="charts-container" id="chartsContainer">
        <div class="chart-wrapper" id="wrapScatter">
            <h2>Method Hotspots (Fan-out vs Complexity)</h2>
            <div id="scatterChart" class="chart-canvas"></div>
        </div>

        <div class="chart-wrapper" id="wrapRadar">
            <h2>Method Health Radar</h2>
            <div id="radarMessage" class="sys-message"></div>
            <div id="radarChart" class="chart-canvas"></div>
        </div>
    </div>

    <!-- Definitions and Reminders -->
    <div class="definitions-container">
        <div class="def-column">
            <h3>Method Hotspots (Scatter Plot) Reminder</h3>
            <div class="how-to-read">
                <strong>How to read:</strong> Look for dots in the top-right corner. Blue dots are safe, yellow dots need monitoring, and red dots are complex methods that also call many other methods, so they are brittle and hard to test. Bigger dots are called by more methods, so changing them has a wider impact. You can use the mouse wheel to zoom in, and hover over dots for exact numbers.
            </div>
            <dl>
                <dt>X-Axis: Fan-out</dt>
                <dd>The number of calls the method makes to other methods and functions. High fan-out means the method depends on a lot of code.</dd>

                <dt>Y-Axis: Cyclomatic Complexity</dt>
                <dd>The number of independent execution paths (if, else, loops, switches) in the method. High complexity makes a method difficult to test and understand.</dd>

                <dt>Dot Size: Fan-in</dt>
                <dd>The number of calls made to this method from elsewhere.</dd>

                <dt>Dot Color (Risk Zones)</dt>
                <dd><strong>Blue:</strong> Low risk.<br>
                    <strong>Yellow:</strong> Warning (Fan-out > 5 & Complexity > 10).<br>
                    <strong>Red:</strong> Hotspot (Fan-out > 10 & Complexity > 15).</dd>
            </dl>
        </div>

        <div class="def-column">
            <h3>Method Health Radar Reminder</h3>
            <div class="how-to-read">
                <strong>How to read:</strong> For every axis lower is better, so ideal methods draw a tight shape near the center. Wild spikes indicate methods that are hard to understand, change or reuse. Toggle method names in the legend to show/hide specific overlays.
            </div>
            <dl>
                <dt>Cyclomatic / Cognitive</dt>
                <dd>Cyclomatic counts execution paths. Cognitive also penalizes nesting, so it tracks how hard the method is to read.</dd>

                <dt>Fan-out / Fan-in</dt>
                <dd><strong>Fan-out:</strong> Calls the method makes.<br><strong>Fan-in:</strong> Calls made to the method. High fan-in and high complexity together mean a risky method to change.</dd>

                <dt>Parameters</dt>
                <dd>Long parameter lists are hard to call correctly and often hide a missing object.</dd>

                <dt>Props written / Globals used</dt>
                <dd>Properties the method modifies and superglobals, global keyword or constants it touches. Both are hidden inputs and side effects.</dd>
            </dl>
        </div>
    </div>
</div>

<script>
    // --- Application Logic ---
    let scatterInstance = null;
    let radarInstance = null;
    let methodRows = [];
    let nsWidget = null;
    let clsWidget = null;
    let mthWidget = null;

    // Variables hydrated directly from PHP GET parameters
    const preSelectedNs = <?= json_encode($preSelectedNamespaces) ?>;
    const preSelectedCls = <?= json_encode($preSelectedClasses) ?>;
    const preSelectedMth = <?= json_encode($preSelectedMethods) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        scatterInstance = echarts.init(document.getElementById('scatterChart'));
        radarInstance = echarts.init(document.getElementById('radarChart'));

        window.addEventListener('resize', () => {
            scatterInstance.resize();
            radarInstance.resize();
        });

        document.getElementById('btnUpdate').addEventListener('click', updateGraphs);
        document.getElementById('chkScatter').addEventListener('change', toggleLayout);
        document.getElementById('chkRadar').addEventListener('change', toggleLayout);

        // Fetch the baseline data to populate the Widgets, the Scatter plot and the Radar
        fetch('api/methodSummary.php')
            .then(res => res.json())
            .then(data => {
                // Native PHP functions have no code to measure
                methodRows = data.filter(row => row.kind !== 'Native Function');

                const nsArray = [...new Set(methodRows.map(row => row.namespace))].sort();
                const clsArray = [...new Set(methodRows.filter(row => row.kind === 'Method').map(row => row.class_name))].sort();
                const mthArray = methodRows.map(row => row.fqn).sort();

                // Filter out the pre-selected items so they don't appear in both columns
                nsWidget = new DualListbox('namespaceWidget', {
                    available: nsArray.filter(n => !preSelectedNs.includes(n)),
                    selected: preSelectedNs,
                    titleAvailable: 'Available Namespaces',
                    titleSelected: 'Show in Graph'
                });

                clsWidget = new DualListbox('classWidget', {
                    available: clsArray.filter(c => !preSelectedCls.includes(c)),
                    selected: preSelectedCls,
                    titleAvailable: 'Available Classes',
                    titleSelected: 'Show in Graph'
                });

                mthWidget = new DualListbox('methodWidget', {
                    available: mthArray.filter(m => !preSelectedMth.includes(m)),
                    selected: preSelectedMth,
                    titleAvailable: 'Available Methods',
                    titleSelected: 'Show in Graph'
                });

                // Render graphs immediately
                updateGraphs();
            })
            .catch(err => console.error("Failed to load baseline data:", err));
    });

    function toggleLayout() {
        const showScatter = document.getElementById('chkScatter').checked;
        const showRadar = document.getElementById('chkRadar').checked;
        const wrapScatter = document.getElementById('wrapScatter');
        const wrapRadar = document.getElementById('wrapRadar');
        const container = document.getElementById('chartsContainer');

        wrapScatter.classList.toggle('hidden', !showScatter);
        wrapRadar.classList.toggle('hidden', !showRadar);

        if (showScatter !== showRadar) {
            container.classList.add('single-chart');
        } else {
            container.classList.remove('single-chart');
        }

        setTimeout(() => {
            if (showScatter) scatterInstance.resize();
            if (showRadar) radarInstance.resize();
        }, 50);
    }

    // Returns the selected methods: chosen methods, plus every method of the chosen classes and namespaces
    function resolveTargetMethods() {
        if (!nsWidget || !clsWidget || !mthWidget) return [];

        const chosenNamespaces = nsWidget.getSelected();
        const chosenClasses = clsWidget.getSelected();
        const chosenMethods = mthWidget.getSelected();

        return methodRows.filter(row =>
            chosenMethods.includes(row.fqn) ||
            chosenClasses.includes(row.class_name) ||
            chosenNamespaces.some(ns => row.namespace === ns || row.namespace.startsWith(ns + '\\'))
        );
    }

    function updateGraphs() {
        const showScatter = document.getElementById('chkScatter').checked;
        const showRadar = document.getElementById('chkRadar').checked;
        const targets = resolveTargetMethods();
        const isFiltered = targets.length > 0;

        if (showScatter) renderScatter(targets, isFiltered);
        if (showRadar) renderRadar(targets, isFiltered);
    }

    function renderScatter(targets, isFiltered) {
        const displayRows = isFiltered ? targets : methodRows;

        // ECharts standard scatter format: [X-value, Y-value, Name, Fan-in, Cognitive]
        const displayData = displayRows.map(row => [
            Number(row.calls_out_total),
            Number(row.cyclo_complexity),
            row.fqn,
            Number(row.calls_in_total),
            Number(row.cognitive_complexity)
        ]);

        const option = {
            grid: { bottom: 80 },
            toolbox: { feature: { saveAsImage: { name: 'method_hotspots', title: 'Download Image' } } },
            dataZoom: [ { type: 'inside' }, { type: 'slider', bottom: 10 } ],
            tooltip: {
                trigger: 'item',
                formatter: function (param) {
                    return `<strong>${param.data[2]}</strong><br/>
                                Fan-out: ${param.data[0]}<br/>
                                Cyclomatic Complexity: ${param.data[1]}<br/>
                                Cognitive Complexity: ${param.data[4]}<br/>
                                Fan-in: ${param.data[3]}`;
                }
            },
            xAxis: { name: 'Fan-out (calls made)', nameLocation: 'middle', nameGap: 30, type: 'value', splitLine: { show: false } },
            yAxis: { name: 'Cyclomatic Complexity', nameLocation: 'middle', nameGap: 40, type: 'value', splitLine: { show: true, lineStyle: { type: 'dashed', color: '#eee' } } },
            series: [{
                type: 'scatter',
                symbolSize: function (data) { return Math.min(8 + data[3] * 2, 30); },
                itemStyle: {
                    color: function(param) {
                        if (param.data[0] > 10 && param.data[1] > 15) return '#dc3545';
                        if (param.data[0] > 5 && param.data[1] > 10) return '#ffc107';
                        return '#0d6efd';
                    }
                },
                data: displayData
            }]
        };
        scatterInstance.setOption(option, true);
    }

    function renderRadar(targets, isFiltered) {
        const msgDiv = document.getElementById('radarMessage');

        if (!isFiltered) {
            msgDiv.innerText = "Radar chart requires at least one namespace, class or method to be filtered.";
            radarInstance.clear();
            return;
        }

        if (targets.length > 6) {
            msgDiv.innerText = `Warning: Rendering ${targets.length} methods on a radar chart will be hard to read. Consider narrowing your filter.`;
        } else {
            msgDiv.innerText = "";
        }

        const schema = ['Cyclomatic', 'Cognitive', 'Fan-out', 'Fan-in', 'Parameters', 'Props written', 'Globals used'];
        const data = targets.map(row => ({
            name: row.fqn,
            value: [
                Number(row.cyclo_complexity), Number(row.cognitive_complexity),
                Number(row.calls_out_total), Number(row.calls_in_total),
                Number(row.num_parameters), Number(row.props_written), Number(row.uses_globals)
            ]
        }));

        const option = {
            toolbox: { feature: { saveAsImage: { name: 'method_health_radar', title: 'Download Image' } } },
            tooltip: { trigger: 'item' },
            legend: { type: 'scroll', bottom: 0, data: data.map(item => item.name) },
            radar: { indicator: schema.map(name => ({ name: name })), shape: 'polygon', splitArea: { show: false }, axisName: { color: '#495057' } },
            series: [{ type: 'radar', symbol: 'none', lineStyle: { width: 2 }, areaStyle: { opacity: 0.1 }, data: data }]
        };
        radarInstance.setOption(option, true);
    }
</script>
</body>
</html>
