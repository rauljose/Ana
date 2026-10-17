<?php declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'static_analysis');
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed");
}

header('Content-Type: text/plain; charset=utf-8');

$action = $_GET['action'] ?? 'call_graph';

// Normalize entries: accept either ?entries[]=ClassA&entries[]=ClassB or ?entries=ClassA,ClassB
$rawEntries = $_GET['entries'] ?? [];
if (!is_array($rawEntries)) {
    $rawEntries = explode(',', $rawEntries);
}

if (empty($rawEntries)) {
    die("graph TD\n    Error[\"Error: No entries provided. Use ?entries[]=... or ?entries=...,...\"]");
}

// Build SQL WHERE conditions based on the entry types
$fromConditions = [];
$toConditions = [];

foreach ($rawEntries as $entry) {
    $entry = $mysqli->real_escape_string(trim($entry));
    if ($entry === '') continue;

    if (strpos($entry, '::') !== false) {
        // It is a specific method (e.g., App\Class::method)
        list($c, $m) = explode('::', $entry, 2);
        $fromConditions[] = "(from_class = '$c' AND from_method = '$m')";
        $toConditions[] = "(to_class = '$c' AND to_method = '$m')";
    } else {
        // It is EITHER a full class OR a global/native function
        // If it's a class, we match the class_name. If a function, class_name is '' and method_name matches.
        $fromConditions[] = "(from_class = '$entry' OR (from_class = '' AND from_method = '$entry'))";
        $toConditions[] = "(to_class = '$entry' OR (to_class = '' AND to_method = '$entry'))";
    }
}

$whereFrom = implode(' OR ', $fromConditions);
$whereTo = implode(' OR ', $toConditions);

$query = "";
switch ($action) {
    case 'calls':
        // Outgoing calls FROM the requested entries
        $query = "SELECT from_class, from_method, to_class, to_method, COUNT(*) as weight 
                  FROM calls WHERE $whereFrom GROUP BY from_class, from_method, to_class, to_method";
        break;

    case 'calledBy':
        // Incoming calls TO the requested entries
        $query = "SELECT from_class, from_method, to_class, to_method, COUNT(*) as weight 
                  FROM calls WHERE $whereTo GROUP BY from_class, from_method, to_class, to_method";
        break;

    case 'call_graph':
        // BOTH incoming and outgoing calls
        $query = "SELECT from_class, from_method, to_class, to_method, COUNT(*) as weight 
                  FROM calls WHERE ($whereFrom) OR ($whereTo) GROUP BY from_class, from_method, to_class, to_method";
        break;

    default:
        die("graph TD\n    Error[\"Error: Invalid action. Use calls, calledBy, or call_graph.\"]");
}

$res = $mysqli->query($query);

// Helper to generate safe Mermaid Node IDs
function escapeId(string $class, string $method): string {
    $name = $class === '' ? $method : $class . '::' . $method;
    $id = str_replace('\\', '_', $name);
    $id = str_replace('::', '--', $id);
    return 'ID_' . $id;
}

// Helper to generate readable Node Labels
function getLabel(string $class, string $method): string {
    if ($class === '') {
        return "{$method}()"; // Global or Native function
    }
    return "{$class}::{$method}()";
}

$nodes = [];
$edges = [];

while ($row = $res->fetch_assoc()) {
    $fc = $row['from_class'];
    $fm = $row['from_method'];
    $tc = $row['to_class'];
    $tm = $row['to_method'];
    $weight = $row['weight'];

    $fId = escapeId($fc, $fm);
    $tId = escapeId($tc, $tm);

    if (!isset($nodes[$fId])) {
        $nodes[$fId] = getLabel($fc, $fm);
    }
    if (!isset($nodes[$tId])) {
        $nodes[$tId] = getLabel($tc, $tm);
    }

    $edges[] = "    {$fId} -->|{$weight}| {$tId}";
}

// Render the Mermaid Graph
echo "graph LR\n"; // Using Left-to-Right (LR) as it is usually easier to read for deep call chains

if (empty($nodes)) {
    echo "    Empty[\"No calls found for the requested entries.\"]\n";
    exit;
}

foreach ($nodes as $id => $label) {
    echo "    {$id}[\"{$label}\"]\n";
}

echo "\n";

foreach ($edges as $edge) {
    echo "{$edge}\n";
}
