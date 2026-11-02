# Ana PHPStan plugin

Runs inside `phpstan analyse` and stores what it finds about the analyzed project in the `ana` MySQL database. It reports no PHPStan errors of its own, except a database error if the export fails.

| Class | Collects |
|---|---|
| `ClassDefinitionCollector` | Classes, interfaces and traits: modifiers, extends, implements, traits used, properties and class constants (visibility, static, type, doc comment) |
| `MethodDefinitionCollector` | Methods and functions: signature, cyclomatic and cognitive complexity, parameters, return type, purity, `#[SensitiveParameter]`, properties, superglobals, `global` variables and constants read or written |
| `MethodCallCollector` | Calls between methods and functions (caller, callee, native or not) |
| `ExportMetricsRule` | Receives everything the collectors gathered and writes it to MySQL in one transaction |

## Wire it

Register the collectors and the rule as services in `phpstan.neon` (see the one in the project root) and set `anaProjectName`. Create the tables first with `php src/PHPStan/create_db.php`. Credentials are in `src/config/config.php`.

## Notes

- Run `vendor/bin/phpstan clear-result-cache` before analyzing again, or PHPStan skips unchanged files and their data.
- Each run first deletes everything from every table except `user_layouts` and `user_settings`, so deleted classes and methods do not linger.
