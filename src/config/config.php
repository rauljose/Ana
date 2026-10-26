<?php

// Connection used by every page, API and the PHPStan rule: the one place with the credentials.
// create_db.php defines ANA_CREATE_DATABASE first, so the database is created when it does not exist yet.
$dbHost = '127.0.0.1';
$dbName = 'ana';
$dbUser = 'root';
$dbPass = '986532';

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass);
    if (defined('ANA_CREATE_DATABASE')) {
        $mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    }
    $mysqli->select_db($dbName);
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
    return $mysqli;
} catch (mysqli_sql_exception $e) {
    die("Database connection failed" . (defined('ANA_CREATE_DATABASE') ? ': ' . $e->getMessage() : ''));
}
