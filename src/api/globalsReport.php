<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

// One row per method (or function) and global symbol it uses

// Constants defined by PHP itself and its extensions. Derived here because the analysis does not store it
$nativeConstants = [];
foreach (get_defined_constants(true) as $category => $constants) {
    if ($category === 'user') continue;
    $nativeConstants += $constants;
}

function accessLabel(string $access): string
{
    return ['READ' => 'Read', 'WRITE' => 'Write', 'READ_WRITE' => 'Read/Write'][$access] ?? $access;
}

function usedBy(string $className, string $methodName): array
{
    $isFunction = $className === '';
    $parts = explode('\\', $isFunction ? $methodName : $className);
    $shortName = array_pop($parts);
    return [
        'namespace' => implode('\\', $parts) ?: '[Global]',
        'class_name' => $className,
        'class_short_name' => $isFunction ? '' : $shortName,
        'method_short_name' => $isFunction ? $shortName : $methodName,
        'method_fqn' => $isFunction ? $methodName : $className . '::' . $methodName,
        'kind' => $isFunction ? 'Function' : 'Method',
    ];
}

$report = [];

// 1. Superglobals, global variables and constants
$res = $mysqli->query("SELECT class_name, method_name, global_type, global_name, access FROM method_globals");
while ($row = $res->fetch_assoc()) {
    $name = $row['global_name'];
    switch ($row['global_type']) {
        case 'SUPERGLOBAL':
            $symbolType = 'Superglobal variable';
            $symbol = '$' . $name;
            $isNative = true;
            break;
        case 'GLOBAL_KEYWORD':
            $symbolType = 'Global variable';
            $symbol = '$' . $name;
            $isNative = false;
            break;
        default:
            $symbolType = 'Constant';
            $symbol = $name;
            $isNative = isset($nativeConstants[ltrim($name, '\\')]);
            break;
    }

    $report[] = [
        'symbol_type' => $symbolType,
        'is_native' => $isNative,
        'symbol' => $symbol,
        'symbol_fqn' => $symbol,
        'access' => accessLabel($row['access']),
    ] + usedBy($row['class_name'], $row['method_name']);
}

// 2. Properties, static properties and class constants of the method's own class.
// The declaration tells which one it is; members declared in a parent class or trait are not found
$res = $mysqli->query("
    SELECT a.class_name, a.method_name, a.property_name, a.access_type,
           p.id AS declared_id, p.is_static, p.is_constant
    FROM method_property_access a
    LEFT JOIN properties p ON p.class_name = a.class_name AND p.property_name = a.property_name
");
while ($row = $res->fetch_assoc()) {
    $name = $row['property_name'];
    if ($row['declared_id'] === null) {
        $symbolType = 'Inherited member';
        $symbol = $name;
        $symbolFqn = $row['class_name'] . '::' . $name;
    } elseif ((int) $row['is_constant'] === 1) {
        $symbolType = 'Class constant';
        $symbol = $name;
        $symbolFqn = $row['class_name'] . '::' . $name;
    } else {
        $symbolType = (int) $row['is_static'] === 1 ? 'Static property' : 'Property';
        $symbol = '$' . $name;
        $symbolFqn = $row['class_name'] . '::$' . $name;
    }

    $report[] = [
        'symbol_type' => $symbolType,
        'is_native' => false,
        'symbol' => $symbol,
        'symbol_fqn' => $symbolFqn,
        'access' => accessLabel($row['access_type']),
    ] + usedBy($row['class_name'], $row['method_name']);
}

echo json_encode($report);
