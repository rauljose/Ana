<?php declare(strict_types=1);

// Single source of truth for the pages: the top menu, the index page and the report page titles read this list.
// [label, description, group]. Group '' is a link of its own in the menu; any other group is a drop-down in the order of $anaGroups
$anaPages = [
    'index.php' => ['Home', 'Project overview and links to every page', ''],
    'review.php' => ['Review', 'Short list of the important things to look at: shared globals, hard to change methods, most used methods and namespaces', ''],
    'namespace_metrics.php' => ['Namespace Metrics', 'Metrics per namespace', 'Namespaces'],
    'class_metrics.php' => ['Class Metrics', 'Metrics per class', 'Classes'],
    'method_metrics.php' => ['Method Metrics', 'Method and function metrics', 'Methods'],
    'method_globals.php' => ['Method Globals', 'Constants, superglobals and global variables it uses, native or not, and read or write per Method', 'State'],
    'global_report.php' => ['Globals Report', 'Superglobals, global variables, constants, properties and static properties, and the methods that use them', 'State'],
    'class_graph.php' => ['Class Graphs', 'Zone of Pain scatter plot and class health radar', 'Classes'],
    'method_graph.php' => ['Method Graphs', 'Method hotspots scatter plot and method health radar', 'Methods'],
    'call_graph.php' => ['Call Graph', 'Calls between namespaces, classes or methods, with incoming and outgoing calls of a focus', 'Dependencies'],
    'change_impact.php' => ['Change Impact', 'Pick a method or function and see everything that calls it, directly or through other methods: what to retest before changing it', 'Methods'],
    'shared_state.php' => ['Shared State', 'Global variables, superglobals and static properties with the methods that read and write each one: hidden coupling between classes and namespaces', 'State'],
    'method_public_consumers.php' => ['Public Method Consumers', 'Who uses each public method: callers in its own class, in other classes or namespaces, or none recorded', 'Methods'],
    'sql_report.php' => ['SQL Report', 'The SQL in the code: which methods read, change, call or define each table and procedure, and which tables are changed from several classes', 'SQL'],
    'maltese_cross.php' => ['Maltese Cross', 'Database tables against the methods and functions that use them: R, W or RW in each cell, and a filter that keeps what writes, read and write included', 'SQL'],
    'firebase_report.php' => ['Firebase Report', 'The Firebase data in the code (Realtime Database, Firestore, Storage): which methods read and write each path, and whether a write overwrites the path, changes only its children, appends or deletes', 'Firebase'],
    'firebase_cross.php' => ['Firebase Cross', 'Firebase paths against the methods and functions that use them: O overwrites, C changes children, A appends, X deletes, R reads, with a filter by what the call does', 'Firebase'],
    'dsm.php' => ['Dependency Matrix', 'Who depends on whom as a matrix of namespaces, classes, methods, shared properties and variables or constants, limited to a focus, with cycles and heavy dependencies in color', 'Dependencies'],
    'dependency_cycles.php' => ['Dependency Cycles', 'Namespaces, classes or methods that depend on each other in a loop, with an example path and the calls behind it', 'Dependencies'],
];

// Drop-downs of the top menu and headings of the index page, in order
$anaGroups = ['Namespaces', 'Classes', 'Methods', 'State', 'SQL', 'Firebase', 'Dependencies'];

// The pages of a group, file => [label, description, group]
$anaPagesIn = fn(string $group): array => array_filter($anaPages, fn(array $page): bool => $page[2] === $group);
