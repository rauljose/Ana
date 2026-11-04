# Ana

Static Analysis: metrics, visualization & graphs for PHP projects. 

A PHPStan plugin records namespaces, classes, methods, calls, properties and globals in MySQL; PHP pages then show metrics, graphs, call graphs and review lists.

Needs PHP 8.4+, `ext-mysqli`, MySQL 8, [PHPStan](https://phpstan.org) 2.3+.

## Setup

Install Ana as its own website, not inside the project you analyze. Ana keeps its own `vendor/` and MySQL credentials, and the project to analyze is only a path in `phpstan.neon`.

1. `composer create-project ocallit/ana /path/to/htdocs/ana` (or `git clone` and `composer install`)
2. Set the MySQL credentials in `src/config/config.php`. The database and its tables are created the first time a page, an API or the analysis connects, and any table a later update adds is created the same way: nothing to install by hand. Existing tables and their data are never touched.
3. Db schema is auto created. Optionally create it. `php src/PHPStan/create_db.php` starts the analysis tables over (after a table gained a column, or to wipe a previous analysis); saved layouts and settings are kept.
4. In `phpstan.neon` set `anaProjectName`, `paths` (the code to analyze) and `bootstrapFiles` (its autoloader).

### Alternative: as a dev dependency of the analyzed project

`composer require --dev ocallit/ana` in the project to analyze. With `phpstan/extension-installer` the plugin is registered for you; otherwise add `includes: - vendor/ocallit/ana/extension.neon` to the project's `phpstan.neon`, and set `anaProjectName`. The MySQL credentials are then in `vendor/`, and `composer update` overwrites them, so prefer the website install above.

## Run

```sh
vendor/bin/phpstan clear-result-cache
vendor/bin/phpstan analyse

```
On Windows
```sh
vendor\bin\phpstan clear-result-cache
vendor\bin\phpstan analyse

```


Then serve `src/` with Apache or `php -S localhost:8000 -t src` and open `index.php`.

## View

| Menu | Page | Shows |
|---|---|---|
| Home | `index.php` | Totals, average and maximum complexity, a box for every page |
| Review | `review.php` | Short lists to look at first: shared globals and static state, handlers, hard to change and most used methods |
| Namespaces | Namespace Metrics | Size, complexity, coupling and cycles per namespace |
| Classes | Class Metrics, Class Graphs | Every class metric; Zone of Pain and health radar |
| Methods | Method Metrics, Method Graphs, Public Method Consumers, Change Impact | Every method metric; hotspots; who uses each public method; callers of a method |
| State | Globals Report, Method Globals, Shared State | Globals, superglobals, constants and static properties, and who reads or writes them |
| SQL | SQL Report, Maltese Cross | The SQL in the code: which methods read, change, call or define each table and procedure, and that as a tables by methods matrix with R, W or RW in each cell |
| Firebase | Firebase Report, Firebase Cross | The Firebase paths (Realtime Database, Firestore, Storage) the code reads and writes, and whether a write overwrites, changes children, appends or deletes |
| Dependencies | Call Graph, Dependency Matrix, Dependency Cycles | Calls between namespaces, classes or methods; the same as a matrix, for a focus, with cycles and heavy dependencies in color; groups that depend on each other |

Every class, method and namespace name links to its detail page (`detail.php`). Tables can be filtered, sorted, exported to CSV, and their column layouts saved.

## How it works

`src/PHPStan` holds three collectors (classes, methods, calls) and `ExportMetricsRule`, which writes them to the `ana` database. `src/api` serves the tables as JSON and `src/*.php` pages render them with [Tabulator](https://tabulator.info) and [ECharts](https://echarts.apache.org). Pages load both from a CDN.

## SQL

The plugin also reads the SQL a method holds, in string literals, concatenations of literals and class constants, heredocs and interpolated strings, and the Sqler `insert()`, `update()` and `junctionTable()` helpers. It records SELECT, INSERT, UPDATE, DELETE, REPLACE, TRUNCATE, CALL and CREATE, ALTER, DROP, RENAME by table or procedure (a table named by a variable shows as `{?}`). The text is read, not run or fully parsed, and it counts for the method that holds it. The helper names are in `src/PHPStan/SqlUsageFinder.php`. A database analyzed before this only needs the analysis run again: the `method_sql` table is created when missing.

## Firebase

Projects using kreait/firebase-php (the latest version) get the same for their Firebase data: the plugin reads the call chains of each method (`getReference('a')->getChild('b')->set(...)`), the variables and typed parameters holding a reference, and the references passed to transactions, bulk writers and batches. It covers the Realtime Database, Firestore and Cloud Storage, and records the path, whether the call reads or writes, and what it does to the path: overwrites it and everything under it, changes only the children it names, appends a new child, or deletes it. A part of a path only known at run time is `{?}`. Firebase Report lists them by path, Firebase Cross puts the paths against the methods. Needs the analysis run again, as for the SQL.

## Limits

- Calls are recorded against the class of the variable they were made on, so methods called through an interface or a parent class can look unused.
- Properties are tracked only through `$this`, `self::` and `static::`.
- Calls outside any method or function, dynamic calls and callbacks by name are not recorded.
