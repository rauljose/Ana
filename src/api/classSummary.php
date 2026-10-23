<?php declare(strict_types=1);

require_once("../config/config.php");

$report = [];

// 1. Base Classes, Modifiers & Namespaces
$res = $mysqli->query("
    SELECT class_name, is_interface, is_trait, is_abstract, is_final, is_readonly, is_static 
    FROM classes
");
while ($row = $res->fetch_assoc()) {
    $className = $row['class_name'] === '' ? '[Global Scope]' : $row['class_name'];
    
    $type = 'Class';
    if ($row['is_interface']) $type = 'Interface';
    elseif ($row['is_trait']) $type = 'Trait';
    elseif ($row['is_abstract']) $type = 'Abstract Class';
    elseif ($row['is_final']) $type = 'Final Class';
    
    $parts = explode('\\', $className);
    $shortName = array_pop($parts);
    $namespace = implode('\\', $parts) ?: '[Global]';
    
    $report[$className] = [
        'namespace' => $namespace,
        'short_name' => $shortName,
        'class_name' => $className,
        'type' => $type,
        'is_static_class' => (bool) $row['is_static'],
        'is_readonly_class' => (bool) $row['is_readonly'],
        'extends' => '',
        'implements' => '',
        'uses_traits' => '',
        'children_count' => 0,
        
        // Methods
        'total_methods' => 0,
        'public_methods' => 0,
        'protected_methods' => 0,
        'private_methods' => 0,
        'pure_methods_pct' => '0.00',
        
        // Complexity
        'total_cyclo' => 0,
        'avg_cyclo' => '0.00',
        'max_cyclo_method' => '',
        'max_cyclo_val' => 0,
        'total_cog' => 0,
        'avg_cog' => '0.00',
        'max_cog_method' => '',
        'max_cog_val' => 0,
        
        // Parameters & Dependencies
        'constructor_params' => 0,
        'total_reference_params' => 0,
        'total_object_params' => 0,
        'methods_ending_script' => 0,
        'methods_with_sensitive_params' => 0,
        
        // Coupling
        'afferent_ca' => 0,
        'efferent_ce' => 0,
        'instability' => '0.00',
        'classes_used' => '',
        'classes_using_this' => '',
        'mpc' => 0,
        'dac' => 0,
        
        // State & Cohesion
        'lcom' => '0.00',
        'mutable_properties' => 0,
        'readonly_properties' => 0,
        'properties_actually_written' => 0,
        'uses_superglobals' => false,
        'uses_global_keyword' => false,
        'uses_constants' => false,
        
        // Archetypes
        'is_god_class' => false,
        'is_data_class' => false,
        'is_stateless_service' => false
    ];
}

// 2. Class Relations
$res = $mysqli->query("SELECT source_class, target_class, relation_type FROM class_relations");
while ($row = $res->fetch_assoc()) {
    $source = $row['source_class'];
    $target = $row['target_class'];
    $type = $row['relation_type'];

    if (isset($report[$source])) {
        if ($type === 'EXTENDS') $report[$source]['extends'] = $target;
        if ($type === 'IMPLEMENTS') $report[$source]['implements'] .= ($report[$source]['implements'] ? ', ' : '') . $target;
        if ($type === 'USES') $report[$source]['uses_traits'] .= ($report[$source]['uses_traits'] ? ', ' : '') . $target;
    }
    
    if ($type === 'EXTENDS' && isset($report[$target])) {
        $report[$target]['children_count']++;
    }
}

// 3. Method Aggregates
$res = $mysqli->query("
    SELECT 
        class_name,
        COUNT(*) as tot_methods,
        SUM(CASE WHEN visibility = 'public' THEN 1 ELSE 0 END) as pub_count,
        SUM(CASE WHEN visibility = 'protected' THEN 1 ELSE 0 END) as prot_count,
        SUM(CASE WHEN visibility = 'private' THEN 1 ELSE 0 END) as priv_count,
        SUM(cyclo_complexity) as tot_cyclo,
        SUM(cognitive_complexity) as tot_cog,
        SUM(is_pure) as pure_count,
        SUM(num_reference_parameters) as tot_ref_params,
        SUM(num_object_parameters) as tot_obj_params,
        SUM(is_ends) as tot_ends,
        SUM(has_sensitive_parameter) as tot_sensitive,
        MAX(CASE WHEN method_name = '__construct' THEN num_parameters ELSE 0 END) as constructor_params
    FROM methods
    GROUP BY class_name
");
while ($row = $res->fetch_assoc()) {
    $className = $row['class_name'] === '' ? '[Global Scope]' : $row['class_name'];
    if (!isset($report[$className])) continue;

    $totalMethods = (int) $row['tot_methods'];
    $report[$className]['total_methods'] = $totalMethods;
    $report[$className]['public_methods'] = (int) $row['pub_count'];
    $report[$className]['protected_methods'] = (int) $row['prot_count'];
    $report[$className]['private_methods'] = (int) $row['priv_count'];
    $report[$className]['total_cyclo'] = (int) $row['tot_cyclo'];
    $report[$className]['total_cog'] = (int) $row['tot_cog'];
    $report[$className]['total_reference_params'] = (int) $row['tot_ref_params'];
    $report[$className]['total_object_params'] = (int) $row['tot_obj_params'];
    $report[$className]['methods_ending_script'] = (int) $row['tot_ends'];
    $report[$className]['methods_with_sensitive_params'] = (int) $row['tot_sensitive'];
    $report[$className]['constructor_params'] = (int) $row['constructor_params'];
    
    if ($totalMethods > 0) {
        $report[$className]['pure_methods_pct'] = number_format(($row['pure_count'] / $totalMethods) * 100, 2, '.', '');
        $report[$className]['avg_cyclo'] = number_format($row['tot_cyclo'] / $totalMethods, 2, '.', '');
        $report[$className]['avg_cog'] = number_format($row['tot_cog'] / $totalMethods, 2, '.', '');
    }
}

// 4. Max Complexity Methods
$res = $mysqli->query("
    WITH RankedMethods AS (
        SELECT class_name, method_name, cyclo_complexity, cognitive_complexity,
               ROW_NUMBER() OVER(PARTITION BY class_name ORDER BY cyclo_complexity DESC) as rn_cyclo,
               ROW_NUMBER() OVER(PARTITION BY class_name ORDER BY cognitive_complexity DESC) as rn_cog
        FROM methods
    )
    SELECT class_name, method_name, cyclo_complexity, cognitive_complexity, rn_cyclo, rn_cog
    FROM RankedMethods
    WHERE rn_cyclo = 1 OR rn_cog = 1
");
while ($row = $res->fetch_assoc()) {
    $className = $row['class_name'] === '' ? '[Global Scope]' : $row['class_name'];
    if (!isset($report[$className])) continue;

    if ($row['rn_cyclo'] == 1) {
        $report[$className]['max_cyclo_method'] = $row['method_name'];
        $report[$className]['max_cyclo_val'] = (int) $row['cyclo_complexity'];
    }
    if ($row['rn_cog'] == 1) {
        $report[$className]['max_cog_method'] = $row['method_name'];
        $report[$className]['max_cog_val'] = (int) $row['cognitive_complexity'];
    }
}

// 5. Properties (Definitions vs Actual Writes)
$res = $mysqli->query("
    SELECT 
        class_name,
        SUM(CASE WHEN is_readonly = 0 AND is_constant = 0 THEN 1 ELSE 0 END) as mutable,
        SUM(CASE WHEN is_readonly = 1 OR is_constant = 1 THEN 1 ELSE 0 END) as immutable
    FROM properties
    GROUP BY class_name
");
while ($row = $res->fetch_assoc()) {
    if (isset($report[$row['class_name']])) {
        $report[$row['class_name']]['mutable_properties'] = (int) $row['mutable'];
        $report[$row['class_name']]['readonly_properties'] = (int) $row['immutable'];
    }
}

$res = $mysqli->query("
    SELECT class_name, COUNT(DISTINCT property_name) as written_count
    FROM method_property_access
    WHERE access_type = 'WRITE'
    GROUP BY class_name
");
while ($row = $res->fetch_assoc()) {
    if (isset($report[$row['class_name']])) {
        $report[$row['class_name']]['properties_actually_written'] = (int) $row['written_count'];
    }
}

// 5.5 Data Abstraction Coupling (DAC)
$res = $mysqli->query("
    SELECT class_name, type_hint 
    FROM properties 
    WHERE type_hint IS NOT NULL AND type_hint != '' AND is_constant = 0
");
$primitives = ['int', 'float', 'string', 'bool', 'array', 'mixed', 'object', 'callable', 'iterable', 'null', 'false', 'true', 'void', 'never'];
while ($row = $res->fetch_assoc()) {
    $className = $row['class_name'];
    if (!isset($report[$className])) continue;
    
    $cleanTypeHint = str_replace(['?', '&'], '|', strtolower($row['type_hint']));
    $types = explode('|', $cleanTypeHint);
    
    $isComplex = false;
    foreach ($types as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $primitives, true)) {
            $isComplex = true;
            break;
        }
    }
    
    if ($isComplex) {
        $report[$className]['dac']++;
    }
}

// 6. Coupling & Instability
$res = $mysqli->query("
    SELECT from_class, GROUP_CONCAT(DISTINCT to_class SEPARATOR ', ') as targets, COUNT(DISTINCT to_class) as ce 
    FROM calls WHERE from_class != to_class AND from_class != '' GROUP BY from_class
");
while ($row = $res->fetch_assoc()) {
    if (isset($report[$row['from_class']])) {
        $report[$row['from_class']]['efferent_ce'] = (int) $row['ce'];
        $report[$row['from_class']]['classes_used'] = $row['targets'];
    }
}

$res = $mysqli->query("
    SELECT to_class, GROUP_CONCAT(DISTINCT from_class SEPARATOR ', ') as sources, COUNT(DISTINCT from_class) as ca 
    FROM calls WHERE from_class != to_class AND to_class != '' GROUP BY to_class
");
while ($row = $res->fetch_assoc()) {
    if (isset($report[$row['to_class']])) {
        $report[$row['to_class']]['afferent_ca'] = (int) $row['ca'];
        $report[$row['to_class']]['classes_using_this'] = $row['sources'];
    }
}

foreach ($report as $key => &$data) {
    $totalCoupling = $data['afferent_ca'] + $data['efferent_ce'];
    if ($totalCoupling > 0) {
        $data['instability'] = number_format($data['efferent_ce'] / $totalCoupling, 2, '.', '');
    }
}
unset($data);

// 6.5 Message Passing Coupling (MPC)
$res = $mysqli->query("
    SELECT from_class, COUNT(*) as mpc_count 
    FROM calls 
    WHERE from_class != to_class 
      AND to_class != '' 
    GROUP BY from_class
");
while ($row = $res->fetch_assoc()) {
    if (isset($report[$row['from_class']])) {
        $report[$row['from_class']]['mpc'] = (int) $row['mpc_count'];
    }
}

// 7. Global State Usages
$res = $mysqli->query("
    SELECT class_name, global_type 
    FROM method_globals
    GROUP BY class_name, global_type
");
while ($row = $res->fetch_assoc()) {
    $className = $row['class_name'] === '' ? '[Global Scope]' : $row['class_name'];
    if (!isset($report[$className])) continue;

    if ($row['global_type'] === 'SUPERGLOBAL') $report[$className]['uses_superglobals'] = true;
    if ($row['global_type'] === 'GLOBAL_KEYWORD') $report[$className]['uses_global_keyword'] = true;
    if ($row['global_type'] === 'CONSTANT') $report[$className]['uses_constants'] = true;
}

// 8. LCOM & Smell Flags Calculation
$res = $mysqli->query("
    SELECT 
        c.class_name,
        (SELECT COUNT(DISTINCT method_name) FROM methods WHERE class_name = c.class_name) as m,
        (SELECT COUNT(DISTINCT property_name) FROM properties WHERE class_name = c.class_name) as a,
        (
            SELECT COUNT(*) 
            FROM (SELECT DISTINCT class_name, method_name, property_name FROM method_property_access) unique_access
            WHERE class_name = c.class_name
        ) as sum_mA
    FROM classes c
    WHERE c.is_interface = 0 AND c.is_trait = 0
");

while ($row = $res->fetch_assoc()) {
    $className = $row['class_name'] === '' ? '[Global Scope]' : $row['class_name'];
    if (!isset($report[$className])) continue;

    $m = (float) $row['m'];
    $a = (float) $row['a'];
    $sum_mA = (float) $row['sum_mA'];
    $lcom = 0.0;

    if ($m > 1 && $a > 0) {
        $lcom = ($m - ($sum_mA / $a)) / ($m - 1);
        if ($lcom < 0) $lcom = 0.0; 
        if ($lcom > 2) $lcom = 2.0; 
    }

    $report[$className]['lcom'] = number_format($lcom, 2, '.', '');
}

foreach ($report as $key => &$data) {
    if ($data['type'] === 'Class' || $data['type'] === 'Final Class' || $data['type'] === 'Abstract Class') {
        if ((float)$data['lcom'] >= 0.8 && $data['total_cyclo'] > 40 && $data['efferent_ce'] > 10) {
            $data['is_god_class'] = true;
        }
        if ($data['mutable_properties'] > 2 && $data['total_methods'] <= 3 && (float)$data['avg_cyclo'] <= 1.5) {
            $data['is_data_class'] = true;
        }
        if ($data['total_methods'] > 0 && $data['mutable_properties'] === 0 && (float)$data['pure_methods_pct'] > 80) {
            $data['is_stateless_service'] = true;
        }
    }
}
unset($data);

// 9. Output as JSON
header('Content-Type: application/json');
echo json_encode(array_values($report), JSON_PRETTY_PRINT);