<?php declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<Node\FunctionLike, array{class: string, method: string, visibility: string, static: int, builtin: int, cyclo: int, cog: int, pure: int, num_params: int, num_defaults: int, has_sensitive: int, num_ref_params: int, num_obj_params: int, return_type: string, is_ends: int, signature: string, doc_comment: string|null, globals: list<array{type: string, name: string}>, type_hints: list<string>, prop_access: list<array{prop: string, type: string}>}>
 */
class MethodDefinitionCollector implements Collector
{
    public function getNodeType(): string
    {
        return Node\FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof Node\Stmt\ClassMethod && !$node instanceof Node\Stmt\Function_) {
            return null;
        }

        $classRef = $scope->getClassReflection();
        $className = $classRef !== null ? $classRef->getName() : '';

        if ($node instanceof Node\Stmt\ClassMethod) {
            $methodName = $node->name->toString();
            $visibility = $node->isPrivate() ? 'private' : ($node->isProtected() ? 'protected' : 'public');
            $isStatic = $node->isStatic() ? 1 : 0;
        } else {
            $methodName = isset($node->namespacedName) ? $node->namespacedName->toString() : $node->name->toString();
            $visibility = 'public';
            $isStatic = 0;
        }

        $isPure = 0;
        $functionRef = $scope->getFunction();
        if ($functionRef !== null && $functionRef->isPure()->yes()) {
            $isPure = 1;
        } else {
            $doc = $node->getDocComment();
            if ($doc !== null) {
                $text = strtolower($doc->getText());
                // If it contains any variation of pure, force the flag to 1
                if (str_contains($text, '@pure') || str_contains($text, '@psalm-pure') || str_contains($text, '@phpstan-pure')) {
                    $isPure = 1;
                }
            }
        }

        $numParams = 0;
        $numDefaults = 0;
        $hasSensitive = 0;
        $numRefParams = 0;
        $numObjParams = 0;
        $typeHints = [];

        $extractTypeHints = function(?Node $typeNode) use (&$typeHints, &$extractTypeHints, $scope, &$numObjParams) {
            if ($typeNode === null) return;
            if ($typeNode instanceof Node\Name) {
                $name = $scope->resolveName($typeNode);
                $lower = strtolower($name);
                $primitives = ['self', 'static', 'parent', 'array', 'callable', 'iterable', 'mixed', 'void', 'never', 'string', 'int', 'float', 'bool', 'false', 'true', 'null'];
                if (!in_array($lower, $primitives, true)) {
                    $typeHints[] = $name;
                    $numObjParams++;
                }
            } elseif ($typeNode instanceof Node\UnionType || $typeNode instanceof Node\IntersectionType) {
                foreach ($typeNode->types as $t) $extractTypeHints($t);
            } elseif ($typeNode instanceof Node\NullableType) {
                $extractTypeHints($typeNode->type);
            }
        };

