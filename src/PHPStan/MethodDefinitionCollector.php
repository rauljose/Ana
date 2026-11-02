<?php declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<Node\FunctionLike, array{class: string, method: string, visibility: string, static: int, builtin: int, cyclo: int, cog: int, pure: int, num_params: int, num_defaults: int, has_sensitive: int, num_ref_params: int, num_obj_params: int, return_type: string, is_ends: int, signature: string, doc_comment: string|null, globals: list<array{type: string, name: string, access: string}>, type_hints: list<string>, prop_access: list<array{prop: string, type: string}>, sql: list<array{operation: string, object_type: string, object_name: string, access: string, is_dynamic: int, source: string, snippet: string}>, firebase: list<array{service: string, object_type: string, path: string, operation: string, access: string, mode: string, is_dynamic: int, snippet: string}>}>
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
        $constantString = fn(Node $constant): ?string => $this->constantString($constant, $scope);
        $sql = (new SqlUsageFinder())->find($stmts, $constantString);
        $firebase = (new FirebaseUsageFinder())->find($stmts, $constantString, $node->getParams());

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
          'sql' => $sql,
          'firebase' => $firebase,
        ];
    }

    private function analyzeMethodBody(array $stmts): array
    {
        $cyclo = 1;
        $cog = 0;
        $globals = [];
        $symbolAccess = [];
        $globalVars = [];
        $superglobals = ['_GET', '_POST', '_SESSION', '_COOKIE', '_SERVER', '_ENV', '_FILES', '_REQUEST', 'GLOBALS'];
        $isSelfOrStatic = fn(Node $class): bool => $class instanceof Node\Name && in_array(strtolower($class->toString()), ['self', 'static'], true);

        // The symbol a node reads or writes: a property or constant of this class, a superglobal or a variable
        // declared with the global keyword. Returns [kind, name] or null
        $symbolOf = function(Node $node) use (&$globalVars, $superglobals, $isSelfOrStatic): ?array {
            if ($node instanceof Node\Expr\PropertyFetch && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) && $node->var->name === 'this' && $node->name instanceof Node\Identifier) {
                return ['prop', $node->name->toString()];
            }
            if ($node instanceof Node\Expr\StaticPropertyFetch && $isSelfOrStatic($node->class) && $node->name instanceof Node\VarLikeIdentifier) {
                return ['prop', $node->name->toString()];
            }
            if ($node instanceof Node\Expr\ClassConstFetch && $isSelfOrStatic($node->class) && $node->name instanceof Node\Identifier && strtolower($node->name->toString()) !== 'class') {
                return ['prop', $node->name->toString()];
            }
            if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
                if (in_array($node->name, $superglobals, true)) return ['super', $node->name];
                if (isset($globalVars[$node->name])) return ['global', $node->name];
            }
            return null;
        };

        // flag is READ or WRITE; a symbol with both ends up as READ_WRITE
        $record = function(array $symbol, string $flag) use (&$symbolAccess): void {
            $symbolAccess[$symbol[0] . '|' . $symbol[1]][$flag] = true;
        };

        // $this->items['a']['b'] changes $this->items
        $root = function(Node $node): Node {
            while ($node instanceof Node\Expr\ArrayDimFetch) $node = $node->var;
            return $node;
        };

        $traverse = null;
        $markTarget = null;

        // Marks the target of an assignment, increment, unset or reference. Anything that is not a tracked
        // symbol is traversed normally, so the reads and calls inside it are still counted
        $markTarget = function(Node $target, array $flags, int $nesting) use (&$traverse, &$markTarget, $symbolOf, $record, $root): void {
            if ($target instanceof Node\Expr\Array_ || $target instanceof Node\Expr\List_) {
                foreach ($target->items as $item) {
                    if ($item !== null) $markTarget($item->value, $flags, $nesting);
                }
                return;
            }

            $symbol = $symbolOf($root($target));
            if ($symbol === null) {
                $traverse($target, $nesting);
                return;
            }

            // Index expressions are plain reads: $this->items[$i]
            for ($node = $target; $node instanceof Node\Expr\ArrayDimFetch; $node = $node->var) {
                if ($node->dim !== null) $traverse($node->dim, $nesting);
            }
            foreach ($flags as $flag) $record($symbol, $flag);
        };

        $traverse = function(Node $node, int $nesting) use (&$traverse, &$cyclo, &$cog, &$globals, &$globalVars, &$symbolAccess, $symbolOf, $record, $root, $markTarget): void {
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

            // Writes: plain assignment only writes, compound assignment and ++ -- read and write
            if ($node instanceof Node\Expr\Assign) {
                $markTarget($node->var, ['WRITE'], $nextNesting);
                $traverse($node->expr, $nextNesting);
                return;
            }
            if ($node instanceof Node\Expr\AssignRef) {
                $markTarget($node->var, ['WRITE'], $nextNesting);
                $markTarget($node->expr, ['READ', 'WRITE'], $nextNesting);
                return;
            }
            if ($node instanceof Node\Expr\AssignOp) {
                $markTarget($node->var, ['READ', 'WRITE'], $nextNesting);
                $traverse($node->expr, $nextNesting);
                return;
            }
            if ($node instanceof Node\Expr\PreInc || $node instanceof Node\Expr\PostInc || $node instanceof Node\Expr\PreDec || $node instanceof Node\Expr\PostDec) {
                $markTarget($node->var, ['READ', 'WRITE'], $nextNesting);
                return;
            }
            if ($node instanceof Node\Stmt\Unset_) {
                foreach ($node->vars as $var) $markTarget($var, ['WRITE'], $nextNesting);
                return;
            }

            // foreach by reference changes the array; the generic traversal below adds the read
            if ($node instanceof Node\Stmt\Foreach_ && $node->byRef) {
                $symbol = $symbolOf($root($node->expr));
                if ($symbol !== null) $record($symbol, 'WRITE');
            }

            // Arguments passed to by-reference parameters of native functions (sort, array_push, preg_match...)
            if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && !$node->isFirstClassCallable()) {
                $params = $this->nativeFunctionParams($node->name);
                foreach ($node->getArgs() as $index => $arg) {
                    if ($arg->unpack || $arg->name !== null) continue;
                    $param = $params[$index] ?? (($params !== [] && end($params)->isVariadic()) ? end($params) : null);
                    if ($param === null || !$param->isPassedByReference()) continue;
                    $symbol = $symbolOf($root($arg->value));
                    if ($symbol !== null) $record($symbol, 'WRITE');
                }
            }

            // Reads
            $symbol = $symbolOf($node);
            if ($symbol !== null) $record($symbol, 'READ');

            if ($node instanceof Node\Stmt\Global_) {
                foreach ($node->vars as $var) {
                    if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
                        $globalVars[$var->name] = true;
                        // Declared even if the method never uses it
                        $symbolAccess['global|' . $var->name] ??= [];
                    }
                }
            } elseif ($node instanceof Node\Expr\ConstFetch) {
                $name = $node->name->toString();
                if (!in_array(strtolower($name), ['true', 'false', 'null'], true)) {
                    $globals[] = ['type' => 'CONSTANT', 'name' => $name, 'access' => 'READ'];
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

        $accessOf = fn(array $flags): string => match (true) {
            isset($flags['READ'], $flags['WRITE']) => 'READ_WRITE',
            isset($flags['WRITE']) => 'WRITE',
            default => 'READ',
        };

        $globalTypes = ['super' => 'SUPERGLOBAL', 'global' => 'GLOBAL_KEYWORD'];
        $finalPropAccess = [];
        foreach ($symbolAccess as $key => $flags) {
            [$kind, $name] = explode('|', $key, 2);
            if ($kind === 'prop') {
                $finalPropAccess[] = ['prop' => $name, 'type' => $accessOf($flags)];
            } else {
                $globals[] = ['type' => $globalTypes[$kind], 'name' => $name, 'access' => $accessOf($flags)];
            }
        }

        $uniqueGlobals = array_map('unserialize', array_unique(array_map('serialize', $globals)));

        return [$cyclo, $cog, array_values($uniqueGlobals), $finalPropAccess];
    }

    // The string a constant fetch (self::QUERY, TABLE_NAME) holds, when PHPStan knows it
    private function constantString(Node $node, Scope $scope): ?string
    {
        try {
            $strings = $scope->getType($node)->getConstantStrings();
        } catch (\Throwable) {
            return null;
        }
        return count($strings) === 1 ? $strings[0]->getValue() : null;
    }

    /**
     * Parameters of a native function, to know which arguments are passed by reference.
     * Empty when the function is not a PHP built-in, or is unknown to the analysing process.
     *
     * @return list<\ReflectionParameter>
     */
    private function nativeFunctionParams(Node\Name $name): array
    {
        $candidates = array_filter([
            ($name->getAttribute('resolvedName') ?? $name)->toString(),
            $name->toString(),
            $name->getLast(),
        ]);
        foreach ($candidates as $candidate) {
            if (!function_exists($candidate)) continue;
            $reflection = new \ReflectionFunction($candidate);
            return $reflection->isInternal() ? $reflection->getParameters() : [];
        }
        return [];
    }
}
