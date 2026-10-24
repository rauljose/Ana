<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Metrics</title>
    <link rel="stylesheet" href="css/ana.css">
    <?php require __DIR__ . '/partials/tabulator_head.php'; ?>
</head>
<body class="ana-table-page">

<?php require __DIR__ . '/partials/nav.php'; ?>

<div class="ana-toolbar">
    <h1>Class Metrics</h1>
    <button id="anaClear">Clear filters</button>
    <button id="anaCsv">Export CSV</button>
    <button id="anaLayout">Layout...</button>
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <label><input type="checkbox" id="chkGroup"> Group by namespace</label>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-presets" id="anaPresets"></div>
<div class="ana-preset-note" id="anaPresetNote"></div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Hover a header for the metric definition.</div>

<div class="ana-table-wrap"><div id="classTable"></div></div>

<script>
    const table = new Tabulator('#classTable', {
        ajaxURL: 'api/classSummary.php',
        // Derived columns, computed here from the API fields
        ajaxResponse: (url, params, response) => response.map(row => {
            const abstractness = ['Interface', 'Abstract Class'].includes(row.type) ? 1 : 0;
            return {
                ...row,
                hotspot_score: row.total_cyclo * row.afferent_ca,
                abstractness: abstractness,
                main_seq_distance: Math.round(Math.abs(abstractness + parseFloat(row.instability) - 1) * 100) / 100,
                is_unused_candidate: row.afferent_ca === 0 && row.children_count === 0 && ['Class', 'Final Class', 'Abstract Class'].includes(row.type),
            };
        }),
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'total_cyclo', dir: 'desc' }],
        groupStartOpen: true,
        groupToggleElement: 'header',
        groupHeader: (value, count, data) => {
            const sum = (field) => data.reduce((total, row) => total + row[field], 0);
            return `${value} <span style="font-weight:normal;color:#495057">&mdash; ${count} types, ${sum('total_methods')} methods, cyclomatic total ${sum('total_cyclo')}</span>`;
        },
        columns: [
            anaCol.rowNumber(),
            anaCol.detail('Class', 'short_name', 'Class name without namespace. Click for the class detail page', 'class', 'class_name', { frozen: true }),
            {
                title: 'Identity', columns: [
                    anaCol.text('Namespace', 'namespace', 'Namespace of the class'),
                    anaCol.text('FQN', 'class_name', 'Fully qualified class name'),
                    anaCol.list('Type', 'type', 'Class, Abstract Class, Final Class, Interface or Trait'),
                    anaCol.flag('Static class', 'is_static_class', 'Only static methods'),
                    anaCol.flag('Readonly class', 'is_readonly_class', 'Declared readonly'),
                ]
            },
            {
                title: 'Inheritance', columns: [
                    anaCol.text('Extends', 'extends', 'Parent class'),
                    anaCol.text('Implements', 'implements', 'Implemented interfaces'),
                    anaCol.text('Uses traits', 'uses_traits', 'Traits used by the class'),
                    anaCol.num('Children', 'children_count', 'Number of classes extending this one'),
                ]
            },
            {
                title: 'Methods', columns: [
                    anaCol.num('Total', 'total_methods', 'Total methods'),
                    anaCol.num('Public', 'public_methods', 'Public methods'),
                    anaCol.num('Protected', 'protected_methods', 'Protected methods'),
                    anaCol.num('Private', 'private_methods', 'Private methods'),
                    anaCol.num('Pure %', 'pure_methods_pct', 'Percentage of methods that do not modify internal or global state. Higher is better'),
                ]
            },
            {
                title: 'Complexity', columns: [
                    anaCol.num('Cyclo total', 'total_cyclo', 'Sum of cyclomatic complexity of all methods'),
                    anaCol.num('Cyclo avg', 'avg_cyclo', 'Average cyclomatic complexity per method'),
                    anaCol.text('Cyclo max method', 'max_cyclo_method', 'Method with the highest cyclomatic complexity'),
                    anaCol.num('Cyclo max', 'max_cyclo_val', 'Cyclomatic complexity of that method'),
                    anaCol.num('Cognitive total', 'total_cog', 'Sum of cognitive complexity of all methods'),
                    anaCol.num('Cognitive avg', 'avg_cog', 'Average cognitive complexity per method'),
                    anaCol.text('Cognitive max method', 'max_cog_method', 'Method with the highest cognitive complexity'),
                    anaCol.num('Cognitive max', 'max_cog_val', 'Cognitive complexity of that method'),
                    anaCol.num('Hotspot score', 'hotspot_score', 'Cyclomatic total x Ca. Complex classes that many classes depend on are the riskiest to change'),
                ]
            },
            {
                title: 'Parameters', columns: [
                    anaCol.num('Constructor params', 'constructor_params', 'Number of constructor parameters'),
                    anaCol.num('By-reference params', 'total_reference_params', 'Parameters passed by reference, all methods'),
                    anaCol.num('Object params', 'total_object_params', 'Parameters typed with a class, all methods'),
                    anaCol.num('Ending methods', 'methods_ending_script', 'Methods that end the script (exit / die)'),
                    anaCol.num('Sensitive params', 'methods_with_sensitive_params', 'Methods with a #[SensitiveParameter] parameter'),
                ]
            },
            {
                title: 'Coupling', columns: [
                    anaCol.num('Ca', 'afferent_ca', 'Afferent coupling: classes that use this class'),
                    anaCol.num('Ce', 'efferent_ce', 'Efferent coupling: classes this class uses'),
                    anaCol.num('Instability', 'instability', 'Ce / (Ca + Ce). 0 = stable, 1 = unstable'),
                    anaCol.num('MPC', 'mpc', 'Message passing coupling: calls to methods of other classes'),
                    anaCol.num('DAC', 'dac', 'Data abstraction coupling: properties typed with a class'),
                    anaCol.num('Abstractness', 'abstractness', '1 for interfaces and abstract classes, 0 for concrete classes'),
                    anaCol.num('Main seq. distance', 'main_seq_distance', '|Abstractness + Instability - 1|. Near 0 is balanced; near 1 is rigid (stable and concrete) or useless (unstable and abstract)'),
                    anaCol.text('Classes used', 'classes_used', 'Classes this class uses'),
                    anaCol.text('Classes using this', 'classes_using_this', 'Classes that use this class'),
                ]
            },
            {
                title: 'State & Cohesion', columns: [
                    anaCol.num('LCOM', 'lcom', 'Lack of cohesion of methods. 0 = cohesive, higher = should be split'),
                    anaCol.num('Mutable props', 'mutable_properties', 'Properties that are neither readonly nor constants'),
                    anaCol.num('Readonly props', 'readonly_properties', 'Readonly properties and constants'),
                    anaCol.num('Props written', 'properties_actually_written', 'Distinct properties written by some method'),
                    anaCol.flag('Superglobals', 'uses_superglobals', 'Uses $_GET, $_POST, $_SESSION...'),
                    anaCol.flag('global keyword', 'uses_global_keyword', 'Uses the global keyword'),
                    anaCol.flag('Constants', 'uses_constants', 'Uses global constants'),
                ]
            },
            {
                title: 'Archetypes', columns: [
                    anaCol.flag('God class', 'is_god_class', 'LCOM >= 0.8, cyclomatic total > 40 and Ce > 10'),
                    anaCol.flag('Data class', 'is_data_class', 'More than 2 mutable properties, at most 3 methods, avg cyclomatic <= 1.5'),
                    anaCol.flag('Stateless service', 'is_stateless_service', 'No mutable properties and more than 80% pure methods'),
                    anaCol.flag('Unused candidate', 'is_unused_candidate', 'Concrete class nothing calls and nothing extends. Classes used only through type hints, instanceof or dynamic names also look unused'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'class_metrics.csv');
    anaInitLayout(table, 'class_metrics', [{ column: 'total_cyclo', dir: 'desc' }]);
    document.getElementById('chkGroup').addEventListener('change', (event) => {
        table.setGroupBy(event.target.checked ? 'namespace' : false);
    });
    anaInitPresets(table, [
        { label: 'Hotspots', tip: 'Complex classes many classes depend on, by cyclomatic total x Ca', filter: row => row.hotspot_score > 0, sort: [{ column: 'hotspot_score', dir: 'desc' }] },
        { label: 'Smells', tip: 'God classes and data classes', filter: row => row.is_god_class || row.is_data_class, sort: [{ column: 'total_cyclo', dir: 'desc' }] },
        { label: 'Unused candidates', tip: 'Concrete classes nothing calls or extends. Verify before deleting: type hints and dynamic use are not counted', filter: row => row.is_unused_candidate, sort: [{ column: 'total_methods', dir: 'desc' }] },
        { label: 'Main sequence', tip: 'Classes furthest from the balance between abstractness and instability', filter: row => row.main_seq_distance >= 0.7, sort: [{ column: 'main_seq_distance', dir: 'desc' }] },
        { label: 'Inheritance', tip: 'Classes that other classes extend, by number of children', filter: row => row.children_count > 0, sort: [{ column: 'children_count', dir: 'desc' }] },
        { label: 'Global state', tip: 'Classes using superglobals or the global keyword', filter: row => row.uses_superglobals || row.uses_global_keyword, sort: [{ column: 'total_cyclo', dir: 'desc' }] },
    ]);
</script>
</body>
</html>
