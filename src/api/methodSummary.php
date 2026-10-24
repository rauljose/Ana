<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'get_methods';
$targetClasses = isset($_GET['classes']) && $_GET['classes'] !== '' ? explode(',', $_GET['classes']) : [];

switch ($action) {
    case 'get_methods':
        $whereMethods = "";
        $whereFrom = "";
        $whereTo = "";

        if (!empty($targetClasses)) {
            $escaped = array_map([$mysqli, 'real_escape_string'], $targetClasses);
            $inList = "'" . implode("','", $escaped) . "'";

            $whereMethods = "WHERE class_name IN ($inList)";
            $whereFrom = "WHERE from_class IN ($inList)";
            $whereTo = "WHERE to_class IN ($inList)";
        }

        $methods = [];

        // 1. Base Data: Grab ALL columns from the database directly
        $res = $mysqli->query("SELECT * FROM methods $whereMethods");
        while ($row = $res->fetch_assoc()) {
            $stringId = $row['class_name'] . '::' . $row['method_name'];

            // Assign every database column directly to the array
            $methods[$stringId] = $row;

            // Format existing columns
            $methods[$stringId]['class_name'] = $row['class_name'] === '' ? '[Global Scope]' : $row['class_name'];

            // Append a unique string ID for the frontend (to distinguish from the DB 'id' INT column)
            $methods[$stringId]['full_method_id'] = $stringId;

            // Name parts for the frontend. Functions have no class: their namespace lives in method_name
            $isFunction = $row['class_name'] === '';
            $nameParts = explode('\\', $isFunction ? $row['method_name'] : $row['class_name']);
            $shortName = array_pop($nameParts);
            $methods[$stringId]['namespace'] = implode('\\', $nameParts) ?: '[Global]';
            $methods[$stringId]['class_short_name'] = $isFunction ? '' : $shortName;
            $methods[$stringId]['method_short_name'] = $isFunction ? $shortName : $row['method_name'];
            $methods[$stringId]['fqn'] = $isFunction ? $row['method_name'] : $stringId;
            $methods[$stringId]['kind'] = !$isFunction ? 'Method' : ($row['is_builtin'] ? 'Native Function' : 'Function');

            // Append default relational metrics to be populated below
            $methods[$stringId]['props_read'] = 0;
            $methods[$stringId]['props_written'] = 0;
            $methods[$stringId]['props_read_list'] = [];
            $methods[$stringId]['props_written_list'] = [];
            $methods[$stringId]['uses_globals'] = 0;
            $methods[$stringId]['globals_list'] = [];
            $methods[$stringId]['calls_out_total'] = 0;
            $methods[$stringId]['calls_out_native'] = 0;
            $methods[$stringId]['calls_out_list'] = [];
            $methods[$stringId]['calls_in_total'] = 0;
            $methods[$stringId]['calls_in_list'] = [];
        }

        // 2. Outbound Calls (What this method calls)
        $res = $mysqli->query("SELECT from_class, from_method, to_class, to_method FROM calls $whereFrom");
        while ($row = $res->fetch_assoc()) {
            $stringId = $row['from_class'] . '::' . $row['from_method'];
            if (!isset($methods[$stringId])) continue;

            $methods[$stringId]['calls_out_total']++;

            if ($row['to_class'] === '') {
                $methods[$stringId]['calls_out_native']++;
                $methods[$stringId]['calls_out_list'][] = $row['to_method'] . '() [Native/Global]';
            } else {
                $methods[$stringId]['calls_out_list'][] = $row['to_class'] . '::' . $row['to_method'] . '()';
            }
        }

        // 3. Inbound Calls (Who calls this method)
        $res = $mysqli->query("SELECT from_class, from_method, to_class, to_method FROM calls $whereTo");
        while ($row = $res->fetch_assoc()) {
            $stringId = $row['to_class'] . '::' . $row['to_method'];
            if (!isset($methods[$stringId])) continue;

            $methods[$stringId]['calls_in_total']++;
            $caller = $row['from_class'] === '' ? '[Global]' : $row['from_class'];
            $methods[$stringId]['calls_in_list'][] = $caller . '::' . $row['from_method'] . '()';
        }

        // 4. Internal State Modification (Properties)
        $res = $mysqli->query("SELECT class_name, method_name, property_name, access_type FROM method_property_access $whereMethods");
        while ($row = $res->fetch_assoc()) {
            $stringId = $row['class_name'] . '::' . $row['method_name'];
            if (!isset($methods[$stringId])) continue;

            if ($row['access_type'] === 'READ') {
                $methods[$stringId]['props_read']++;
                $methods[$stringId]['props_read_list'][] = $row['property_name'];
            } else {
                $methods[$stringId]['props_written']++;
                $methods[$stringId]['props_written_list'][] = $row['property_name'];
            }
        }

        // 5. Global State Usage
        $res = $mysqli->query("SELECT class_name, method_name, global_type, global_name FROM method_globals $whereMethods");
        while ($row = $res->fetch_assoc()) {
            $stringId = $row['class_name'] . '::' . $row['method_name'];
            if (isset($methods[$stringId])) {
                $methods[$stringId]['uses_globals']++;
                // Written the way it appears in code: $_GET, global $name, or the constant
                $methods[$stringId]['globals_list'][] = match ($row['global_type']) {
                    'SUPERGLOBAL' => '$' . $row['global_name'],
                    'GLOBAL_KEYWORD' => 'global $' . $row['global_name'],
                    default => $row['global_name'],
                };
            }
        }

        // 6. Polish lists for Tabulator text rendering
        foreach ($methods as &$m) {
            $m['calls_out_list'] = implode(', ', array_unique($m['calls_out_list']));
            $m['calls_in_list'] = implode(', ', array_unique($m['calls_in_list']));
            $m['props_read_list'] = implode(', ', $m['props_read_list']);
            $m['props_written_list'] = implode(', ', $m['props_written_list']);
            $m['globals_list'] = implode(', ', $m['globals_list']);
        }
        unset($m);

        echo json_encode(array_values($methods), JSON_PRETTY_PRINT);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => "Unknown action: $action"]);
        break;
}
