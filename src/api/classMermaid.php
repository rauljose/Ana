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

$action = $_GET['action'] ?? '1';
$targetClass = $_GET['class'] ?? '';

// Helper to generate safe Mermaid Node IDs
// Helper to generate safe, readable Mermaid Node IDs
function escapeId(string $name): string {
    if ($name === '') return 'Global_Functions';

    // Replace namespace slashes with a single underscore
    $id = str_replace('\\', '_', $name);

    // Replace method double-colons with a double dash
    $id = str_replace('::', '--', $id);

    // Prefix with 'ID_' just in case a class name starts with a number
    // or to prevent conflicts with Mermaid reserved words
    return 'ID_' . $id;
}

// Start Mermaid Graph
$mermaid = "graph TD\n";

switch ($action) {
    case '1':
        // ACTION 1: Class-to-Class map with call counts.
        $res = $mysqli->query("
            SELECT 
                from_class, 
                to_class, 
                COUNT(*) as total_calls
            FROM calls
            WHERE from_class != to_class
            GROUP BY from_class, to_class
        ");
        
        $nodes = [];
        while ($row = $res->fetch_assoc()) {
            $from = $row['from_class'] === '' ? 'Global_Scope' : $row['from_class'];
            $to = $row['to_class'] === '' ? 'Global_Functions' : $row['to_class'];
            $count = $row['total_calls'];
            
            $fromId = escapeId($from);
            $toId = escapeId($to);
            
            if (!isset($nodes[$fromId])) {
                $mermaid .= "    {$fromId}[\"{$from}\"]\n";
                $nodes[$fromId] = true;
            }
            if (!isset($nodes[$toId])) {
                $mermaid .= "    {$toId}[\"{$to}\"]\n";
                $nodes[$toId] = true;
            }
            
            $mermaid .= "    {$fromId} -->|{$count}| {$toId}\n";
        }
        break;

    case '2':
        // ACTION 2: Method-to-Method (Inter-class)
        // Shows methods in a target class and exactly what external methods they call.
        if (empty($targetClass)) {
            die("Error: Action 2 requires a '?class=' parameter.");
        }
        
        $stmt = $mysqli->prepare("
            SELECT from_method, to_class, to_method 
            FROM calls 
            WHERE from_class = ? AND from_class != to_class
        ");
        $stmt->bind_param('s', $targetClass);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $sourceMethods = [];
        $externalCalls = [];
        
        while ($row = $res->fetch_assoc()) {
            $sourceMethods[$row['from_method']] = true;
            $toClass = $row['to_class'] === '' ? 'Global_Functions' : $row['to_class'];
            
            $externalCalls[] = [
                'from_method' => $row['from_method'],
                'to_class' => $toClass,
                'to_method' => $row['to_method']
            ];
        }
        
        // Group the source class methods into a Subgraph
        $mermaid .= "    subgraph " . escapeId($targetClass) . " [\"{$targetClass}\"]\n";
        foreach (array_keys($sourceMethods) as $method) {
            $nodeId = escapeId($targetClass . '::' . $method);
            $mermaid .= "        {$nodeId}([\"{$method}()\"])\n";
        }
        $mermaid .= "    end\n\n";
        
        // Draw external calls
        $drawnExternals = [];
        foreach ($externalCalls as $call) {
            $fromId = escapeId($targetClass . '::' . $call['from_method']);
            $toNodeId = escapeId($call['to_class'] . '::' . $call['to_method']);
            
            // Define the external node if not drawn yet
            if (!isset($drawnExternals[$toNodeId])) {
                $mermaid .= "    {$toNodeId}[\"{$call['to_class']}::{$call['to_method']}()\"]\n";
                $drawnExternals[$toNodeId] = true;
            }
            $mermaid .= "    {$fromId} --> {$toNodeId}\n";
        }
        break;

    case '3':
        // ACTION 3: Intra-class calls (Method-to-Method within the SAME class)
        if (empty($targetClass)) {
            die("Error: Action 3 requires a '?class=' parameter.");
        }
        
        $stmt = $mysqli->prepare("
            SELECT from_method, to_method, COUNT(*) as weight
            FROM calls 
            WHERE from_class = ? AND to_class = ?
        ");
        $stmt->bind_param('ss', $targetClass, $targetClass);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $mermaid .= "    subgraph " . escapeId($targetClass) . " [\"Internal Calls: {$targetClass}\"]\n";
        
        $nodes = [];
        while ($row = $res->fetch_assoc()) {
            $fromId = escapeId($targetClass . '::' . $row['from_method']);
            $toId = escapeId($targetClass . '::' . $row['to_method']);
            
            if (!isset($nodes[$fromId])) {
                $mermaid .= "        {$fromId}([\"{$row['from_method']}()\"])\n";
                $nodes[$fromId] = true;
            }
            if (!isset($nodes[$toId])) {
                $mermaid .= "        {$toId}([\"{$row['to_method']}()\"])\n";
                $nodes[$toId] = true;
            }
            
            $mermaid .= "        {$fromId} -->|{$row['weight']}| {$toId}\n";
        }
        $mermaid .= "    end\n";
        break;

    case '4':
        // ACTION 4: Inheritance & Implementation Tree
        // Maps architectural depth: EXTENDS, IMPLEMENTS, and USES (traits)
        $res = $mysqli->query("
            SELECT source_class, target_class, relation_type 
            FROM class_relations 
            WHERE source_class != '' AND target_class != ''
        ");

        $nodes = [];
        while ($row = $res->fetch_assoc()) {
            $source = $row['source_class'];
            $target = $row['target_class'];
            $type = $row['relation_type'];

            $sourceId = escapeId($source);
            $targetId = escapeId($target);

            if (!isset($nodes[$sourceId])) {
                $mermaid .= "    {$sourceId}[\"{$source}\"]\n";
                $nodes[$sourceId] = true;
            }
            if (!isset($nodes[$targetId])) {
                $mermaid .= "    {$targetId}[\"{$target}\"]\n";
                $nodes[$targetId] = true;
            }

            // Differentiate relations with line styles
            if ($type === 'EXTENDS') {
                $mermaid .= "    {$sourceId} --|> {$targetId}\n"; // Solid line with inheritance arrow
            } elseif ($type === 'IMPLEMENTS') {
                $mermaid .= "    {$sourceId} -.->|Implements| {$targetId}\n"; // Dotted line
            } elseif ($type === 'USES') {
                $mermaid .= "    {$sourceId} -.->|Uses Trait| {$targetId}\n";
            } else {
                $mermaid .= "    {$sourceId} -->|{$type}| {$targetId}\n";
            }
        }
        break;

    case '5':
        // ACTION 5: Data Composition Graph (DAC visualization)
        // Shows which classes hold other complex classes as properties
        $res = $mysqli->query("
            SELECT class_name, type_hint 
            FROM properties 
            WHERE type_hint IS NOT NULL AND type_hint != '' AND is_constant = 0
        ");

        $primitives = ['int', 'float', 'string', 'bool', 'array', 'mixed', 'object', 'callable', 'iterable', 'null', 'false', 'true', 'void', 'never'];
        $nodes = [];
        $relations = [];

        while ($row = $res->fetch_assoc()) {
            $className = $row['class_name'];

            // Extract individual types from unions/intersections, keeping original casing for labels
            $rawTypes = explode('|', str_replace(['?', '&'], '|', $row['type_hint']));

            foreach ($rawTypes as $t) {
                $t = trim($t);
                $tLower = strtolower($t);

                if ($t !== '' && !in_array($tLower, $primitives, true)) {
                    // Group multiple properties of the same complex type
                    $key = $className . '->' . $t;
                    if (!isset($relations[$key])) {
                        $relations[$key] = ['from' => $className, 'to' => $t, 'count' => 1];
                    } else {
                        $relations[$key]['count']++;
                    }
                }
            }
        }

        foreach ($relations as $rel) {
            $from = $rel['from'];
            $to = $rel['to'];
            $count = $rel['count'];

            $fromId = escapeId($from);
            $toId = escapeId($to);

            if (!isset($nodes[$fromId])) {
                $mermaid .= "    {$fromId}[\"{$from}\"]\n";
                $nodes[$fromId] = true;
            }
            if (!isset($nodes[$toId])) {
                $mermaid .= "    {$toId}[\"{$to}\"]\n";
                $nodes[$toId] = true;
            }

            $mermaid .= "    {$fromId} -->|has {$count}| {$toId}\n";
        }
        break;

    case '6':
        // ACTION 6: "Zone of Pain" Scatter Plot (ECharts JSON)
        // X-axis = Efferent Coupling (Ce), Y-axis = Total Cyclomatic Complexity
        header('Content-Type: application/json; charset=utf-8');

        $targetClasses = isset($_GET['classes']) && $_GET['classes'] !== '' ? explode(',', $_GET['classes']) : [];
        $whereClause = "";

        if (!empty($targetClasses)) {
            $escaped = array_map([$mysqli, 'real_escape_string'], $targetClasses);
            $inList = "'" . implode("','", $escaped) . "'";
            $whereClause = "WHERE c.class_name IN ($inList)";
        }

        $res = $mysqli->query("
            SELECT 
                c.class_name,
                COALESCE((SELECT SUM(cyclo_complexity) FROM methods WHERE class_name = c.class_name), 0) as total_cyclo,
                COALESCE((SELECT COUNT(DISTINCT to_class) FROM calls WHERE from_class = c.class_name AND from_class != to_class AND to_class != ''), 0) as ce
            FROM classes c
            $whereClause
        ");

        $scatterData = [];
        while ($row = $res->fetch_assoc()) {
            // ECharts standard scatter format: [X-value, Y-value, Name]
            $scatterData[] = [
              (int)$row['ce'],
              (int)$row['total_cyclo'],
              $row['class_name']
            ];
        }

        echo json_encode($scatterData, JSON_PRETTY_PRINT);
        exit;

    case '7':
        // ACTION 7: Class Health Radar (ECharts JSON)
        // Plots LCOM, Purity %, Avg Complexity, Ce, and Ca for specific classes
        header('Content-Type: application/json; charset=utf-8');

        $targetClasses = isset($_GET['classes']) && $_GET['classes'] !== '' ? explode(',', $_GET['classes']) : [];
        if (empty($targetClasses)) {
            echo json_encode(['error' => 'Radar chart requires at least one class provided in the ?classes= parameter']);
            exit;
        }

        $radarData = [];

        foreach ($targetClasses as $cls) {
            $cleanCls = $mysqli->real_escape_string($cls);

            // 1. Get Method counts, Purity, and Avg Cyclo
            $res = $mysqli->query("SELECT COUNT(*) as m, SUM(is_pure) as pure, SUM(cyclo_complexity) as cyclo FROM methods WHERE class_name = '$cleanCls'");
            $row = $res->fetch_assoc();
            $m = (float)$row['m'];
            $purity = $m > 0 ? round(($row['pure'] / $m) * 100, 2) : 0.0;
            $avgCyclo = $m > 0 ? round($row['cyclo'] / $m, 2) : 0.0;

            // 2. Get Efferent (Ce) and Afferent (Ca) Coupling
            $res = $mysqli->query("SELECT COUNT(DISTINCT to_class) as ce FROM calls WHERE from_class = '$cleanCls' AND from_class != to_class AND to_class != ''");
            $ce = (int)$res->fetch_assoc()['ce'];

            $res = $mysqli->query("SELECT COUNT(DISTINCT from_class) as ca FROM calls WHERE to_class = '$cleanCls' AND from_class != to_class");
            $ca = (int)$res->fetch_assoc()['ca'];

            // 3. Calculate LCOM
            $res = $mysqli->query("SELECT COUNT(DISTINCT property_name) as a FROM properties WHERE class_name = '$cleanCls'");
            $a = (float)$res->fetch_assoc()['a'];

            $res = $mysqli->query("SELECT COUNT(*) as sum_mA FROM (SELECT DISTINCT method_name, property_name FROM method_property_access WHERE class_name = '$cleanCls') u");
            $sum_mA = (float)$res->fetch_assoc()['sum_mA'];

            $lcom = 0.0;
            if ($m > 1 && $a > 0) {
                $lcom = ($m - ($sum_mA / $a)) / ($m - 1);
                if ($lcom < 0) $lcom = 0;
                if ($lcom > 2) $lcom = 2;
            }

            // Push into ECharts series data structure
            $radarData[] = [
              'name' => $cls,
              'value' => [round($lcom, 2), $purity, $avgCyclo, $ce, $ca]
            ];
        }

        echo json_encode([
            // Provide the schema mapping so the frontend doesn't have to guess axis order
          'schema' => ['LCOM (Lower=Better)', 'Purity % (Higher=Better)', 'Avg Complexity', 'Efferent (Ce)', 'Afferent (Ca)'],
          'data' => $radarData
        ], JSON_PRETTY_PRINT);
        exit;

    default:
        $mermaid = "Error: Invalid action.";
        break;
}

echo $mermaid;