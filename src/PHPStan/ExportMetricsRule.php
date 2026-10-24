<?php declare(strict_types=1);

namespace App\PHPStan;

use mysqli;
use mysqli_sql_exception;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * @implements Rule<CollectedDataNode>
 */
class ExportMetricsRule implements Rule
{
    public function __construct(private readonly string $projectName)
    {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @return array<int, RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {

        /** @var CollectedDataNode $node */
        $classes = $node->get(ClassDefinitionCollector::class);
        $definitions = $node->get(MethodDefinitionCollector::class);
        $calls = $node->get(MethodCallCollector::class);

        $mysqli = require_once("../config/config.php");

        try {
        $mysqli->begin_transaction();

        $stmtClass = $mysqli->prepare("
            INSERT INTO classes (class_name, is_interface, is_trait, is_abstract, is_final, is_readonly, is_static) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            is_interface = VALUES(is_interface), is_trait = VALUES(is_trait), is_abstract = VALUES(is_abstract), 
            is_final = VALUES(is_final), is_readonly = VALUES(is_readonly), is_static = VALUES(is_static)
        ");

        $stmtRelation = $mysqli->prepare("
            INSERT IGNORE INTO class_relations (source_class, target_class, relation_type) 
            VALUES (?, ?, ?)
        ");

        $stmtProperty = $mysqli->prepare("
            INSERT INTO properties (class_name, property_name, visibility, is_static, is_readonly, is_constant, type_hint) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            visibility = VALUES(visibility), is_static = VALUES(is_static), is_readonly = VALUES(is_readonly),
            is_constant = VALUES(is_constant), type_hint = VALUES(type_hint)
        ");

        $mysqli->query("TRUNCATE TABLE project");
        $stmtProject = $mysqli->prepare("INSERT INTO project (name) VALUES (?)");
        $stmtProject->execute([$this->projectName]);

        $mysqli->query("TRUNCATE TABLE class_relations");
        $mysqli->query("TRUNCATE TABLE properties");

        foreach ($classes as  $fileClasses) {
            foreach ($fileClasses as $cls) {
                $stmtClass->execute([
                  $cls['class'], $cls['is_interface'], $cls['is_trait'], $cls['abstract'],
                  $cls['final'], $cls['readonly'], $cls['static'],
                ]);

                if ($cls['extends'] !== null) {
                    $stmtRelation->execute([$cls['class'], $cls['extends'], 'EXTENDS']);
                }
                foreach ($cls['implements'] as $interface) {
                    $stmtRelation->execute([$cls['class'], $interface, 'IMPLEMENTS']);
                }
                foreach ($cls['traits'] as $trait) {
                    $stmtRelation->execute([$cls['class'], $trait, 'USES']);
                }
                foreach ($cls['properties'] as $prop) {
                    $stmtProperty->execute([
                      $cls['class'], $prop['name'], $prop['visibility'], $prop['is_static'],
                      $prop['is_readonly'], $prop['is_constant'], $prop['type_hint']
                    ]);
                }
            }
        }

        $stmtMethod = $mysqli->prepare("
            INSERT INTO methods (
                class_name, method_name, visibility, is_static, is_builtin, cyclo_complexity, 
                cognitive_complexity, is_pure, num_parameters, num_default_parameters, 
                has_sensitive_parameter, num_reference_parameters, num_object_parameters, 
                return_type, is_ends, signature
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            visibility = VALUES(visibility), is_static = VALUES(is_static), is_builtin = VALUES(is_builtin),
            cyclo_complexity = VALUES(cyclo_complexity), cognitive_complexity = VALUES(cognitive_complexity), 
            is_pure = VALUES(is_pure), num_parameters = VALUES(num_parameters),
            num_default_parameters = VALUES(num_default_parameters), has_sensitive_parameter = VALUES(has_sensitive_parameter),
            num_reference_parameters = VALUES(num_reference_parameters), num_object_parameters = VALUES(num_object_parameters),
            return_type = VALUES(return_type), is_ends = VALUES(is_ends), signature = VALUES(signature)
        ");

        $stmtGlobals = $mysqli->prepare("
            INSERT IGNORE INTO method_globals (class_name, method_name, global_type, global_name) 
            VALUES (?, ?, ?, ?)
        ");

        $stmtPropAccess = $mysqli->prepare("
            INSERT IGNORE INTO method_property_access (class_name, method_name, property_name, access_type) 
            VALUES (?, ?, ?, ?)
        ");

        $mysqli->query("TRUNCATE TABLE method_globals");
        $mysqli->query("TRUNCATE TABLE method_property_access");

        foreach ($definitions as $file => $fileDefs) {
            foreach ($fileDefs as $def) {
                $stmtMethod->execute([
                  $def['class'], $def['method'], $def['visibility'], $def['static'], $def['builtin'],
                  $def['cyclo'], $def['cog'], $def['pure'], $def['num_params'], $def['num_defaults'],
                  $def['has_sensitive'], $def['num_ref_params'], $def['num_obj_params'],
                  $def['return_type'], $def['is_ends'], $def['signature']
                ]);

                foreach ($def['globals'] as $global) {
                    $stmtGlobals->execute([$def['class'], $def['method'], $global['type'], $global['name']]);
                }
                foreach ($def['type_hints'] as $targetClass) {
                    $stmtRelation->execute([$def['class'], $targetClass, 'TYPE_HINT']);
                }
                foreach ($def['prop_access'] as $access) {
                    $stmtPropAccess->execute([$def['class'], $def['method'], $access['prop'], $access['type']]);
                }
            }
        }

        $stmtCall = $mysqli->prepare("INSERT INTO calls (from_class, from_method, to_class, to_method) VALUES (?, ?, ?, ?)");
        $stmtNativeFunc = $mysqli->prepare("
            INSERT IGNORE INTO methods (
                class_name, method_name, visibility, is_static, is_builtin, cyclo_complexity, cognitive_complexity, 
                is_pure, num_parameters, num_default_parameters, has_sensitive_parameter, num_reference_parameters, 
                num_object_parameters, return_type, is_ends, signature
            ) VALUES ('', ?, 'public', 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 'mixed', 0, '')
        ");

        $mysqli->query("TRUNCATE TABLE calls");

        foreach ($calls as $file => $fileCalls) {
            foreach ($fileCalls as $call) {
                if ($call['is_builtin'] === 1) {
                    $stmtNativeFunc->execute([$call['to_method']]);
                }
                $stmtCall->execute([$call['from_class'], $call['from_method'], $call['to_class'], $call['to_method']]);
            }
        }
        } catch (mysqli_sql_exception $e) {
            return [RuleErrorBuilder::message("Database error: " . $e->getMessage())->build()];
        }
        $mysqli->commit();
        $mysqli->close();

        return [];
    }
}
