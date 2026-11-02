<?php declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;

/**
 * The Firebase data a method reads and writes through kreait/firebase-php: the Realtime Database (references),
 * Firestore (collections and documents) and Cloud Storage (objects), by path.
 * Reads the call chains of the method body (getReference('a')->getChild('b')->set(...)), the variables they were kept in
 * and the typed parameters. A part of a path only known at run time is {?}. Where the data is used is not followed:
 * it counts for the method that makes the call. One instance per method.
 *
 * Mode, what the call does to the path:
 *   OVERWRITE replaces the path and everything under it       CHILDREN changes only the children it names
 *   APPEND    adds a new child with a generated key           REMOVE deletes the path and everything under it
 *   ALL       reads the path and everything under it          QUERY reads some of the children
 *   KEYS      reads only the names of the children            METADATA reads information about it, not its content
 */
final class FirebaseUsageFinder
{
    private const DYN = SqlStatements::DYNAMIC;

    // Parameter types of a Firebase object whose path is not known: [service, kind]
    private const PARAMETER_TYPES = [
        'Reference' => ['REALTIME', 'reference'],
        'DocumentReference' => ['FIRESTORE', 'document'],
        'CollectionReference' => ['FIRESTORE', 'collection'],
        'Bucket' => ['STORAGE', 'bucket'],
        'StorageObject' => ['STORAGE', 'object'],
    ];
    private const REALTIME_QUERY = ['orderbychild', 'orderbykey', 'orderbyvalue', 'limittofirst', 'limittolast', 'startat', 'startafter', 'endat', 'endbefore', 'equalto', 'shallow'];
    private const FIRESTORE_QUERY = ['where', 'orderby', 'limit', 'limittolast', 'offset', 'select', 'startat', 'startafter', 'endat', 'endbefore'];
    private const OBJECT_READS = ['downloadasstring' => 'DOWNLOAD', 'downloadtofile' => 'DOWNLOAD', 'downloadasstream' => 'DOWNLOAD', 'downloadasstreamasync' => 'DOWNLOAD', 'signedurl' => 'SIGNED_URL', 'signeduploadurl' => 'SIGNED_URL'];

    /** @var callable(Node): ?string */
    private $constantValue;
    /** @var array<string, array> what a variable or $this property holds */
    private array $variables = [];
    private array $found = [];
    private Standard $printer;

    /**
     * @param array<Node> $statements the body of a method or function
     * @param callable(Node): ?string $constantValue the string a constant fetch holds, or null when it is not a known string
     * @param array<Node\Param> $parameters
     * @return list<array{service: string, object_type: string, path: string, operation: string, access: string, mode: string, is_dynamic: int, snippet: string}>
     */
    public function find(array $statements, callable $constantValue, array $parameters = []): array
    {
        $this->constantValue = $constantValue;
        $this->printer = new Standard();
        $this->variables = [];
        $this->found = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->type instanceof Node\NullableType ? $parameter->type->type : $parameter->type;
            $key = $this->keyOf($parameter->var);
            $kind = $type instanceof Node\Name ? (self::PARAMETER_TYPES[$type->getLast()] ?? null) : null;
            if ($key !== null && $kind !== null) $this->variables[$key] = $this->target($kind[0], $kind[1], self::DYN);
        }