        foreach ($node->getParams() as $param) {
            $numParams++;
            if ($param->default !== null) $numDefaults++;
            if ($param->byRef) $numRefParams++;

            foreach ($param->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if (in_array($attr->name->toString(), ['SensitiveParameter', '\SensitiveParameter'])) {
                        $hasSensitive = 1;
                    }
                }
            }
            $prevObjCount = $numObjParams;
            $extractTypeHints($param->type);
            if ($numObjParams > $prevObjCount) {
                $numObjParams = $prevObjCount + 1;
            }
        }

        $printer = new PrettyPrinter();
        $returnTypeStr = '';
        if ($node->getReturnType() !== null) {
            $extractTypeHints($node->getReturnType());
            $returnTypeStr = $printer->prettyPrint([$node->getReturnType()]);
        }

        $stmts = $node->getStmts() ?? [];
        if ($returnTypeStr === '') {
            $hasReturnExpr = false;
            $traverseReturn = function(Node $subNode) use (&$traverseReturn, &$hasReturnExpr) {
                if ($subNode instanceof Node\Stmt\Return_ && $subNode->expr !== null) $hasReturnExpr = true;
                if ($subNode instanceof Node\Expr\Closure || $subNode instanceof Node\Stmt\Class_ || $subNode instanceof Node\Expr\ArrowFunction) return;
                foreach ($subNode->getSubNodeNames() as $name) {
                    $childNode = $subNode->$name;
                    if (is_array($childNode)) {
                        foreach ($childNode as $c) if ($c instanceof Node) $traverseReturn($c);
                    } elseif ($childNode instanceof Node) {
                        $traverseReturn($childNode);
                    }
                }
            };
            foreach ($stmts as $stmt) {
                if ($stmt instanceof Node) $traverseReturn($stmt);
            }
            $returnTypeStr = $hasReturnExpr ? 'mixed' : 'void';
        }

        $isEnds = 0;
        $doc = $node->getDocComment();
        if ($doc !== null) {
            $text = strtolower($doc->getText());
            if (str_contains($text, '@noreturn') || str_contains($text, '@return never') || str_contains($text, '@exit') || str_contains($text, '@die')) {
                $isEnds = 1;
            }
        }
        if ($returnTypeStr === 'never') $isEnds = 1;
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (in_array($attr->name->toString(), ['NoReturn', 'JetBrains\PhpStorm\NoReturn'], true)) $isEnds = 1;
            }
        }

        $docComment = $node->getDocComment();

        // The pretty printer prints attached comments: keep the doc block out of the signature
        $sigNode = clone $node;
        $sigNode->stmts = null;
        $sigNode->setAttribute('comments', []);
        $signature = rtrim($printer->prettyPrint([$sigNode]), "; \n\r\t");

        list($cyclo, $cog, $globals, $propAccess) = $this->analyzeMethodBody($stmts);

        return [
          'class' => $className,
          'method' => $methodName,
          'visibility' => $visibility,
          'static' => $isStatic,
          'builtin' => 0,
          'cyclo' => $cyclo,
          'cog' => $cog,
          'pure' => $isPure,
          'num_params' => $numParams,
          'num_defaults' => $numDefaults,
          'has_sensitive' => $hasSensitive,
          'num_ref_params' => $numRefParams,
          'num_obj_params' => $numObjParams,
          'return_type' => $returnTypeStr,
          'is_ends' => $isEnds,
          'signature' => $signature,
          'doc_comment' => $docComment?->getText(),
          'globals' => $globals,
          'type_hints' => array_values(array_unique($typeHints)),
          'prop_access' => $propAccess,
        ];
    }

    private function analyzeMethodBody(array $stmts): array
    {
        $cyclo = 1;
        $cog = 0;
        $globals = [];
        $propAccess = [];

        $traverse = function(Node $node, int $nesting) use (&$traverse, &$cyclo, &$cog, &$globals, &$propAccess): void {
            $nodeClass = $node::class;

            $cycloClasses = [
              Node\Stmt\If_::class, Node\Stmt\ElseIf_::class, Node\Stmt\For_::class,
              Node\Stmt\Foreach_::class, Node\Stmt\While_::class, Node\Stmt\Do_::class,
              Node\Stmt\Catch_::class, Node\Expr\BinaryOp\BooleanAnd::class,
              Node\Expr\BinaryOp\BooleanOr::class, Node\Expr\BinaryOp\Coalesce::class,
              Node\Expr\Ternary::class,
            ];
            if (in_array($nodeClass, $cycloClasses, true)) $cyclo++;
            elseif ($node instanceof Node\Stmt\Case_ && $node->cond !== null) $cyclo++;

            $cogClasses = [
              Node\Stmt\If_::class, Node\Stmt\For_::class, Node\Stmt\Foreach_::class,
              Node\Stmt\While_::class, Node\Stmt\Do_::class, Node\Stmt\Switch_::class,
              Node\Stmt\Catch_::class,
            ];

            $nextNesting = $nesting;
            if (in_array($nodeClass, $cogClasses, true)) {
                $cog += (1 + $nesting);
                $nextNesting = $nesting + 1;
            } elseif (in_array($nodeClass, [Node\Stmt\ElseIf_::class, Node\Stmt\Else_::class], true)) {
                $cog += 1;
            }

            if ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp || $node instanceof Node\Expr\AssignRef) {
                $var = $node->var;
                if ($var instanceof Node\Expr\PropertyFetch && $var->var instanceof Node\Expr\Variable && is_string($var->var->name) && $var->var->name === 'this' && $var->name instanceof Node\Identifier) {
                    $propAccess[$var->name->toString()] = 'WRITE';
                } elseif ($var instanceof Node\Expr\StaticPropertyFetch && $var->class instanceof Node\Name && in_array(strtolower($var->class->toString()), ['self', 'static'], true) && $var->name instanceof Node\VarLikeIdentifier) {
                    $propAccess[$var->name->toString()] = 'WRITE';
                }

                if ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignRef) {
                    $traverse($node->expr, $nextNesting);
                }
                return;
            }

            if ($node instanceof Node\Expr\PropertyFetch && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) && $node->var->name === 'this' && $node->name instanceof Node\Identifier) {
                $name = $node->name->toString();
                if (!isset($propAccess[$name])) $propAccess[$name] = 'READ';
            } elseif ($node instanceof Node\Expr\StaticPropertyFetch && $node->class instanceof Node\Name && in_array(strtolower($node->class->toString()), ['self', 'static'], true) && $node->name instanceof Node\VarLikeIdentifier) {
                $name = $node->name->toString();
                if (!isset($propAccess[$name])) $propAccess[$name] = 'READ';
            } elseif ($node instanceof Node\Expr\ClassConstFetch && $node->class instanceof Node\Name && in_array(strtolower($node->class->toString()), ['self', 'static'], true) && $node->name instanceof Node\Identifier) {
                $name = $node->name->toString();
                if (!isset($propAccess[$name])) $propAccess[$name] = 'READ';
            }

            if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
                $superglobals = ['_GET', '_POST', '_SESSION', '_COOKIE', '_SERVER', '_ENV', '_FILES', '_REQUEST', 'GLOBALS'];
                if (in_array($node->name, $superglobals, true)) {
                    $globals[] = ['type' => 'SUPERGLOBAL', 'name' => $node->name];
                }
            } elseif ($node instanceof Node\Stmt\Global_) {
                foreach ($node->vars as $var) {
                    if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
                        $globals[] = ['type' => 'GLOBAL_KEYWORD', 'name' => $var->name];
                    }
                }
            } elseif ($node instanceof Node\Expr\ConstFetch) {
                $name = $node->name->toString();
                if (!in_array(strtolower($name), ['true', 'false', 'null'], true)) {
                    $globals[] = ['type' => 'CONSTANT', 'name' => $name];
                }
            }

            foreach ($node->getSubNodeNames() as $name) {
                $subNode = $node->$name;
                if (is_array($subNode)) {
                    foreach ($subNode as $child) {
                        if ($child instanceof Node) $traverse($child, $nextNesting);
                    }
                } elseif ($subNode instanceof Node) {
                    $traverse($subNode, $nextNesting);
                }
            }
        };

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node) $traverse($stmt, 0);
        }

        $uniqueGlobals = array_map('unserialize', array_unique(array_map('serialize', $globals)));

        $finalPropAccess = [];
        foreach ($propAccess as $prop => $type) {
            $finalPropAccess[] = ['prop' => $prop, 'type' => $type];
        }

        return [$cyclo, $cog, array_values($uniqueGlobals), $finalPropAccess];
    }
}
