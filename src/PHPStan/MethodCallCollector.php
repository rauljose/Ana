<?php declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use ReflectionException;
use ReflectionFunction;

/**
 * @implements Collector<Node\Expr\CallLike, array{from_class: string, from_method: string, to_class: string, to_method: string, is_builtin: int}>
 */
class MethodCallCollector implements Collector
{
    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $functionRef = $scope->getFunction();
        if ($functionRef === null) {
            return null; // Not inside any function/method
        }

        $callerClass = $scope->getClassReflection();
        $fromClass = $callerClass !== null ? $callerClass->getName() : '';
        $fromMethod = $functionRef->getName();

        $toClassNames = [];
        $toMethod = null;
        $isBuiltin = 0;

        if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
            $calledOnType = $scope->getType($node->var);
            $toClassNames = $calledOnType->getObjectClassNames();
            $toMethod = $node->name->toString();
        } elseif ($node instanceof Node\Expr\StaticCall && $node->name instanceof Node\Identifier) {
            if ($node->class instanceof Node\Name) {
                $className = $node->class->toString();
                if (in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
                    $toClassNames = [$callerClass ? $callerClass->getName() : ''];
                } else {
                    $toClassNames = [$scope->resolveName($node->class)];
                }
            }
            $toMethod = $node->name->toString();
        } elseif ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
            $toClassNames = [$scope->resolveName($node->class)];
            $toMethod = '__construct';
        } elseif ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $toClassNames = ['']; // Empty class for procedural functions
            $funcNameStr = $scope->resolveName($node->name);
            $toMethod = $funcNameStr;

            if (function_exists($funcNameStr)) {
                try {
                    $ref = new ReflectionFunction($funcNameStr);
                    if ($ref->isInternal()) {
                        $isBuiltin = 1;
                    }
                } catch (ReflectionException $e) { }
            }
        }

        if (empty($toClassNames) || $toMethod === null) {
            return null;
        }

        return [
          'from_class' => $fromClass,
          'from_method' => $fromMethod,
          'to_class' => $toClassNames[0],
          'to_method' => $toMethod,
          'is_builtin' => $isBuiltin,
        ];
    }
}
