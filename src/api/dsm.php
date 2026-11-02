<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

// What dsm.php needs besides api/callGraph.php (calls) and api/globalsReport.php (state and constants):
// every class, every own method or function (the empty rows of the matrix) and the class to class relations.
// Native PHP functions are left out; class_name '' is a function.

$classes = [];
$res = $mysqli->query("SELECT class_name, is_abstract, is_interface, is_trait FROM classes");
while ($row = $res->fetch_assoc()) {
    $classes[] = [
        'class_name' => $row['class_name'],
        'kind' => (int) $row['is_interface'] === 1 ? 'interface' : ((int) $row['is_trait'] === 1 ? 'trait' : ((int) $row['is_abstract'] === 1 ? 'abstract' : 'class')),
    ];
}

$methods = [];
$res = $mysqli->query("SELECT class_name, method_name FROM methods WHERE is_builtin = 0");
while ($row = $res->fetch_assoc()) {
    $methods[] = ['class_name' => $row['class_name'], 'method_name' => $row['method_name']];
}

// Type hints of functions have no class: nothing to depend from
$relations = [];
$res = $mysqli->query("SELECT source_class, target_class, relation_type FROM class_relations WHERE source_class <> ''");
while ($row = $res->fetch_assoc()) {
    $relations[] = [
        'source_class' => $row['source_class'],
        'target_class' => $row['target_class'],
        'relation_type' => $row['relation_type'],
    ];
}

echo json_encode(['classes' => $classes, 'methods' => $methods, 'relations' => $relations]);
