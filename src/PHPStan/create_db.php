<?php declare(strict_types=1);

// Creates the database and runs db/db_schema.sql, the single source of truth for the tables
$dbHost = 'localhost';
$dbName = 'ana';
$dbUser = 'root';
$dbPass = '986532';

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass);
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
} catch (mysqli_sql_exception $e) {
    die("Connection failed!: " . $e->getMessage() . "\n");
}

$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
$mysqli->select_db($dbName);

try {
    $mysqli->multi_query(file_get_contents(__DIR__ . '/../db/db_schema.sql'));
    while ($mysqli->more_results()) {
        $mysqli->next_result();
    }
} catch (mysqli_sql_exception $e) {
    die("Schema failed!: " . $e->getMessage() . "\n");
}

echo "Database $dbName ready\n";