        $visitor = new class(fn(Node $node) => $this->visit($node)) extends NodeVisitorAbstract {
            public function __construct(private readonly \Closure $visit) {}

            public function enterNode(Node $node): ?int
            {
                ($this->visit)($node);
                return null;
            }
        };
        (new NodeTraverser($visitor))->traverse($statements);
        return array_values($this->found);
    }

    private function visit(Node $node): void
    {
        if ($node instanceof Node\Expr\Assign) {
            $key = $this->keyOf($node->var);
            if ($key === null) return;
            $target = $this->describe($node->expr);
            if ($target === null) unset($this->variables[$key]);
            else $this->variables[$key] = $target;
            return;
        }
        if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall) $this->operation($node);
    }

    private function keyOf(Node $node): ?string
    {
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) return '$' . $node->name;
        if ($node instanceof Node\Expr\PropertyFetch && $node->var instanceof Node\Expr\Variable && $node->var->name === 'this' && $node->name instanceof Node\Identifier) {
            return 'this->' . $node->name->toString();
        }
        return null;
    }

    private function target(string $service, string $kind, string $path, bool $isQuery = false, bool $isSure = true, bool $isShallow = false): array
    {
        return ['service' => $service, 'kind' => $kind, 'path' => $path, 'query' => $isQuery, 'sure' => $isSure, 'shallow' => $isShallow];
    }

    // ---------- Paths ----------

    private function clean(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path), '/');
    }

    private function pathOf(?Node $node): string
    {
        return $node === null ? '' : $this->clean(StringFolder::fold($node, $this->constantValue) ?? self::DYN);
    }

    private function join(string $base, string $child): string
    {
        return $this->clean($base . '/' . $child);
    }

    private function isNull(?Node $node): bool
    {
        return $node instanceof Node\Expr\ConstFetch && strtolower($node->name->toString()) === 'null';
    }

    // The value of a key of an array written in place: its text, DYN when it is not a known string, null when the key is not there
    private function option(?Node $array, string $key): ?string
    {
        if (!$array instanceof Node\Expr\Array_) return null;
        foreach ($array->items as $item) {
            if ($item?->key instanceof Node\Scalar\String_ && $item->key->value === $key) {
                return StringFolder::fold($item->value, $this->constantValue) ?? self::DYN;
            }
        }
        return null;
    }

    private function isMerge(?Node $options): bool
    {
        if (!$options instanceof Node\Expr\Array_) return false;
        foreach ($options->items as $item) {
            if (!$item?->key instanceof Node\Scalar\String_) continue;
            if ($item->key->value === 'mergeFields') return true;
            if ($item->key->value === 'merge' && $item->value instanceof Node\Expr\ConstFetch && strtolower($item->value->name->toString()) === 'true') return true;
        }
        return false;
    }

    // $bucket, $this->bucket, $this->storageBucket: named as a bucket
    private function isBucketNamed(Node $node): bool
    {
        $name = $node instanceof Node\Expr\Variable && is_string($node->name) ? $node->name
            : ($node instanceof Node\Expr\PropertyFetch && $node->name instanceof Node\Identifier ? $node->name->toString() : '');
        return preg_match('/bucket/i', $name) === 1;
    }

    // ---------- What an expression holds: a Firebase reference, collection, document, bucket or object ----------

    private function describe(Node $node): ?array
    {
        $key = $this->keyOf($node);
        if ($key !== null) return $this->variables[$key] ?? null;
        if (!$node instanceof Node\Expr\MethodCall && !$node instanceof Node\Expr\NullsafeMethodCall) return null;
        if (!$node->name instanceof Node\Identifier || $node->isFirstClassCallable()) return null;

        $name = strtolower($node->name->toString());
        $arguments = $node->getArgs();
        $first = ($arguments[0] ?? null)?->value;
        $receiver = $this->describe($node->var);
        $service = $receiver['service'] ?? null;

        if ($name === 'getreference') {
            return count($arguments) <= 1 ? $this->target('REALTIME', 'reference', $this->pathOf($first)) : null;
        }
        if ($service === 'REALTIME') {
            switch (true) {
                case $name === 'getchild' && count($arguments) === 1:
                    return $this->target('REALTIME', 'reference', $this->join($receiver['path'], $this->pathOf($first)));
                case $name === 'getparent':
                    $position = strrpos($receiver['path'], '/');
                    return $this->target('REALTIME', 'reference', $position === false ? '' : substr($receiver['path'], 0, $position));
                case $name === 'getroot':
                    return $this->target('REALTIME', 'reference', '');
                case $name === 'push' && $receiver['kind'] === 'reference':
                    return $this->target('REALTIME', 'pushed', $receiver['path']);
                case in_array($name, self::REALTIME_QUERY, true):
                    return $this->target('REALTIME', 'query', $receiver['path'], true, true, $name === 'shallow' || $receiver['shallow']);
            }
            return null;
        }

        if ($name === 'collection' && count($arguments) === 1) {
            $base = $service === 'FIRESTORE' && $receiver['kind'] === 'document' ? $receiver['path'] : '';
            return $this->target('FIRESTORE', 'collection', $this->join($base, $this->pathOf($first)));
        }
        if ($name === 'document' && count($arguments) === 1) {
            $base = $service === 'FIRESTORE' && $receiver['kind'] === 'collection' ? $receiver['path'] : '';
            return $this->target('FIRESTORE', 'document', $this->join($base, $this->pathOf($first)));
        }
        if ($name === 'collectiongroup' && count($arguments) === 1) {
            return $this->target('FIRESTORE', 'group', $this->join('**', $this->pathOf($first)));
        }
        if ($service === 'FIRESTORE' && in_array($name, self::FIRESTORE_QUERY, true) && $receiver['kind'] !== 'document') {
            return $this->target('FIRESTORE', 'query', $receiver['path'], true);
        }

        if ($name === 'getbucket' && count($arguments) <= 1) return $this->target('STORAGE', 'bucket', '');
        if ($name === 'object' && count($arguments) >= 1) {
            $isSure = $service === 'STORAGE' || $this->isBucketNamed($node->var);
            return $this->target('STORAGE', 'object', $this->pathOf($first), false, $isSure);
        }
        return null;
    }

    // ---------- What a call does to it ----------

    private function operation(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $call): void
    {
        if (!$call->name instanceof Node\Identifier || $call->isFirstClassCallable()) return;
        $name = strtolower($call->name->toString());
        $arguments = $call->getArgs();
        $first = ($arguments[0] ?? null)?->value;
        $receiver = $this->describe($call->var);

        // A transaction, a bulk writer or a batch takes the reference as its first argument
        if ($receiver === null && $first !== null && in_array($name, ['set', 'update', 'create', 'delete', 'remove', 'snapshot'], true)) {
            $subject = $this->describe($first);
            if ($subject !== null && in_array($subject['kind'], ['reference', 'document'], true)) {
                $this->apply($call, $subject, $name, $arguments[1]->value ?? null, $arguments[2]->value ?? null);
            }
            return;
        }

        if ($receiver === null && $this->isBucketNamed($call->var) && in_array($name, ['upload', 'objects'], true)) {
            $receiver = $this->target('STORAGE', 'bucket', '');
        }
        if ($receiver === null && $name === 'upload' && $this->option($arguments[1]->value ?? null, 'name') !== null) {
            $receiver = $this->target('STORAGE', 'bucket', '');
        }
        if ($receiver !== null) $this->apply($call, $receiver, $name, $first, $arguments[1]->value ?? null);
    }

    // $argument is the first argument of the call, $second the one after it (the options of a Firestore set())
    private function apply(Node $call, array $subject, string $name, ?Node $argument, ?Node $second): void
    {
        $path = $subject['path'];
        $kind = $subject['kind'];
        $record = fn(string $service, string $type, string $at, string $operation, string $mode) => $this->record($call, $service, $type, $at, $operation, $mode);

        switch ($subject['service']) {
            case 'REALTIME':
                $mode = $subject['shallow'] ? 'KEYS' : ($subject['query'] ? 'QUERY' : 'ALL');
                if ($name === 'getvalue' || $name === 'getsnapshot') $record('REALTIME', 'path', $path, 'GET', $mode);
                elseif ($name === 'getchildkeys') $record('REALTIME', 'path', $path, 'GET_KEYS', 'KEYS');
                elseif ($kind === 'query') return;
                elseif ($name === 'push' && $kind === 'reference' && $argument !== null) $record('REALTIME', 'path', $path, 'PUSH', 'APPEND');
                elseif ($name === 'set') $record('REALTIME', 'path', $path, 'SET', $kind === 'pushed' ? 'APPEND' : ($this->isNull($argument) ? 'REMOVE' : 'OVERWRITE'));
                elseif ($name === 'update') $record('REALTIME', 'path', $path, 'UPDATE', $kind === 'pushed' ? 'APPEND' : 'CHILDREN');
                elseif ($name === 'remove') $record('REALTIME', 'path', $path, 'REMOVE', 'REMOVE');
                elseif ($name === 'removechildren') $record('REALTIME', 'path', $path, 'REMOVE_CHILDREN', 'CHILDREN');
                elseif ($name === 'snapshot') $record('REALTIME', 'path', $path, 'GET', 'ALL');
                return;

            case 'FIRESTORE':
                if ($kind === 'document') {
                    if ($name === 'snapshot') $record('FIRESTORE', 'document', $path, 'GET', 'ALL');
                    elseif ($name === 'set') $record('FIRESTORE', 'document', $path, 'SET', $this->isMerge($second) ? 'CHILDREN' : 'OVERWRITE');
                    elseif ($name === 'create') $record('FIRESTORE', 'document', $path, 'CREATE', 'APPEND');
                    elseif ($name === 'update') $record('FIRESTORE', 'document', $path, 'UPDATE', 'CHILDREN');
                    elseif ($name === 'delete') $record('FIRESTORE', 'document', $path, 'DELETE', 'REMOVE');
                    return;
                }
                // set() on a document given as an argument has the options second; a document as receiver has them first
                if ($name === 'add' && $kind === 'collection') $record('FIRESTORE', 'collection', $path, 'ADD', 'APPEND');
                elseif ($name === 'documents') $record('FIRESTORE', 'collection', $path, 'GET', $kind === 'collection' ? 'ALL' : 'QUERY');
                elseif ($name === 'listdocuments') $record('FIRESTORE', 'collection', $path, 'LIST', 'KEYS');
                elseif (in_array($name, ['count', 'sum', 'avg'], true)) $record('FIRESTORE', 'collection', $path, 'AGGREGATE', 'QUERY');
                return;

            case 'STORAGE':
                if ($kind === 'bucket') {
                    if ($name === 'upload') $record('STORAGE', 'object', $this->clean($this->option($second, 'name') ?? self::DYN), 'UPLOAD', 'OVERWRITE');
                    elseif ($name === 'objects') $record('STORAGE', 'folder', $this->clean($this->option($argument, 'prefix') ?? ''), 'LIST', 'KEYS');
                    return;
                }
                if (isset(self::OBJECT_READS[$name])) {
                    $record('STORAGE', 'object', $path, self::OBJECT_READS[$name], 'ALL');
                    return;
                }
                // The names below are also used by other objects: only when it is surely a Storage object
                if (!$subject['sure']) return;
                if (in_array($name, ['info', 'exists', 'reload'], true)) $record('STORAGE', 'object', $path, strtoupper($name), 'METADATA');
                elseif ($name === 'delete') $record('STORAGE', 'object', $path, 'DELETE', 'REMOVE');
                elseif ($name === 'update') $record('STORAGE', 'object', $path, 'UPDATE', 'CHILDREN');
                elseif ($name === 'rename') {
                    $record('STORAGE', 'object', $path, 'RENAME', 'REMOVE');
                    $record('STORAGE', 'object', $this->pathOf($argument), 'RENAME', 'OVERWRITE');
                } elseif (in_array($name, ['copy', 'rewrite', 'move'], true)) {
                    $record('STORAGE', 'object', $path, strtoupper($name), 'ALL');
                    $record('STORAGE', 'object', $this->clean($this->option($second, 'name') ?? self::DYN), strtoupper($name), 'OVERWRITE');
                }
                return;
        }
    }

    private function record(Node $call, string $service, string $type, string $path, string $operation, string $mode): void
    {
        $access = in_array($mode, ['OVERWRITE', 'CHILDREN', 'APPEND', 'REMOVE'], true) ? 'WRITE' : 'READ';
        $shown = str_replace(self::DYN, '{?}', $path === '' ? '/' : $path);
        $key = $service . '|' . $type . '|' . $shown . '|' . $operation . '|' . $mode;
        if (isset($this->found[$key])) return;
        $snippet = trim(preg_replace('/\s+/', ' ', str_replace(self::DYN, '{?}', $this->printer->prettyPrintExpr($call))));
        $this->found[$key] = [
            'service' => $service,
            'object_type' => $type,
            'path' => $shown,
            'operation' => $operation,
            'access' => $access,
            'mode' => $mode,
            'is_dynamic' => str_contains($path, self::DYN) ? 1 : 0,
            'snippet' => mb_substr($snippet, 0, 255),
        ];
    }
}
