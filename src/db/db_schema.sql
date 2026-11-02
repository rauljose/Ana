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

-- The SQL each method or function holds: what it reads, changes, calls or defines, by table or procedure
DROP TABLE IF EXISTS method_sql;
CREATE TABLE IF NOT EXISTS method_sql (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    method_name VARCHAR(255) NOT NULL,
    operation VARCHAR(20) NOT NULL,
    object_type VARCHAR(12) NOT NULL,
    object_name VARCHAR(255) NOT NULL,
    access ENUM('READ', 'WRITE', 'CALL', 'DDL') NOT NULL,
    is_dynamic TINYINT(1) NOT NULL DEFAULT 0,
    source ENUM('SQL', 'HELPER') NOT NULL DEFAULT 'SQL',
    snippet VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_method_sql_method (class_name, method_name),
    KEY idx_method_sql_object (object_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- User settings live apart from the analysis data: no DROP, so re-running this file keeps the layouts
CREATE TABLE IF NOT EXISTS user_layouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page VARCHAR(64) NOT NULL,
    name VARCHAR(100) NOT NULL,
    hidden_columns JSON NOT NULL,
    sort_rules JSON NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_page_name (page, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Per page settings (thresholds, pickers). Same as user_layouts: no DROP, not part of a project's analysis
CREATE TABLE IF NOT EXISTS user_settings (
    page VARCHAR(64) NOT NULL,
    name VARCHAR(64) NOT NULL,
    value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (page, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- default layouts
INSERT IGNORE INTO user_layouts (page, name, hidden_columns, sort_rules, is_default) VALUES
                                                                                         ('class_metrics', 'Complexity review', '["class_name","is_static_class","is_readonly_class","doc_comment","extends","implements","uses_traits","children_count","public_methods","protected_methods","private_methods","pure_methods_pct","constructor_params","total_reference_params","total_object_params","methods_ending_script","methods_with_sensitive_params","afferent_ca","efferent_ce","instability","mpc","dac","abstractness","main_seq_distance","classes_used","classes_using_this","mutable_properties","readonly_properties","properties_actually_written","uses_superglobals","uses_global_keyword","uses_constants","is_data_class","is_stateless_service","is_unused_candidate"]', '[{"field":"hotspot_score","dir":"desc"}]', 0),
                                                                                         ('class_metrics', 'Coupling and architecture', '["class_name","is_static_class","is_readonly_class","doc_comment","uses_traits","total_methods","public_methods","protected_methods","private_methods","pure_methods_pct","total_cyclo","avg_cyclo","max_cyclo_method","max_cyclo_val","total_cog","avg_cog","max_cog_method","max_cog_val","hotspot_score","constructor_params","total_reference_params","total_object_params","methods_ending_script","methods_with_sensitive_params","lcom","mutable_properties","readonly_properties","properties_actually_written","uses_superglobals","uses_global_keyword","uses_constants","is_god_class","is_data_class","is_stateless_service","is_unused_candidate"]', '[{"field":"efferent_ce","dir":"desc"}]', 0),
                                                                                         ('class_metrics', 'State and smells', '["class_name","is_static_class","is_readonly_class","doc_comment","extends","implements","uses_traits","children_count","total_methods","public_methods","protected_methods","private_methods","total_cyclo","avg_cyclo","max_cyclo_method","max_cyclo_val","total_cog","avg_cog","max_cog_method","max_cog_val","hotspot_score","constructor_params","total_reference_params","total_object_params","methods_ending_script","methods_with_sensitive_params","afferent_ca","efferent_ce","instability","mpc","dac","abstractness","main_seq_distance","classes_used","classes_using_this"]', '[{"field":"lcom","dir":"desc"}]', 0);

INSERT IGNORE INTO user_layouts (page, name, hidden_columns, sort_rules, is_default) VALUES
                                                                                         ('method_metrics', 'Refactoring candidates', '["namespace","class_name","fqn","is_static","is_builtin","is_pure","is_ends","num_default_parameters","num_reference_parameters","num_object_parameters","has_sensitive_parameter","return_type","signature","doc_comment","props_read","props_written","uses_globals","props_read_list","props_written_list","globals_list","calls_out_native","calls_out_list","calls_in_list","is_unused_candidate"]', '[{"field":"hotspot_score","dir":"desc"}]', 0),
                                                                                         ('method_metrics', 'Signatures and API', '["namespace","class_name","fqn","kind","is_builtin","cyclo_complexity","cognitive_complexity","hotspot_score","is_pure","is_ends","has_sensitive_parameter","props_read","props_written","uses_globals","props_read_list","props_written_list","globals_list","calls_out_total","calls_out_native","calls_in_total","calls_out_list","calls_in_list","is_unused_candidate"]', '[{"field":"num_parameters","dir":"desc"}]', 0),
                                                                                         ('method_metrics', 'State and side effects', '["namespace","class_name","fqn","kind","is_static","is_builtin","cyclo_complexity","cognitive_complexity","hotspot_score","num_parameters","num_default_parameters","num_reference_parameters","num_object_parameters","has_sensitive_parameter","return_type","signature","doc_comment","calls_out_total","calls_out_native","calls_in_total","calls_out_list","calls_in_list","is_unused_candidate"]', '[{"field":"uses_globals","dir":"desc"},{"field":"props_written","dir":"desc"}]', 0);

INSERT IGNORE INTO user_layouts (page, name, hidden_columns, sort_rules, is_default) VALUES
                                                                                         ('namespace_metrics', 'Size and complexity', '["total_cog","afferent_ca","efferent_ce","instability","abstractness","main_seq_distance","calls_out","calls_in","calls_internal","internal_call_ratio","in_cycle","namespaces_used","namespaces_using_this"]', '[{"field":"total_cyclo","dir":"desc"}]', 0),
                                                                                         ('namespace_metrics', 'Dependencies', '["classes","abstract_classes","interfaces","traits","total_types","methods","total_cyclo","avg_cyclo","max_cyclo","total_cog","pure_methods_pct","hotspot_score","instability","abstractness","main_seq_distance","calls_internal","internal_call_ratio"]', '[{"field":"efferent_ce","dir":"desc"}]', 0),
                                                                                         ('namespace_metrics', 'Architecture health', '["classes","abstract_classes","interfaces","traits","total_types","methods","total_cyclo","avg_cyclo","max_cyclo","total_cog","pure_methods_pct","hotspot_score","afferent_ca","efferent_ce","calls_in","namespaces_used","namespaces_using_this"]', '[{"field":"main_seq_distance","dir":"desc"}]', 0);

INSERT IGNORE INTO user_layouts (page, name, hidden_columns, sort_rules, is_default) VALUES
                                                                                         ('global_report', 'Who writes shared state', '["is_native","namespace","class_name","method_fqn","kind"]', '[{"field":"symbol_fqn","dir":"asc"}]', 0),
                                                                                         ('global_report', 'Hidden inputs', '["symbol_fqn","access","namespace","class_short_name","class_name","method_short_name"]', '[{"field":"symbol_type","dir":"asc"},{"field":"symbol","dir":"asc"}]', 0),
                                                                                         ('global_report', 'Full detail', '[]', '[{"field":"namespace","dir":"asc"},{"field":"class_name","dir":"asc"}]', 0);

INSERT IGNORE INTO user_layouts (page, name, hidden_columns, sort_rules, is_default) VALUES
                                                                                         ('method_globals', 'Method overview', '["namespace","class_name","method_fqn","kind"]', '[{"field":"method_short_name","dir":"asc"}]', 0),
                                                                                         ('method_globals', 'Writes and project globals', '["method_short_name","namespace","class_short_name","class_name","kind"]', '[{"field":"access","dir":"desc"},{"field":"is_native","dir":"asc"}]', 0),
                                                                                         ('method_globals', 'Full trace', '["method_short_name","class_short_name","class_name"]', '[{"field":"method_fqn","dir":"asc"}]', 0);

