<?php

// Connection used by every page, API and the PHPStan rule: the one place with the credentials.
// The database and its tables are created when they do not exist yet (db/ensure_schema.php).
$dbHost = '127.0.0.1';
$dbName = 'ana';
$dbUser = 'root';
$dbPass = '986532';

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass);
    try {
        $mysqli->select_db($dbName);
    } catch (mysqli_sql_exception $e) {
        $mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $mysqli->select_db($dbName);
    }
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
    require_once __DIR__ . '/../db/ensure_schema.php';
    anaEnsureSchema($mysqli);
    return $mysqli;
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
