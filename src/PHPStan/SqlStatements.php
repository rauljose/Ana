<?php declare(strict_types=1);

namespace App\PHPStan;

/**
 * What a piece of SQL text does: which tables it reads, which it changes, which procedures it calls
 * and which objects it creates, alters or drops. Reads the text, it does not run or fully parse it:
 * the statements must be written the usual MySQL way and a table named by a variable shows as '?'.
 */
final class SqlStatements
{
    /** Stands for a part of the text only known at run time, such as a variable in a concatenation */
    public const DYNAMIC = "\x01";

    private const IDENTIFIER = '(?:`[^`]+`|[\w$\x01]+)(?:\.(?:`[^`]+`|[\w$\x01]+))?';
    private const OBJECT_TYPES = 'TABLE|VIEW|PROCEDURE|FUNCTION|TRIGGER|DATABASE|SCHEMA|EVENT';

    // Words that can follow a table name and are not an alias
    private const NOT_ALIAS = [
        'where', 'join', 'left', 'right', 'inner', 'cross', 'natural', 'straight_join', 'on', 'using', 'set', 'group', 'order',
        'limit', 'having', 'union', 'for', 'lock', 'window', 'partition', 'use', 'force', 'ignore', 'values', 'value', 'select',
        'outer', 'full', 'into', 'procedure', 'intersect', 'except', 'fetch', 'offset',
    ];

    // Not tables: DUAL, and the words that start English sentences such as "Select an option from the menu"
    private const NOT_A_TABLE = ['dual', 'select', 'lateral', 'the', 'a', 'an', 'your', 'this', 'that', 'these', 'those', 'my', 'our', 'their', 'its', 'all', 'any', 'each', 'one', 'list'];

    /**
     * @return list<array{operation: string, object_type: string, object_name: string, access: string, is_dynamic: int}>
     *   access is READ, WRITE, CALL or DDL
     */
    public static function parse(string $text): array
    {
        $found = [];
        foreach (self::split($text) as $statement) {
            foreach (self::statement($statement) as $usage) {
                $found[$usage['operation'] . '|' . $usage['object_type'] . '|' . $usage['object_name'] . '|' . $usage['access']] = $usage;
            }
        }
        return array_values($found);
    }

    /** @return list<string> the statements of the text, comments removed, one line each */
    private static function split(string $text): array
    {
        $text = preg_replace('~/\*.*?\*/~s', ' ', $text);
        $text = preg_replace('~(?:^|\s)(?:--\s|#)[^\n]*~', ' ', $text);
        $statements = [];
        foreach (explode(';', $text) as $statement) {
            $statement = trim(preg_replace('/\s+/', ' ', $statement));
            if ($statement !== '') $statements[] = $statement;
        }
        return $statements;
    }

    /** @return list<array{operation: string, object_type: string, object_name: string, access: string, is_dynamic: int}> */
    private static function statement(string $statement): array
    {
        $statement = ltrim($statement, '( ');
        if (!preg_match('/^(WITH|SELECT|INSERT|REPLACE|UPDATE|DELETE|TRUNCATE|CALL|CREATE|ALTER|DROP|RENAME)\b/i', $statement, $verb)) return [];

        return match (strtoupper($verb[1])) {
            'WITH' => self::withStatement($statement),
            'SELECT' => self::reads(self::tableRefs($statement)),
            'INSERT', 'REPLACE' => self::insert($statement),
            'UPDATE' => self::update($statement),
            'DELETE' => self::delete($statement),
            'TRUNCATE' => self::truncate($statement),
            'CALL' => self::call($statement),
            'RENAME' => self::rename($statement),
            default => self::definition($statement),
        };
    }

    private static function usage(string $operation, string $type, string $name, string $access): array
    {
        $name = str_replace('`', '', $name);
        return [
            'operation' => $operation,
            'object_type' => $type,
            'object_name' => str_replace(self::DYNAMIC, '?', $name),
            'access' => $access,
            'is_dynamic' => str_contains($name, self::DYNAMIC) ? 1 : 0,
        ];
    }

    /** @param list<string> $tables */
    private static function reads(array $tables): array
    {
        return array_map(fn(string $table): array => self::usage('SELECT', 'table', $table, 'READ'), $tables);
    }

