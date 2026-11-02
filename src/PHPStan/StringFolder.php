<?php declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;

/**
 * The text of a string expression: a literal, a concatenation, an interpolated string or a constant.
 * A part that is not known (a variable, a call) becomes SqlStatements::DYNAMIC; null when the node is no string at all.
 */
final class StringFolder
{
    /** @param callable(Node): ?string $constantValue the string a constant fetch holds, or null when it is not a known string */
    public static function fold(Node $node, callable $constantValue): ?string
    {
        return match (true) {
            $node instanceof Node\Scalar\String_, $node instanceof Node\InterpolatedStringPart => $node->value,
            $node instanceof Node\Scalar\InterpolatedString => implode('', array_map(fn(Node $part): string => self::fold($part, $constantValue) ?? SqlStatements::DYNAMIC, $node->parts)),
            $node instanceof Node\Expr\BinaryOp\Concat => (self::fold($node->left, $constantValue) ?? SqlStatements::DYNAMIC) . (self::fold($node->right, $constantValue) ?? SqlStatements::DYNAMIC),
            $node instanceof Node\Expr\ClassConstFetch, $node instanceof Node\Expr\ConstFetch => $constantValue($node),
            default => null,
        };
    }
}
