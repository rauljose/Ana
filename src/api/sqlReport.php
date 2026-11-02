<?php declare(strict_types=1);

require_once("../config/config.php");
require_once __DIR__ . '/used_by.php';

header('Content-Type: application/json; charset=utf-8');

// One row per method (or function), SQL operation and table or procedure it names.
// Operations are SELECT, INSERT, UPDATE, DELETE, REPLACE, TRUNCATE, CALL and the DDL ones (CREATE, ALTER, DROP, RENAME)

$accessLabels = ['READ' => 'Read', 'WRITE' => 'Write', 'CALL' => 'Call', 'DDL' => 'DDL'];

$report = [];
try {
    $res = $mysqli->query("SELECT class_name, method_name, operation, object_type, object_name, access, is_dynamic, source, snippet FROM method_sql");
    while ($row = $res->fetch_assoc()) {
        $report[] = [
            'operation' => $row['operation'],
            'object_type' => $row['object_type'],
            // {?} is a name only known at run time; databases analyzed before wrote it as ?
            'object_name' => str_replace('?', '{?}', str_replace('{?}', '?', $row['object_name'])),
            'access' => $accessLabels[$row['access']] ?? $row['access'],
            'is_dynamic' => (int) $row['is_dynamic'] === 1,
            'source' => $row['source'] === 'HELPER' ? 'Sqler helper' : 'SQL text',
            'snippet' => $row['snippet'],
        ] + usedBy($row['class_name'], $row['method_name']);
    }
} catch (mysqli_sql_exception $e) {
    // Database analyzed before the SQL was recorded: no rows, run the analysis again
}

echo json_encode($report);