    // Tables named after FROM and JOIN, in the order written. Subqueries are found by their own FROM
    private static function tableRefs(string $text): array
    {
        preg_match_all('/\b(FROM|JOIN)\s+/i', $text, $matches, PREG_OFFSET_CAPTURE);
        $tables = [];
        foreach ($matches[1] as $index => [$keyword, $offset]) {
            // EXTRACT(YEAR FROM date) and TRIM(x FROM y) are not table lists
            $before = substr($text, max(0, $offset - 80), min($offset, 80));
            if (preg_match('/\b(?:EXTRACT|TRIM|SUBSTRING|SUBSTR)\s*\([^()]*$/i', $before)) continue;
            $after = substr($text, $matches[0][$index][1] + strlen($matches[0][$index][0]));
            array_push($tables, ...self::tableList($after, strtoupper($keyword) === 'FROM'));
        }
        return $tables;
    }

    // The table names at the start of the text: name [AS] [alias] [, name [alias]]
    private static function tableList(string $text, bool $allowComma): array
    {
        $tables = [];
        while (preg_match('/^\s*(' . self::IDENTIFIER . ')/', $text, $match)) {
            $name = str_replace('`', '', $match[1]);
            if (in_array(strtolower($name), self::NOT_A_TABLE, true)) break;
            $tables[] = $match[1];
            $text = substr($text, strlen($match[0]));

            if (preg_match('/^\s+AS\s+(' . self::IDENTIFIER . ')/i', $text, $alias)) {
                $text = substr($text, strlen($alias[0]));
            } elseif (preg_match('/^\s+(' . self::IDENTIFIER . ')/', $text, $alias) && !in_array(strtolower($alias[1]), self::NOT_ALIAS, true)) {
                $text = substr($text, strlen($alias[0]));
            }
            if (!$allowComma || !preg_match('/^\s*,/', $text)) break;
            $text = preg_replace('/^\s*,/', '', $text);
        }
        return $tables;
    }

    private static function insert(string $statement): array
    {
        $pattern = '/^(INSERT|REPLACE)\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*INTO\s+(' . self::IDENTIFIER . ')\s*(?:\(|SET\b|VALUES?\b|SELECT\b|PARTITION\b|$)/i';
        if (!preg_match($pattern, $statement, $match)) return [];

        // INSERT ... SELECT also reads
        return [
            self::usage(strtoupper($match[1]), 'table', $match[2], 'WRITE'),
            ...self::reads(self::tableRefs(substr($statement, strlen($match[0])))),
        ];
    }

    // The first table is the one changed; further tables of a multi table UPDATE are counted as read
    private static function update(string $statement): array
    {
        if (!preg_match('/^UPDATE\s+(?:(?:LOW_PRIORITY|IGNORE)\s+)*(.+?)\s+SET\b(.*)$/is', $statement, $match)) return [];

        $head = $match[1];
        $tables = self::tableList($head, true);
        if ($tables === []) return [];
        $changed = array_shift($tables);
        preg_match_all('/\bJOIN\s+(.*)$/i', $head, $joins);
        foreach ($joins[1] as $joined) array_push($tables, ...self::tableList($joined, false));

        return [
            self::usage('UPDATE', 'table', $changed, 'WRITE'),
            ...self::reads([...$tables, ...self::tableRefs($match[2])]),
        ];
    }

    // The first table after FROM is the one deleted from; DELETE a FROM a JOIN b is the same
    private static function delete(string $statement): array
    {
        if (!preg_match('/^DELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*(?:[\w`.,$\x01 ]*?\s)?FROM\s/i', $statement)) return [];

        $tables = self::tableRefs($statement);
        if ($tables === []) return [];
        return [self::usage('DELETE', 'table', array_shift($tables), 'WRITE'), ...self::reads($tables)];
    }

    private static function truncate(string $statement): array
    {
        if (!preg_match('/^TRUNCATE\s+(?:TABLE\s+)?(' . self::IDENTIFIER . ')\s*$/i', $statement, $match)) return [];
        return [self::usage('TRUNCATE', 'table', $match[1], 'WRITE')];
    }

