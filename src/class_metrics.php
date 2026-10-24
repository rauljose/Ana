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
    <details class="ana-columns">
        <summary>Columns</summary>
        <div class="ana-columns-list" id="anaColumnsList"></div>
    </details>
    <span class="ana-status" id="anaStatus">Loading...</span>
</div>
<div class="ana-filter-hint">Click a header to sort (shift+click for multi-sort). Text filters match any part, case insensitive. Number filters accept 5, &gt;5, &gt;=5, &lt;5, &lt;=5, !=5 or a range 2-8. Hover a header for the metric definition.</div>

<div class="ana-table-wrap"><div id="classTable"></div></div>

<script>
    const table = new Tabulator('#classTable', {
        ajaxURL: 'api/classSummary.php',
        layout: 'fitDataFill',
        movableColumns: true,
        initialSort: [{ column: 'total_cyclo', dir: 'desc' }],
        columns: [
            anaCol.text('Class', 'short_name', 'Class name without namespace', { frozen: true }),
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
                ]
            },
        ],
    });

    anaInitToolbar(table, 'class_metrics.csv');
</script>
</body>
</html>
