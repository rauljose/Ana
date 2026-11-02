<?php declare(strict_types=1);

require_once("../config/config.php");
require_once __DIR__ . '/used_by.php';

header('Content-Type: application/json; charset=utf-8');

// One row per method (or function), Firebase service, path and operation. Services: the Realtime Database, Firestore and
// Cloud Storage, through kreait/firebase-php. A part of a path only known at run time is {?}; the root is /

$serviceLabels = ['REALTIME' => 'Realtime Database', 'FIRESTORE' => 'Firestore', 'STORAGE' => 'Storage'];
// What the call does to the path, in words. The single place for them: the pages show these
$effects = [
    'OVERWRITE' => 'Overwrites the path and everything under it',
    'CHILDREN' => 'Changes only the children it names, the rest stays',
    'APPEND' => 'Adds a new child with a generated key',
    'REMOVE' => 'Deletes the path and everything under it',
    'ALL' => 'Reads the path and everything under it',
    'QUERY' => 'Reads some of the children (a query)',
    'KEYS' => 'Reads only the names of the children',
    'METADATA' => 'Reads information about it, not its content',
];

$report = [];
try {
    $res = $mysqli->query("SELECT class_name, method_name, service, object_type, path, operation, access, mode, is_dynamic, snippet FROM method_firebase");
    while ($row = $res->fetch_assoc()) {
        $report[] = [
            'service' => $serviceLabels[$row['service']] ?? $row['service'],
            'service_code' => $row['service'],
            'object_type' => $row['object_type'],
            'path' => $row['path'],
            'operation' => $row['operation'],
            'access' => $row['access'] === 'WRITE' ? 'Write' : 'Read',
            'mode' => $row['mode'],
            'effect' => $effects[$row['mode']] ?? $row['mode'],
            'is_dynamic' => (int) $row['is_dynamic'] === 1,
            'snippet' => $row['snippet'],
        ] + usedBy($row['class_name'], $row['method_name']);
    }
} catch (mysqli_sql_exception $e) {
    // Database analyzed before Firebase was recorded: no rows, run the analysis again
}

echo json_encode($report);
