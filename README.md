# Ana

Static Analysis: metrics, visualization & graphs for PHP projects. 

A PHPStan plugin records namespaces, classes, methods, calls, properties and globals in MySQL; PHP pages then show metrics, graphs, call graphs and review lists.

Needs PHP 8.4+, `ext-mysqli`, MySQL 8, [PHPStan](https://phpstan.org) 2.3+.

## Setup

1. `composer install`
2. Set the MySQL credentials in `src/config/config.php`.
3. Create the database: `php src/PHPStan/create_db.php` (or run `src/db/db_schema.sql`). Run it again to wipe a previous analysis; saved layouts and settings are kept.
4. In `phpstan.neon` set `anaProjectName`, `paths` (the code to analyze) and `bootstrapFiles` (its autoloader).

## Run

```sh
vendor/bin/phpstan clear-result-cache
vendor/bin/phpstan analyse
```
On Windows
```sh
vendor\bin\phpsta clear-result-cache
vendor\bin\phpsta analyse
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
| Dependencies | Call Graph, Dependency Matrix, Dependency Cycles | Calls between namespaces, classes or methods; the same as a matrix, for a focus, with cycles and heavy dependencies in color; groups that depend on each other |

Every class, method and namespace name links to its detail page (`detail.php`). Tables can be filtered, sorted, exported to CSV, and their column layouts saved.

## How it works

`src/PHPStan` holds three collectors (classes, methods, calls) and `ExportMetricsRule`, which writes them to the `ana` database. `src/api` serves the tables as JSON and `src/*.php` pages render them with [Tabulator](https://tabulator.info) and [ECharts](https://echarts.apache.org). Pages load both from a CDN.

## Limits

- Calls are recorded against the class of the variable they were made on, so methods called through an interface or a parent class can look unused.
- Properties are tracked only through `$this`, `self::` and `static::`.
- Calls outside any method or function, dynamic calls and callbacks by name are not recorded.
