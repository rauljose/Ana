<?php declare(strict_types=1);

require_once("../config/config.php");

function namespaceOf(string $className): string
{
    $parts = explode('\\', $className);
    array_pop($parts);
    return implode('\\', $parts) ?: '[Global]';
}

$report = [];
$blank = [
    'namespace' => '',
    'classes' => 0,
    'abstract_classes' => 0,
    'interfaces' => 0,
    'traits' => 0,
    'total_types' => 0,
    'methods' => 0,
    'total_cyclo' => 0,
    'avg_cyclo' => 0.0,
    'max_cyclo' => 0,
    'total_cog' => 0,
    'pure_methods_pct' => 0.0,
    'afferent_ca' => 0,
    'efferent_ce' => 0,
    'instability' => 0.0,
    'abstractness' => 0.0,
    'main_seq_distance' => 0.0,
    'calls_out' => 0,
    'calls_in' => 0,
    'calls_internal' => 0,
    'internal_call_ratio' => 0.0,
    'hotspot_score' => 0,
    'namespaces_used' => [],
    'namespaces_using_this' => [],
];
$pureCount = [];

// 1. Types per namespace
$res = $mysqli->query("SELECT class_name, is_interface, is_trait, is_abstract FROM classes");
while ($row = $res->fetch_assoc()) {
    $ns = namespaceOf($row['class_name']);
    $report[$ns] ??= ['namespace' => $ns] + $blank;
    $report[$ns]['total_types']++;
    if ($row['is_interface']) $report[$ns]['interfaces']++;
    elseif ($row['is_trait']) $report[$ns]['traits']++;
    elseif ($row['is_abstract']) $report[$ns]['abstract_classes']++;
    else $report[$ns]['classes']++;
}

// 2. Method aggregates per class, rolled up to the namespace
$res = $mysqli->query("
    SELECT class_name, COUNT(*) AS m, SUM(cyclo_complexity) AS cyclo, MAX(cyclo_complexity) AS max_cyclo,
           SUM(cognitive_complexity) AS cog, SUM(is_pure) AS pure
    FROM methods
    WHERE class_name != ''
    GROUP BY class_name
");
while ($row = $res->fetch_assoc()) {
    $ns = namespaceOf($row['class_name']);
    if (!isset($report[$ns])) continue;
    $report[$ns]['methods'] += (int) $row['m'];
    $report[$ns]['total_cyclo'] += (int) $row['cyclo'];
    $report[$ns]['total_cog'] += (int) $row['cog'];
    $report[$ns]['max_cyclo'] = max($report[$ns]['max_cyclo'], (int) $row['max_cyclo']);
    $pureCount[$ns] = ($pureCount[$ns] ?? 0) + (int) $row['pure'];
}

// 3. Calls between classes, rolled up to namespace pairs
$res = $mysqli->query("
    SELECT from_class, to_class, COUNT(*) AS calls
    FROM calls
    WHERE from_class != '' AND to_class != ''
    GROUP BY from_class, to_class
");
while ($row = $res->fetch_assoc()) {
    $from = namespaceOf($row['from_class']);
    $to = namespaceOf($row['to_class']);
    if (!isset($report[$from], $report[$to])) continue;
    $calls = (int) $row['calls'];

    if ($from === $to) {
        $report[$from]['calls_internal'] += $calls;
        continue;
    }
    $report[$from]['calls_out'] += $calls;
    $report[$to]['calls_in'] += $calls;
    $report[$from]['namespaces_used'][$to] = true;
    $report[$to]['namespaces_using_this'][$from] = true;
}

// 4. Derived metrics
foreach ($report as $ns => &$data) {
    $data['afferent_ca'] = count($data['namespaces_using_this']);
    $data['efferent_ce'] = count($data['namespaces_used']);
    $data['namespaces_used'] = implode(', ', array_keys($data['namespaces_used']));
    $data['namespaces_using_this'] = implode(', ', array_keys($data['namespaces_using_this']));

    $coupling = $data['afferent_ca'] + $data['efferent_ce'];
    if ($coupling > 0) $data['instability'] = round($data['efferent_ce'] / $coupling, 2);
    if ($data['total_types'] > 0) {
        $data['abstractness'] = round(($data['interfaces'] + $data['abstract_classes']) / $data['total_types'], 2);
    }
    $data['main_seq_distance'] = round(abs($data['abstractness'] + $data['instability'] - 1), 2);

    if ($data['methods'] > 0) {
        $data['avg_cyclo'] = round($data['total_cyclo'] / $data['methods'], 2);
        $data['pure_methods_pct'] = round((($pureCount[$ns] ?? 0) / $data['methods']) * 100, 2);
    }
    $allCalls = $data['calls_internal'] + $data['calls_out'];
    if ($allCalls > 0) $data['internal_call_ratio'] = round($data['calls_internal'] / $allCalls, 2);
    $data['hotspot_score'] = $data['total_cyclo'] * $data['afferent_ca'];
}
unset($data);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array_values($report));
