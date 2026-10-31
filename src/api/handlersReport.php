<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

// One row per method (or function) and PHP handler registration it calls.
// Read from the stored calls: calls made outside any method or function are not collected
$handlerFunctions = ['register_shutdown_function', 'set_error_handler'];
$inList = "'" . implode("','", $handlerFunctions) . "'";
$lastSegment = "SUBSTRING_INDEX(to_method, '\\\\', -1)";

$report = [];
$res = $mysqli->query("
    SELECT from_class, from_method, $lastSegment AS handler_function, COUNT(*) AS call_count
    FROM calls
    WHERE to_class = '' AND $lastSegment IN ($inList)
    GROUP BY from_class, from_method, handler_function
");
while ($row = $res->fetch_assoc()) {
    $isFunction = $row['from_class'] === '';
    $parts = explode('\\', $isFunction ? $row['from_method'] : $row['from_class']);
    array_pop($parts);
    $report[] = [
        'handler_function' => $row['handler_function'],
        'namespace' => implode('\\', $parts) ?: '[Global]',
        'class_name' => $row['from_class'],
        'method_fqn' => $isFunction ? $row['from_method'] : $row['from_class'] . '::' . $row['from_method'],
        'kind' => $isFunction ? 'Function' : 'Method',
        'call_count' => (int) $row['call_count'],
    ];
}

echo json_encode($report);
