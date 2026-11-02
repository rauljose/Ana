<?php declare(strict_types=1);

// Creates the tables of db_schema.sql that the database does not have yet, so a new install, or a table added by an
// update, needs no manual step. Nothing that exists is touched: the DROP statements of the file are never run and
// the data stays. A table that was changed (a new column) is not altered: use create_db.php to start over.
// Called by config/config.php, so every page, API and the PHPStan rule check it.

// The statements of the schema file, comments removed
function anaSchemaStatements(string $schema): array
{
    $schema = preg_replace('/^\s*--.*$/m', '', $schema);
    return array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\n|$)/', $schema))));
}

// Returns the tables it created
function anaEnsureSchema(mysqli $mysqli): array
{
    static $checked = false;
    if ($checked) return [];
    $checked = true;

    $statements = anaSchemaStatements(file_get_contents(__DIR__ . '/db_schema.sql'));
    $existing = array_map('strtolower', array_column($mysqli->query("SHOW TABLES")->fetch_all(MYSQLI_NUM), 0));

    $created = [];
    $createPattern = '/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i';
    foreach ($statements as $statement) {
        if (!preg_match($createPattern, $statement, $match)) continue;
        $table = strtolower($match[1]);
        if (in_array($table, $existing, true)) continue;
        $mysqli->query(preg_replace('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?/i', 'CREATE TABLE IF NOT EXISTS ', $statement));
        $created[] = $table;
    }

    // The default layouts go in only with a new user_layouts table: a layout the user deleted stays deleted
    if (in_array('user_layouts', $created, true)) {
        foreach ($statements as $statement) {
            if (preg_match('/^INSERT\s+IGNORE\s+INTO\s+user_layouts/i', $statement)) $mysqli->query($statement);
        }
    }
    return $created;
}
