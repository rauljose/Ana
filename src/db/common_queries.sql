-- most called methods, hotspots
WITH MethodCounts AS (
    SELECT
        to_class,
        to_method,
        COUNT(*) AS total_calls
    FROM calls
    GROUP BY to_class, to_method
),
     RankedMethods AS (
         SELECT
             m.class_name,
             m.visibility,
             m.method_name,
             COALESCE(mc.total_calls, 0) AS total_calls,
             RANK() OVER (PARTITION BY m.class_name, m.visibility ORDER BY COALESCE(mc.total_calls, 0) DESC) as call_rank
         FROM methods m
                  LEFT JOIN MethodCounts mc
                            ON m.class_name = mc.to_class
                                AND m.method_name = mc.to_method
     )
SELECT
    class_name,
    visibility,
    method_name,
    total_calls
FROM RankedMethods
WHERE call_rank = 1
ORDER BY class_name, visibility;

-- class to class calls
SELECT
    from_class AS calling_class,
    to_class AS called_class,
    COUNT(*) AS total_interactions
FROM calls
WHERE from_class != to_class
GROUP BY from_class, to_class
ORDER BY from_class, total_interactions DESC;

-- class calls to method called
SELECT
    from_class AS calling_class,
    to_class AS called_class,
    to_method AS called_method,
    COUNT(*) AS total_calls
FROM calls
WHERE from_class != to_class
GROUP BY from_class, to_class, to_method
ORDER BY from_class, to_class, total_calls DESC;

-- class summary
WITH ClassMetrics AS (
    SELECT
        class_name,
        SUM(cyclo_complexity) AS total_cyclo,
        SUM(cognitive_complexity) AS total_cognitive
    FROM methods
    GROUP BY class_name
),
     EfferentData AS (
         SELECT
             from_class AS class_name,
             COUNT(DISTINCT to_class) AS efferent_coupling,
             GROUP_CONCAT(DISTINCT to_class ORDER BY to_class SEPARATOR ', ') AS called_classes
         FROM calls
         WHERE from_class != to_class
         GROUP BY from_class
     ),
     AfferentData AS (
         SELECT
             to_class AS class_name,
             COUNT(DISTINCT from_class) AS afferent_coupling
         FROM calls
         WHERE from_class != to_class
         GROUP BY to_class
     )
SELECT
    m.class_name,
    COALESCE(a.afferent_coupling, 0) AS afferent_coupling,
    COALESCE(e.efferent_coupling, 0) AS efferent_coupling,
    m.total_cyclo AS cyclo_complexity,
    m.total_cognitive AS cognitive_complexity,
    COALESCE(e.called_classes, '') AS called_classes
FROM ClassMetrics m
         LEFT JOIN AfferentData a ON m.class_name = a.class_name
         LEFT JOIN EfferentData e ON m.class_name = e.class_name
ORDER BY m.class_name;

-- method summary
WITH EfferentData AS (
    SELECT
        from_class AS class_name,
        from_method AS method_name,
        COUNT(DISTINCT CONCAT(to_class, '::', to_method)) AS efferent_coupling,
        GROUP_CONCAT(DISTINCT CONCAT(to_class, '::', to_method) ORDER BY to_class, to_method SEPARATOR ', ') AS called_methods
    FROM calls
    WHERE NOT (from_class = to_class AND from_method = to_method)
    GROUP BY from_class, from_method
),
     AfferentData AS (
         SELECT
             to_class AS class_name,
             to_method AS method_name,
             COUNT(DISTINCT CONCAT(from_class, '::', from_method)) AS afferent_coupling
         FROM calls
         WHERE NOT (from_class = to_class AND from_method = to_method)
         GROUP BY to_class, to_method
     )
SELECT
    CONCAT(m.class_name, '::', m.method_name) AS method_identifier,
    COALESCE(a.afferent_coupling, 0) AS afferent_coupling,
    COALESCE(e.efferent_coupling, 0) AS efferent_coupling,
    m.cyclo_complexity,
    m.cognitive_complexity,
    COALESCE(e.called_methods, '') AS called_methods
FROM methods m
         LEFT JOIN AfferentData a
                   ON m.class_name = a.class_name AND m.method_name = a.method_name
         LEFT JOIN EfferentData e
                   ON m.class_name = e.class_name AND m.method_name = e.method_name
ORDER BY m.class_name, m.method_name;
