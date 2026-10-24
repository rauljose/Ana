<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';
$name = $_GET['name'] ?? '';

function rows(mysqli $mysqli, string $sql, string ...$params): array
{
    $stmt = $mysqli->prepare($sql);
    $stmt->execute($params);
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

switch ($type) {
    case 'class':
        // Everything about one class that the summary APIs only expose as counts
        echo json_encode([
            'properties' => rows($mysqli, "
                SELECT property_name, visibility, is_static, is_readonly, is_constant, type_hint, doc_comment
                FROM properties WHERE class_name = ? ORDER BY is_constant DESC, property_name", $name),
            'parents' => rows($mysqli, "
                SELECT target_class AS class_name, relation_type
                FROM class_relations WHERE source_class = ? ORDER BY relation_type, target_class", $name),
            'children' => rows($mysqli, "
                SELECT source_class AS class_name, relation_type
                FROM class_relations WHERE target_class = ? ORDER BY relation_type, source_class", $name),
            'calls_out' => rows($mysqli, "
                SELECT to_class AS class_name, COUNT(*) AS calls
                FROM calls WHERE from_class = ? AND to_class != from_class AND to_class != ''
                GROUP BY to_class ORDER BY calls DESC, to_class", $name),
            'calls_in' => rows($mysqli, "
                SELECT from_class AS class_name, COUNT(*) AS calls
                FROM calls WHERE to_class = ? AND from_class != to_class
                GROUP BY from_class ORDER BY calls DESC, from_class", $name),
            'prop_access' => rows($mysqli, "
                SELECT method_name, property_name, access_type
                FROM method_property_access WHERE class_name = ? ORDER BY method_name, property_name", $name),
            'globals' => rows($mysqli, "
                SELECT method_name, global_type, global_name
                FROM method_globals WHERE class_name = ? ORDER BY method_name, global_type, global_name", $name),
        ]);
        break;

    case 'method':
        // name is Class::method, or just the function name for functions (class_name is '')
        $parts = explode('::', $name, 2);
        [$className, $methodName] = count($parts) === 2 ? $parts : ['', $name];

        $method = rows($mysqli, "SELECT * FROM methods WHERE class_name = ? AND method_name = ?", $className, $methodName);
        echo json_encode([
            'class_name' => $className,
            'method_name' => $methodName,
            'method' => $method[0] ?? null,
            'calls_out' => rows($mysqli, "
                SELECT c.to_class, c.to_method, COUNT(*) AS calls, COALESCE(MAX(m.is_builtin), 0) AS is_builtin
                FROM calls c
                LEFT JOIN methods m ON m.class_name = c.to_class AND m.method_name = c.to_method
                WHERE c.from_class = ? AND c.from_method = ?
                GROUP BY c.to_class, c.to_method ORDER BY calls DESC, c.to_class, c.to_method", $className, $methodName),
            'calls_in' => rows($mysqli, "
                SELECT from_class, from_method, COUNT(*) AS calls
                FROM calls WHERE to_class = ? AND to_method = ?
                GROUP BY from_class, from_method ORDER BY calls DESC, from_class, from_method", $className, $methodName),
            'prop_access' => rows($mysqli, "
                SELECT property_name, access_type
                FROM method_property_access WHERE class_name = ? AND method_name = ? ORDER BY property_name", $className, $methodName),
            'globals' => rows($mysqli, "
                SELECT global_type, global_name
                FROM method_globals WHERE class_name = ? AND method_name = ? ORDER BY global_type, global_name", $className, $methodName),
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => "Unknown type: $type"]);
        break;
}
