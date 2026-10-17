<?php declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'static_analysis');
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
} catch (mysqli_sql_exception $e) {
    die(json_encode(['error' => 'Database connection failed']));
}

header('Content-Type: application/json; charset=utf-8');

// Query calls where the target class is empty (native PHP functions or global functions)
// Grouping by caller and target to provide a clean, unique list with a call_count
$res = $mysqli->query("
    SELECT 
        from_class, 
        from_method, 
        to_method, 
        COUNT(*) as call_count
    FROM calls
    WHERE to_class = ''
    GROUP BY from_class, from_method, to_method
");

$report = [];

while ($row = $res->fetch_assoc()) {
    $fromClass = $row['from_class'];
    $fromMethod = $row['from_method'];
    $nativeFunction = $row['to_method'];

    $namespace = '[Global]';
    $className = '';

    if ($fromClass !== '') {
        // It is a class method
        $parts = explode('\\', $fromClass);
        $className = array_pop($parts); // The short class name

        if (!empty($parts)) {
            $namespace = implode('\\', $parts);
        }
    } else {
        // It is a global user function; class name remains blank, method name is the function name
        $className = '';
    }

    $report[] = [
      'namespace' => $namespace,
      'className' => $className,
      'full_class_name' => $fromClass, // Kept for exact filtering/matching on the frontend
      'methodName' => $fromMethod,
      'native_function' => $nativeFunction,
      'call_count' => (int)$row['call_count']
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT);
