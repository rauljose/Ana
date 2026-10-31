<?php declare(strict_types=1);

// Single source of truth for the pages: the top menu, the index page and the report page titles read this list.
// [label, description, optional false = not in the top menu, only a box on the index page]
$anaPages = [
    'index.php' => ['Home', 'Project overview and links to every page'],
    'review.php' => ['Review', 'Short list of the important things to look at: shared globals, hard to change methods, most used methods and namespaces'],
    'namespace_metrics.php' => ['Namespace Metrics', 'Metrics rolled up per namespace: size, complexity, coupling, cycles'],
    'class_metrics.php' => ['Class Metrics', 'Every recovered class metric in one sortable, filterable table'],
    'method_metrics.php' => ['Method Metrics', 'Every recovered method and function metric in one sortable, filterable table'],
    'method_globals.php' => ['Method Globals', 'For each method or function: the constants, superglobals and global variables it uses, native or not, and read or write'],
    'global_report.php' => ['Globals Report', 'Superglobals, global variables, constants, properties and static properties, and the methods that use them'],
    'class_graph.php' => ['Class Graphs', 'Zone of Pain scatter plot and class health radar'],
    'method_graph.php' => ['Method Graphs', 'Method hotspots scatter plot and method health radar'],
    'call_graph.php' => ['Call Graph', 'Calls between namespaces, classes or methods, with incoming and outgoing calls of a focus'],
    'change_impact.php' => ['Change Impact', 'Pick a method or function and see everything that calls it, directly or through other methods: what to retest before changing it', false],
    'shared_state.php' => ['Shared State', 'Global variables, superglobals and static properties with the methods that read and write each one: hidden coupling between classes and namespaces', false],
    'api_consumers.php' => ['Public Method Consumers', 'Who uses each public method: callers in its own class, in other classes or namespaces, or none recorded', false],
    'dependency_cycles.php' => ['Dependency Cycles', 'Namespaces, classes or methods that depend on each other in a loop, with an example path and the calls behind it', false],
];
