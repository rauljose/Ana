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
    <input type="search" id="anaFind" placeholder="Find a column...">
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

<section class="ana-guide">
    <h2>Column guide</h2>
    <div id="anaGuide"></div>
</section>

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
            anaCol.detail('Class', 'short_name', 'Class name without namespace. Click it to open the class detail page.', 'class', 'class_name', { frozen: true }),
            {
                title: 'Identity', columns: [
                    anaCol.text('Namespace', 'namespace', 'Namespace of the class. Use it to compare or group classes of the same package.'),
                    anaCol.text('FQN', 'class_name', 'Fully qualified class name, the unique key of the class.'),
                    anaCol.list('Type', 'type', 'Class, Abstract Class, Final Class, Interface or Trait. Interfaces and traits hold no state of their own, so some metrics mean less for them.'),
                    anaCol.flag('Static class', 'is_static_class', 'True when the class only has static methods. Such a class cannot be substituted or mocked easily, many of them point to procedural code.'),
                    anaCol.flag('Readonly class', 'is_readonly_class', 'Declared readonly: all properties are immutable after construction. Generally a good sign.'),
                    anaCol.text('Doc comment', 'doc_comment', 'Doc block written above the class. Empty means the class is undocumented.'),
                ]
            },
            {
                title: 'Inheritance', columns: [
                    anaCol.text('Extends', 'extends', 'Parent class. Deep inheritance chains make behaviour hard to follow.'),
                    anaCol.text('Implements', 'implements', 'Interfaces the class implements. Depending on interfaces instead of classes keeps coupling low.'),
                    anaCol.text('Uses traits', 'uses_traits', 'Traits mixed into the class. Traits hide dependencies; many of them can mean shared code that should be a class.'),
                    anaCol.num('Children', 'children_count', 'Classes that extend this one. A high number means changes here ripple to many classes.'),
                ]
            },
            {
                title: 'Methods', columns: [
                    anaCol.num('Total', 'total_methods', 'Number of methods. Very high values suggest a class with too many responsibilities.'),
                    anaCol.num('Public', 'public_methods', 'Public methods, the API other code can use. Fewer is easier to keep stable.'),
                    anaCol.num('Protected', 'protected_methods', 'Protected methods, available to subclasses.'),
                    anaCol.num('Private', 'private_methods', 'Private methods, internal helpers. A high share compared to public is normal in well encapsulated classes.'),
                    anaCol.num('Pure %', 'pure_methods_pct', 'Share of methods without side effects. Higher is better: easier to test and reason about.'),
                ]
            },
            {
                title: 'Complexity', columns: [
                    anaCol.num('Cyclo total', 'total_cyclo', 'Sum of the cyclomatic complexity of all methods, the number of independent paths to test. Above about 40 the class is hard to test and understand.'),
                    anaCol.num('Cyclo avg', 'avg_cyclo', 'Average complexity per method. Up to 5 is simple; above 10 means methods that should be split.'),
                    anaCol.text('Cyclo max method', 'max_cyclo_method', 'Name of the most complex method: where to start refactoring.'),
                    anaCol.num('Cyclo max', 'max_cyclo_val', 'Complexity of that method. Over 15 is a refactoring candidate, over 30 is very risky.'),
                    anaCol.num('Cognitive total', 'total_cog', 'Sum of cognitive complexity: how hard the code is to read, nesting counts extra. Higher is worse.'),
                    anaCol.num('Cognitive avg', 'avg_cog', 'Average cognitive complexity per method. Above about 8 the methods are tiring to read.'),
                    anaCol.text('Cognitive max method', 'max_cog_method', 'Name of the hardest to read method.'),
                    anaCol.num('Cognitive max', 'max_cog_val', 'Cognitive complexity of that method. 15 or more is hard to read.'),
                    anaCol.num('Hotspot score', 'hotspot_score', 'Cyclomatic total x Ca. Classes that are both complex and widely used are the riskiest to change: sort descending to find them.'),
                ]
            },
            {
                title: 'Parameters', columns: [
                    anaCol.num('Constructor params', 'constructor_params', 'Constructor parameters, mostly dependencies. More than 5 or 6 suggests the class does too much.'),
                    anaCol.num('By-reference params', 'total_reference_params', 'Parameters passed by reference across all methods. They silently change the callers variables, ideally 0.'),
                    anaCol.num('Object params', 'total_object_params', 'Parameters typed with a class across all methods: how much the methods depend on other classes.'),
                    anaCol.num('Ending methods', 'methods_ending_script', 'Methods that call exit or die. They cannot be tested or reused, ideally 0.'),
                    anaCol.num('Sensitive params', 'methods_with_sensitive_params', 'Methods with a #[SensitiveParameter]. Informational: shows where secrets such as passwords pass through.'),
                ]
            },
            {
                title: 'Coupling', columns: [
                    anaCol.num('Ca', 'afferent_ca', 'Afferent coupling: how many classes call this one. High means many depend on it, so it must stay stable.'),
                    anaCol.num('Ce', 'efferent_ce', 'Efferent coupling: how many classes this one calls. High means it knows too much and breaks easily; above 10 is a warning.'),
                    anaCol.num('Instability', 'instability', 'Ce / (Ca + Ce). 0 is stable (many depend on it, it depends on little), 1 is unstable. Stable classes should be abstract, unstable ones concrete.'),
                    anaCol.num('MPC', 'mpc', 'Message passing coupling: calls this class makes to other classes. High means a chatty class.'),
                    anaCol.num('DAC', 'dac', 'Data abstraction coupling: properties typed with another class. High means the class holds many collaborators.'),
                    anaCol.num('Abstractness', 'abstractness', '1 for interfaces and abstract classes, 0 for concrete ones. Read it together with instability (main sequence).'),
                    anaCol.num('Main seq. distance', 'main_seq_distance', 'Abs(abstractness + instability - 1). Near 0 is balanced. Near 1 is rigid (stable and concrete, painful to change) or useless (unstable and abstract).'),
                    anaCol.text('Classes used', 'classes_used', 'The classes this class calls.'),
                    anaCol.text('Classes using this', 'classes_using_this', 'The classes that call this class. Check them before changing it.'),
                ]
            },
            {
                title: 'State & Cohesion', columns: [
                    anaCol.num('LCOM', 'lcom', 'Lack of cohesion of methods. 0 means the methods share the properties (cohesive). Closer to 1 or more means unrelated methods in one class: split it.'),
                    anaCol.num('Mutable props', 'mutable_properties', 'Properties that can change after construction. More mutable state means more ways to end up inconsistent.'),
                    anaCol.num('Readonly props', 'readonly_properties', 'Readonly properties and constants. Higher is better.'),
                    anaCol.num('Props written', 'properties_actually_written', 'Distinct properties that some method writes. Compare with mutable properties: mutable ones never written could be readonly.'),
                    anaCol.flag('Superglobals', 'uses_superglobals', 'Reads $_GET, $_POST, $_SESSION and similar. Hidden input makes the class hard to test; ideally false outside controllers.'),
                    anaCol.flag('global keyword', 'uses_global_keyword', 'Uses the global keyword. A hidden dependency, ideally false.'),
                    anaCol.flag('Constants', 'uses_constants', 'Uses global constants. Mostly harmless, but configuration constants are hidden inputs.'),
                ]
            },
            {
                title: 'Archetypes', columns: [
                    anaCol.flag('God class', 'is_god_class', 'LCOM >= 0.8, cyclomatic total above 40 and Ce above 10: a class doing too much. Candidate for splitting.'),
                    anaCol.flag('Data class', 'is_data_class', 'More than 2 mutable properties, at most 3 methods and almost no logic: a bag of data. Consider moving behaviour in or using a readonly value object.'),
                    anaCol.flag('Stateless service', 'is_stateless_service', 'No mutable properties and mostly pure methods. Usually a good shape: easy to test and share.'),
                    anaCol.flag('Unused candidate', 'is_unused_candidate', 'Concrete class nothing calls and nothing extends. May be dead code; verify first, type hints, instanceof and dynamic names are not counted.'),
                ]
            },
        ],
    });

    anaInitToolbar(table, 'class_metrics.csv');
    anaInitGuide(table);
    anaInitFind(table);
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
