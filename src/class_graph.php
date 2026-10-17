<?php
$preSelectedNamespaces = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['namespace'] ?? ''))));
$preSelectedClasses = array_values(array_filter(array_map('trim', explode(',',$_REQUEST['classname'] ?? ''))));
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Metrics Graphs</title>

    <!-- External Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

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
            grid-template-columns: 1fr 1fr;
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

<header>
    <a href="index.php">&larr; Home</a>
    <h1>Class Metrics Graphs</h1>
</header>

<div class="top-controls">
    <div class="checkbox-group">
        <label><input type="checkbox" id="chkScatter" checked> Show Zone of Pain (Scatter)</label>
        <label><input type="checkbox" id="chkRadar" checked> Show Health Radar</label>
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
            <h3>Filter by Specific Class</h3>
            <div id="classWidget"></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-container" id="chartsContainer">
        <div class="chart-wrapper" id="wrapScatter">
            <h2>Zone of Pain (Coupling vs Complexity)</h2>
            <div id="scatterChart" class="chart-canvas"></div>
        </div>

        <div class="chart-wrapper" id="wrapRadar">
            <h2>Class Health Radar</h2>
            <div id="radarMessage" class="sys-message"></div>
            <div id="radarChart" class="chart-canvas"></div>
        </div>
    </div>

    <!-- Definitions and Reminders -->
    <div class="definitions-container">
        <div class="def-column">
            <h3>Zone of Pain (Scatter Plot) Reminder</h3>
            <div class="how-to-read">
                <strong>How to read:</strong> Look for dots in the top-right corner. These are the danger zones. Blue dots are safe, yellow dots need monitoring, and red dots belong to highly coupled, overly complex classes that are brittle and hard to maintain. You can use the mouse wheel to zoom in, and hover over dots for exact numbers.
            </div>
            <dl>
                <dt>X-Axis: Efferent Coupling (Ce)</dt>
                <dd>The number of external classes that a given class depends on. High Ce means the class is vulnerable to cascading changes from elsewhere.</dd>

                <dt>Y-Axis: Total Cyclomatic Complexity</dt>
                <dd>The sum of all execution paths (if, else, loops, switches) across all methods in the class. High complexity makes a class difficult to test and understand.</dd>

                <dt>Dot Color (Risk Zones)</dt>
                <dd><strong>Blue:</strong> Low risk.<br>
                    <strong>Yellow:</strong> Warning (Ce > 5 & Complexity > 20).<br>
                    <strong>Red:</strong> Zone of Pain (Ce > 10 & Complexity > 40).</dd>
            </dl>
        </div>

        <div class="def-column">
            <h3>Class Health Radar Reminder</h3>
            <div class="how-to-read">
                <strong>How to read:</strong> Ideal classes have a tight shape leaning toward 0 for LCOM, Ce, and Ca, and 100 for Purity. Wild spikes indicate structural imbalances. Hover over the legend or polygons for specific metric balloons. Toggle class names in the legend to show/hide specific overlays.
            </div>
            <dl>
                <dt>LCOM (Lack of Cohesion of Methods)</dt>
                <dd>Measures how strongly methods relate to class properties. Lower is better (0 = perfect cohesion). High LCOM suggests the class is doing too many unrelated things and should be split.</dd>

                <dt>Purity %</dt>
                <dd>The percentage of methods in the class that do not modify internal or global state. Higher is better for predictable architecture.</dd>

                <dt>Avg Complexity</dt>
                <dd>The average cyclomatic complexity per method. Helps identify massive "God methods" versus uniform complexity.</dd>

                <dt>Efferent (Ce) / Afferent (Ca)</dt>
                <dd><strong>Ce:</strong> Outbound dependencies (what this class relies on).<br><strong>Ca:</strong> Inbound dependencies (how many classes rely on this one). High Ca + High Ce indicates a severe bottleneck.</dd>
            </dl>
        </div>
    </div>
</div>

