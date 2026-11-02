<?php declare(strict_types=1);

// Starts the analysis tables over: runs db/db_schema.sql, the single source of truth for the tables, which drops and
// creates them again. Not needed to install: config/config.php creates what is missing. Use it after a table changed
// (a new column), or to wipe a previous analysis. Credentials are in config/config.php
$mysqli = require __DIR__ . '/../config/config.php';

try {
    $mysqli->multi_query(file_get_contents(__DIR__ . '/../db/db_schema.sql'));
    while ($mysqli->more_results()) {
        $mysqli->next_result();
    }
} catch (mysqli_sql_exception $e) {
    die("Schema failed!: " . $e->getMessage() . "\n");
}

echo "Database Ana ready\n";
