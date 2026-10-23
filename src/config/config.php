<?php

try {
    $mysqli = new mysqli('127.0.0.1', 'root', '986532', 'ana');
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
    return $mysqli;
} catch (mysqli_sql_exception $e) {
    die("Database connection failed");
}

