<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

// One row per distinct caller/callee method pair. The page aggregates them to class or namespace level.
// is_builtin tells native PHP functions apart from user code (to_class is '' for every function)
$res = $mysqli->query("
    SELECT
        c.from_class, c.from_method, c.to_class, c.to_method,
        COUNT(*) AS weight,
        COALESCE(MAX(m.is_builtin), 0) AS is_builtin
    FROM calls c
    LEFT JOIN methods m ON m.class_name = c.to_class AND m.method_name = c.to_method
    GROUP BY c.from_class, c.from_method, c.to_class, c.to_method
");

$report = [];
while ($row = $res->fetch_assoc()) {
    $report[] = [
        'from_class' => $row['from_class'] ?? '',
        'from_method' => $row['from_method'] ?? '',
        'to_class' => $row['to_class'] ?? '',
        'to_method' => $row['to_method'] ?? '',
        'weight' => (int) $row['weight'],
        'is_builtin' => (int) $row['is_builtin'],
    ];
}

echo json_encode($report);
