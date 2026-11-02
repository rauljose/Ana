<?php declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * The SQL a method body holds: string literals, concatenations and interpolated strings that read as SQL
 * (a variable inside them makes that part '?'), and calls of the Sqler helpers that take the table as an argument.
 * Where the SQL runs is not followed: it counts for the method that holds the text.
 */
final class SqlUsageFinder
{
    // Ocallit\Sqler\QueryBuilder: method name (lower case) => [operations, fewest arguments]. Add your own helpers here
    public const HELPERS = [
        'insert' => [['INSERT'], 2],
        'update' => [['UPDATE'], 3],
        'junctiontable' => [['DELETE', 'INSERT'], 5],
    ];

    /**
     * @param array<Node> $statements the body of a method or function
     * @param callable(Node): ?string $constantValue the string a constant fetch holds, or null when it is not a known string
     * @return list<array{operation: string, object_type: string, object_name: string, access: string, is_dynamic: int, source: string, snippet: string}>
     */
    public function find(array $statements, callable $constantValue): array
    {
        $found = [];
        $record = function (array $usages, string $source, string $snippet) use (&$found): void {
            foreach ($usages as $usage) {
                $key = $usage['operation'] . '|' . $usage['object_type'] . '|' . $usage['object_name'] . '|' . $usage['access'] . '|' . $source;
                $found[$key] ??= $usage + ['source' => $source, 'snippet' => $snippet];
            }
        };
        $fold = fn(Node $node): ?string => $this->fold($node, $constantValue);

        $visitor = new class($fold, $record) extends NodeVisitorAbstract {
            public function __construct(private readonly \Closure $fold, private readonly \Closure $record) {}

            public function enterNode(Node $node): ?int
            {
                $isText = $node instanceof Node\Scalar\String_ || $node instanceof Node\Scalar\InterpolatedString || $node instanceof Node\Expr\BinaryOp\Concat;
                if ($isText || $node instanceof Node\Expr\ClassConstFetch || $node instanceof Node\Expr\ConstFetch) {
                    $text = ($this->fold)($node);
                    if ($text !== null) {
                        $usages = SqlStatements::parse($text);
                        if ($usages !== []) ($this->record)($usages, 'SQL', self::snippetOf($text));
                    }
                    // The parts of a string are already in the text
                    return $isText ? NodeVisitor::DONT_TRAVERSE_CHILDREN : null;
                }

                if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\StaticCall) {
                    $helper = $this->helperUsages($node);
                    if ($helper !== null) ($this->record)($helper[0], 'HELPER', $helper[1]);
                }
                return null;
            }

            private static function snippetOf(string $text): string
            {
                return mb_substr(trim(preg_replace('/\s+/', ' ', str_replace(SqlStatements::DYNAMIC, '{?}', $text))), 0, 255);
            }

            /** @return array{0: list<array>, 1: string}|null */
            private function helperUsages(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall|Node\Expr\StaticCall $call): ?array
            {
                if (!$call->name instanceof Node\Identifier) return null;
                $name = $call->name->toString();
                $helper = SqlUsageFinder::HELPERS[strtolower($name)] ?? null;
                if ($helper === null || $call->isFirstClassCallable()) return null;

                $arguments = $call->getArgs();
                if (count($arguments) < $helper[1] || $arguments[0]->unpack) return null;
                $table = ($this->fold)($arguments[0]->value);
                // Only a plain table name: a variable here is more likely some other insert() or update()
                if ($table === null || !preg_match('/^[A-Za-z_][\w$]*(?:\.[A-Za-z_][\w$]*)?$/', $table)) return null;

                $usages = array_map(fn(string $operation): array => [
                    'operation' => $operation, 'object_type' => 'table', 'object_name' => $table, 'access' => 'WRITE', 'is_dynamic' => 0,
                ], $helper[0]);
                return [$usages, '->' . $name . "('" . $table . "', …)"];
            }
        };

        $traverser = new NodeTraverser($visitor);
        $traverser->traverse($statements);
        return array_values($found);
    }

    // The text of a string expression; null when there is none. A part that is not known becomes SqlStatements::DYNAMIC
    private function fold(Node $node, callable $constantValue): ?string
    {
        return match (true) {
            $node instanceof Node\Scalar\String_, $node instanceof Node\InterpolatedStringPart => $node->value,
            $node instanceof Node\Scalar\InterpolatedString => implode('', array_map(fn(Node $part): string => $this->fold($part, $constantValue) ?? SqlStatements::DYNAMIC, $node->parts)),
            $node instanceof Node\Expr\BinaryOp\Concat => ($this->fold($node->left, $constantValue) ?? SqlStatements::DYNAMIC) . ($this->fold($node->right, $constantValue) ?? SqlStatements::DYNAMIC),
            $node instanceof Node\Expr\ClassConstFetch, $node instanceof Node\Expr\ConstFetch => $constantValue($node),
            default => null,
        };
    }
}
