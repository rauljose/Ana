<?php declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;



/**
 * @implements Collector<InClassNode, array{class: string, is_interface: int, is_trait: int, abstract: int, final: int, readonly: int, static: int, extends: string|null, implements: list<string>, traits: list<string>, properties: list<array{name: string, visibility: string, is_static: int, is_readonly: int, is_constant: int, type_hint: string|null, doc_comment: string|null}>, doc_comment: string|null}>
 */
class ClassDefinitionCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $classRef = $node->getClassReflection();
        $origNode = $node->getOriginalNode();

        if (!$origNode instanceof Node\Stmt\ClassLike || $classRef->isAnonymous()) {
            return null;
        }

        $hasMethods = false;
        $allStatic = true;
        $traits = [];
        $properties = [];
        $printer = new PrettyPrinter();

        foreach ($origNode->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                $methodName = $stmt->name->toString();
                if (!in_array($methodName, ['__construct', '__clone', '__wakeup', '__destruct'])) {
                    $hasMethods = TRUE;
                    if(!$stmt->isStatic()) {
                        $allStatic = FALSE;
                    }
                }
            } elseif ($stmt instanceof Node\Stmt\TraitUse) {
                foreach ($stmt->traits as $trait) {
                    $traits[] = $trait->toString();
                }
            } elseif ($stmt instanceof Node\Stmt\Property) {
                $visibility = $stmt->isPrivate() ? 'private' : ($stmt->isProtected() ? 'protected' : 'public');
                $isStatic = $stmt->isStatic() ? 1 : 0;
                $isReadonly = $stmt->isReadonly() ? 1 : 0;
                $typeStr = $stmt->type !== null ? $printer->prettyPrint([$stmt->type]) : null;

                foreach ($stmt->props as $prop) {
                    $properties[] = [
                      'name' => $prop->name->toString(),
                      'visibility' => $visibility,
                      'is_static' => $isStatic,
                      'is_readonly' => $isReadonly,
                      'is_constant' => 0,
                      'type_hint' => $typeStr,
                      'doc_comment' => $stmt->getDocComment()?->getText()
                    ];
                }
            } elseif ($stmt instanceof Node\Stmt\ClassConst) {
                $visibility = $stmt->isPrivate() ? 'private' : ($stmt->isProtected() ? 'protected' : 'public');
                foreach ($stmt->consts as $const) {
                    $properties[] = [
                      'name' => $const->name->toString(),
                      'visibility' => $visibility,
                      'is_static' => 1,
                      'is_readonly' => 1,
                      'is_constant' => 1,
                      'type_hint' => null,
                      'doc_comment' => $stmt->getDocComment()?->getText()
                    ];
                }
            }
        }

        $extends = null;
        $implements = [];

        if ($origNode instanceof Node\Stmt\Class_) {
            if ($origNode->extends !== null) {
                $extends = $origNode->extends->toString();
            }
            foreach ($origNode->implements as $interface) {
                $implements[] = $interface->toString();
            }
        } elseif ($origNode instanceof Node\Stmt\Interface_) {
            foreach ($origNode->extends as $interface) {
                $implements[] = $interface->toString();
            }
        }

        return [
          'class' => $classRef->getName(),
          'is_interface' => $origNode instanceof Node\Stmt\Interface_ ? 1 : 0,
          'is_trait' => $origNode instanceof Node\Stmt\Trait_ ? 1 : 0,
          'abstract' => (method_exists($origNode, 'isAbstract') && $origNode->isAbstract()) ? 1 : 0,
          'final' => (method_exists($origNode, 'isFinal') && $origNode->isFinal()) ? 1 : 0,
          'readonly' => (method_exists($origNode, 'isReadonly') && $origNode->isReadonly()) ? 1 : 0,
          'static' => ($hasMethods && $allStatic) ? 1 : 0,
          'extends' => $extends,
          'implements' => $implements,
          'traits' => $traits,
          'properties' => $properties,
          'doc_comment' => $origNode->getDocComment()?->getText(),
        ];
    }
}
