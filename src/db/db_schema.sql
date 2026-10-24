DROP TABLE IF EXISTS project;
CREATE TABLE IF NOT EXISTS project (
    name VARCHAR(255) NOT NULL,
    analyzed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS classes;
CREATE TABLE IF NOT EXISTS classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL UNIQUE,
    is_abstract TINYINT(1) DEFAULT 0,
    is_final TINYINT(1) DEFAULT 0,
    is_readonly TINYINT(1) DEFAULT 0,
    is_static TINYINT(1) DEFAULT 0,
    is_interface TINYINT(1) NOT NULL DEFAULT 0,
    is_trait TINYINT(1) NOT NULL DEFAULT 0,
    doc_comment TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS class_relations;
CREATE TABLE IF NOT EXISTS class_relations (
    source_class VARCHAR(255) NOT NULL ,
    target_class VARCHAR(255) NOT NULL,
    relation_type ENUM('EXTENDS', 'IMPLEMENTS', 'USES', 'TYPE_HINT') NOT NULL,
    UNIQUE KEY idx_relation (source_class, target_class, relation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS methods;
CREATE TABLE methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    method_name VARCHAR(255) NOT NULL,
    visibility ENUM('public', 'private', 'protected') NOT NULL,
    is_static TINYINT(1) NOT NULL DEFAULT 0,
    is_builtin TINYINT(1) NOT NULL DEFAULT 0,
    cyclo_complexity MEDIUMINT UNSIGNED,
    cognitive_complexity MEDIUMINT UNSIGNED,
    is_pure TINYINT(1),
    num_parameters INT NOT NULL DEFAULT 0,
    num_default_parameters INT NOT NULL DEFAULT 0,
    num_reference_parameters INT NOT NULL DEFAULT 0,
    num_object_parameters INT NOT NULL DEFAULT 0,
    has_sensitive_parameter TINYINT(1) NOT NULL DEFAULT 0,
    return_type VARCHAR(255) NOT NULL DEFAULT 'void',
    is_ends TINYINT(1) NOT NULL DEFAULT 0,
    signature TEXT,
    doc_comment TEXT,
    UNIQUE KEY idx_class_method (class_name, method_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS calls;
CREATE TABLE calls (
    from_class VARCHAR(255) NULL,
    from_method VARCHAR(255) NULL,
    to_class VARCHAR(255) NULL,
    to_method VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS method_globals;
CREATE TABLE IF NOT EXISTS method_globals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    method_name VARCHAR(255) NOT NULL,
    global_type ENUM('SUPERGLOBAL', 'GLOBAL_KEYWORD', 'CONSTANT') NOT NULL,
    global_name VARCHAR(255) NOT NULL,
    access ENUM('READ', 'WRITE', 'READ_WRITE') NOT NULL DEFAULT 'READ',
    UNIQUE KEY idx_method_global (class_name, method_name, global_type, global_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS properties;
CREATE TABLE IF NOT EXISTS properties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    property_name VARCHAR(255) NOT NULL,
    visibility ENUM('public', 'private', 'protected') NOT NULL,
    is_static TINYINT(1) NOT NULL DEFAULT 0,
    is_readonly TINYINT(1) NOT NULL DEFAULT 0,
    is_constant TINYINT(1) NOT NULL DEFAULT 0,
    type_hint VARCHAR(255),
    doc_comment TEXT,
    UNIQUE KEY idx_class_prop (class_name, property_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS method_property_access;
CREATE TABLE IF NOT EXISTS method_property_access (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    method_name VARCHAR(255) NOT NULL,
    property_name VARCHAR(255) NOT NULL,
    access_type ENUM('READ', 'WRITE', 'READ_WRITE') NOT NULL,
    UNIQUE KEY idx_method_prop_access (class_name, method_name, property_name, access_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
