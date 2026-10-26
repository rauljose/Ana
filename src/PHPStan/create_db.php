<?php declare(strict_types=1);

// Creates the database and runs db/db_schema.sql, the single source of truth for the tables,
// then db/default_layouts.sql. Credentials are in config/config.php
define('ANA_CREATE_DATABASE', true);
$mysqli = require __DIR__ . '/../config/config.php';

try {
    $mysqli->multi_query(
        file_get_contents(__DIR__ . '/../db/db_schema.sql') . "\n" .
        file_get_contents(__DIR__ . '/../db/default_layouts.sql')
    );
    while ($mysqli->more_results()) {
        $mysqli->next_result();
    }
} catch (mysqli_sql_exception $e) {
    die("Schema failed!: " . $e->getMessage() . "\n");
}

echo "Database $dbName ready\n";