    private static function call(string $statement): array
    {
        if (!preg_match('/^CALL\s+(' . self::IDENTIFIER . ')\s*(?:\(|$)/i', $statement, $match)) return [];
        return [self::usage('CALL', 'procedure', $match[1], 'CALL')];
    }

    private static function rename(string $statement): array
    {
        if (!preg_match('/^RENAME\s+TABLE\s+(' . self::IDENTIFIER . ')\s+TO\s+(' . self::IDENTIFIER . ')/i', $statement, $match)) return [];
        return [self::usage('RENAME', 'table', $match[1], 'DDL'), self::usage('RENAME', 'table', $match[2], 'DDL')];
    }

    // CREATE, ALTER and DROP of tables, views, procedures, functions, triggers, events, databases and indexes
    private static function definition(string $statement): array
    {
        $verb = strtoupper(strtok($statement, ' '));
        $types = self::OBJECT_TYPES;

        if ($verb === 'CREATE') {
            $modifiers = '(?:OR\s+REPLACE\s+)?(?:TEMPORARY\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?';
            if (preg_match('/^CREATE\s+(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?INDEX\s+' . self::IDENTIFIER . '\s+(?:USING\s+\w+\s+)?ON\s+(' . self::IDENTIFIER . ')/i', $statement, $match)) {
                return [self::usage('CREATE INDEX', 'table', $match[1], 'DDL')];
            }
            if (!preg_match('/^CREATE\s+' . $modifiers . '(' . $types . ')\s+(?:IF\s+NOT\s+EXISTS\s+)?(' . self::IDENTIFIER . ')/i', $statement, $match)) return [];
            // CREATE TABLE ... SELECT also reads
            $reads = strtoupper($match[1]) === 'TABLE' ? self::reads(self::tableRefs(substr($statement, strlen($match[0])))) : [];
            return [self::usage('CREATE', strtolower($match[1]), $match[2], 'DDL'), ...$reads];
        }

        if ($verb === 'ALTER') {
            if (!preg_match('/^ALTER\s+(?:ONLINE\s+)?(?:IGNORE\s+)?(' . $types . ')\s+(' . self::IDENTIFIER . ')/i', $statement, $match)) return [];
            return [self::usage('ALTER', strtolower($match[1]), $match[2], 'DDL')];
        }

        if (preg_match('/^DROP\s+INDEX\s+' . self::IDENTIFIER . '\s+ON\s+(' . self::IDENTIFIER . ')/i', $statement, $match)) {
            return [self::usage('DROP INDEX', 'table', $match[1], 'DDL')];
        }
        $list = '(' . self::IDENTIFIER . '(?:\s*,\s*' . self::IDENTIFIER . ')*)';
        if (!preg_match('/^DROP\s+(?:TEMPORARY\s+)?(' . $types . ')\s+(?:IF\s+EXISTS\s+)?' . $list . '/i', $statement, $match)) return [];
        return array_map(
            fn(string $name): array => self::usage('DROP', strtolower($match[1]), trim($name), 'DDL'),
            explode(',', $match[2])
        );
    }

    // WITH cte AS (...) SELECT, or WITH cte AS (...) INSERT / UPDATE / DELETE. The names of the CTEs are not tables
    private static function withStatement(string $statement): array
    {
        preg_match_all('/(' . self::IDENTIFIER . ')\s+AS\s*\(/i', $statement, $names);
        $cteNames = array_map('strtolower', $names[1]);
        $notCte = fn(array $tables): array => array_values(array_filter($tables, fn(string $table): bool => !in_array(strtolower($table), $cteNames, true)));

        if (preg_match('/\)\s*(INSERT|REPLACE|UPDATE|DELETE)\b/i', $statement, $main, PREG_OFFSET_CAPTURE)) {
            $offset = $main[1][1];
            $usages = [
                ...self::statement(substr($statement, $offset)),
                ...self::reads($notCte(self::tableRefs(substr($statement, 0, $offset)))),
            ];
            return array_values(array_filter($usages, fn(array $usage): bool => $usage['access'] !== 'READ' || !in_array(strtolower($usage['object_name']), $cteNames, true)));
        }
        return self::reads($notCte(self::tableRefs($statement)));
    }
}
