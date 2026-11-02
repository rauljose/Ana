# Ana PHPStan plugin

Runs inside `phpstan analyse` and stores what it finds about the analyzed project in the `ana` MySQL database. It reports no PHPStan errors of its own, except a database error if the export fails.

| Class | Collects |
|---|---|
| `ClassDefinitionCollector` | Classes, interfaces and traits: modifiers, extends, implements, traits used, properties and class constants (visibility, static, type, doc comment) |
| `MethodDefinitionCollector` | Methods and functions: signature, cyclomatic and cognitive complexity, parameters, return type, purity, `#[SensitiveParameter]`, properties, superglobals, `global` variables and constants read or written |
| `MethodCallCollector` | Calls between methods and functions (caller, callee, native or not) |
| `SqlUsageFinder`, `SqlStatements` | Used by `MethodDefinitionCollector`: the SQL in each method (strings that read as SQL, and the Sqler `insert()`, `update()` and `junctionTable()` helpers): operation and table or procedure |
| `FirebaseUsageFinder` | Used by `MethodDefinitionCollector`: the Firebase paths each method reads and writes through kreait/firebase-php (Realtime Database, Firestore, Storage), and what each call does to the path |
| `ExportMetricsRule` | Receives everything the collectors gathered and writes it to MySQL in one transaction |

## Wire it

Register the collectors and the rule as services in `phpstan.neon` (see the one in the project root) and set `anaProjectName`. The database and tables are created on the first connection (`db/ensure_schema.php`); `php src/PHPStan/create_db.php` starts them over. Credentials are in `src/config/config.php`.

## Notes

- Run `vendor/bin/phpstan clear-result-cache` before analyzing again, or PHPStan skips unchanged files and their data.
- Each run first deletes everything from every table except `user_layouts` and `user_settings`, so deleted classes and methods do not linger.
