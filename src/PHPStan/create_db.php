<?php declare(strict_types=1);

$dbHost = 'localhost';
$dbName = 'ana';
$dbUser = 'root';$dbPass = '986532';

try {
    $mysqli = new mysqli($dbHost, $dbUser,$dbPass);
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
} catch (mysqli_sql_exception $e) {
    die("Connection failed!: " . $e->getMessage() . "\n");
}

$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
$mysqli->select_db($dbName);

$mysqli->query("
    CREATE TABLE IF NOT EXISTS project (
        name VARCHAR(255) NOT NULL,
        analyzed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

$mysqli->query("
    CREATE TABLE IF NOT EXISTS classes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(255) NOT NULL UNIQUE,
        is_abstract TINYINT(1) DEFAULT 0,
        is_final TINYINT(1) DEFAULT 0,
        is_readonly TINYINT(1) DEFAULT 0,
        is_static TINYINT(1) DEFAULT 0,
        is_interface TINYINT(1) NOT NULL DEFAULT 0,
        is_trait TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

$mysqli->query("
    CREATE TABLE IF NOT EXISTS class_relations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_class VARCHAR(255) NOT NULL,
        target_class VARCHAR(255) NOT NULL,
        relation_type ENUM('EXTENDS', 'IMPLEMENTS', 'USES', 'TYPE_HINT') NOT NULL,
        UNIQUE KEY idx_relation (source_class, target_class, relation_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

$mysqli->query("
    CREATE TABLE IF NOT EXISTS methods (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(255) NOT NULL,
        method_name VARCHAR(255) NOT NULL,
        visibility ENUM('public', 'private', 'protected') NOT NULL,
        is_static TINYINT(1) NOT NULL DEFAULT 0,
        is_builtin TINYINT(1) NOT NULL DEFAULT 0,
        cyclo_complexity INT NOT NULL DEFAULT 1,
        cognitive_complexity INT NOT NULL DEFAULT 0,
        is_pure TINYINT(1) NOT NULL DEFAULT 0,
        num_parameters INT NOT NULL DEFAULT 0,
        num_default_parameters INT NOT NULL DEFAULT 0,
        num_reference_parameters INT NOT NULL DEFAULT 0,
        num_object_parameters INT NOT NULL DEFAULT 0,
        has_sensitive_parameter TINYINT(1) NOT NULL DEFAULT 0,
        return_type VARCHAR(255) NOT NULL DEFAULT 'void',
        is_ends TINYINT(1) NOT NULL DEFAULT 0,
        signature TEXT,
        UNIQUE KEY idx_class_method (class_name, method_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

$mysqli->query("
    CREATE TABLE IF NOT EXISTS calls (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        from_class VARCHAR(255) NOT NULL,
        from_method VARCHAR(255) NOT NULL,
        to_class VARCHAR(255) NOT NULL,
        to_method VARCHAR(255) NOT NULL,
        KEY idx_from (from_class, from_method),
        KEY idx_to (to_class, to_method)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

$mysqli->query("
CREATE TABLE IF NOT EXISTS method_globals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    method_name VARCHAR(255) NOT NULL,
    global_type ENUM('SUPERGLOBAL', 'GLOBAL_KEYWORD', 'CONSTANT') NOT NULL,
    global_name VARCHAR(255) NOT NULL,
    UNIQUE KEY idx_method_global (class_name, method_name, global_type, global_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