<script>
    // --- Application Logic ---
    let scatterInstance = null;
    let radarInstance = null;
    let globalClassList = [];
    let rawScatterData = [];
    let nsWidget = null;
    let clsWidget = null;

    // Variables hydrated directly from PHP GET parameters
    const preSelectedNs = <?= json_encode($preSelectedNamespaces) ?>;
    const preSelectedCls = <?= json_encode($preSelectedClasses) ?>;

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

        // Fetch the baseline data to populate the Widgets and the Scatter plot
        fetch('classMermaid.php?action=6')
            .then(res => res.json())
            .then(data => {
                rawScatterData = data;
                globalClassList = data.map(row => row[2]);

                // Derive unique namespaces from the class list
                let allNamespaces = new Set();
                globalClassList.forEach(cls => {
                    const lastSlash = cls.lastIndexOf('\\');
                    if (lastSlash > 0) {
                        allNamespaces.add(cls.substring(0, lastSlash));
                    }
                });

                const nsArray = Array.from(allNamespaces).sort();
                const clsArray = [...globalClassList].sort();

                // Filter out the pre-selected items so they don't appear in both columns
                const availNs = nsArray.filter(n => !preSelectedNs.includes(n));
                const availCls = clsArray.filter(c => !preSelectedCls.includes(c));

                // Initialize Widgets using the external DualListbox class
                nsWidget = new DualListbox('namespaceWidget', {
                    available: availNs,
                    selected: preSelectedNs,
                    titleAvailable: 'Available Namespaces',
                    titleSelected: 'Show in Graph'
                });

                clsWidget = new DualListbox('classWidget', {
                    available: availCls,
                    selected: preSelectedCls,
                    titleAvailable: 'Available Classes',
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

    function resolveTargetClasses() {
        if (!nsWidget || !clsWidget) return [];

        let targets = new Set();
        let chosenNamespaces = nsWidget.getSelected();
        let chosenClasses = clsWidget.getSelected();

        chosenClasses.forEach(c => targets.add(c));

        if (chosenNamespaces.length > 0) {
            globalClassList.forEach(className => {
                chosenNamespaces.forEach(ns => {
                    if (className.startsWith(ns + '\\') || className === ns) {
                        targets.add(className);
                    }
                });
            });
        }

        return Array.from(targets);
    }

    function updateGraphs() {
        const showScatter = document.getElementById('chkScatter').checked;
        const showRadar = document.getElementById('chkRadar').checked;
        const targets = resolveTargetClasses();
        const isFiltered = targets.length > 0;

        if (showScatter) renderScatter(targets, isFiltered);
        if (showRadar) renderRadar(targets, isFiltered);
    }

    function renderScatter(targets, isFiltered) {
        let displayData = rawScatterData;
        if (isFiltered) {
            displayData = rawScatterData.filter(row => targets.includes(row[2]));
        }

        const option = {
            grid: { bottom: 80 },
            toolbox: { feature: { saveAsImage: { name: 'zone_of_pain', title: 'Download Image' } } },
            dataZoom: [ { type: 'inside' }, { type: 'slider', bottom: 10 } ],
            tooltip: {
                trigger: 'item',
                formatter: function (param) {
                    return `<strong>${param.data[2]}</strong><br/>
                                Efferent Coupling (Ce): ${param.data[0]}<br/>
                                Cyclomatic Complexity: ${param.data[1]}`;
                }
            },
            xAxis: { name: 'Efferent Coupling (Ce)', nameLocation: 'middle', nameGap: 30, type: 'value', splitLine: { show: false } },
            yAxis: { name: 'Total Cyclomatic Complexity', nameLocation: 'middle', nameGap: 40, type: 'value', splitLine: { show: true, lineStyle: { type: 'dashed', color: '#eee' } } },
            series: [{
                type: 'scatter',
                symbolSize: 10,
                itemStyle: {
                    color: function(param) {
                        if (param.data[0] > 10 && param.data[1] > 40) return '#dc3545';
                        if (param.data[0] > 5 && param.data[1] > 20) return '#ffc107';
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
            msgDiv.innerText = "Radar chart requires at least one namespace or class to be filtered.";
            radarInstance.clear();
            return;
        }

        if (targets.length > 6) {
            msgDiv.innerText = `Warning: Rendering ${targets.length} classes on a radar chart will be hard to read. Consider narrowing your filter.`;
        } else {
            msgDiv.innerText = "";
        }

        const qs = targets.map(encodeURIComponent).join(',');
        fetch(`classMermaid.php?action=7&classes=${qs}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    msgDiv.innerText = data.error;
                    radarInstance.clear();
                    return;
                }

                const indicators = data.schema.map(name => ({ name: name }));
                const option = {
                    toolbox: { feature: { saveAsImage: { name: 'class_health_radar', title: 'Download Image' } } },
                    tooltip: { trigger: 'item' },
                    legend: { type: 'scroll', bottom: 0, data: data.data.map(item => item.name) },
                    radar: { indicator: indicators, shape: 'polygon', splitArea: { show: false }, axisName: { color: '#495057' } },
                    series: [{ type: 'radar', symbol: 'none', lineStyle: { width: 2 }, areaStyle: { opacity: 0.1 }, data: data.data }]
                };
                radarInstance.setOption(option, true);
            })
            .catch(err => {
                console.error("Radar fetch failed:", err);
                msgDiv.innerText = "Error loading radar data.";
            });
    }
</script>
</body>
</html>