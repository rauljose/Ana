<?php declare(strict_types=1);

require_once("../config/config.php");
require_once __DIR__ . '/used_by.php';

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
        'is_own_class' => false,
        // Superglobals are always arrays; a global variable or constant has no declared type
        'type_hint' => $row['global_type'] === 'SUPERGLOBAL' ? 'array' : null,
        'doc_comment' => null,
        'symbol' => $symbol,
        'symbol_fqn' => $symbol,
        'access' => accessLabel($row['access']),
    ] + usedBy($row['class_name'], $row['method_name']);
}

// ?only=globals leaves out the properties, for the page that lists globals per method
if (($_GET['only'] ?? '') === 'globals') {
    echo json_encode($report);
    exit;
}

// Where an inherited member is declared: the nearest class up the extends and trait use chain declaring it
$parents = [];
$res = $mysqli->query("SELECT source_class, target_class FROM class_relations WHERE relation_type IN ('EXTENDS', 'USES')");
while ($row = $res->fetch_assoc()) {
    $parents[$row['source_class']][] = $row['target_class'];
}
$declared = [];
$declarations = [];
$res = $mysqli->query("SELECT class_name, property_name, visibility, is_static, is_constant, type_hint, doc_comment FROM properties");
while ($row = $res->fetch_assoc()) {
    $declared[$row['class_name'] . '::' . $row['property_name']] = (int) $row['is_static'] === 1;
    $declarations[$row['class_name'] . '::' . $row['property_name']] = $row;
}

function declaringClass(string $className, string $name, array $parents, array $declared): ?string
{
    $queue = $parents[$className] ?? [];
    $seen = [$className => true];
    while ($queue) {
        $candidate = array_shift($queue);
        if (isset($seen[$candidate])) continue;
        $seen[$candidate] = true;
        if (isset($declared[$candidate . '::' . $name])) return $candidate;
        array_push($queue, ...($parents[$candidate] ?? []));
    }
    return null;
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

    // declared_in: the class declaring the member, found up the chain for inherited ones; '' when unknown
    $declaredIn = $row['declared_id'] !== null ? $row['class_name'] : (declaringClass($row['class_name'], $name, $parents, $declared) ?? '');
    $isDeclaredStatic = $declaredIn !== '' && $declared[$declaredIn . '::' . $name];
    $declaration = $declaredIn !== '' ? $declarations[$declaredIn . '::' . $name] : null;

    $report[] = [
        'symbol_type' => $symbolType,
        'is_native' => false,
        'declared_in' => $declaredIn,
        'is_declared_static' => $isDeclaredStatic,
        'is_own_class' => $row['declared_id'] !== null,
        'visibility' => $declaration['visibility'] ?? null,
        'is_declared_constant' => (int) ($declaration['is_constant'] ?? 0) === 1,
        'type_hint' => $declaration['type_hint'] ?? null,
        'doc_comment' => $declaration['doc_comment'] ?? null,
        'symbol' => $symbol,
        'symbol_fqn' => $symbolFqn,
        'access' => accessLabel($row['access_type']),
    ] + usedBy($row['class_name'], $row['method_name']);
}

echo json_encode($report);
