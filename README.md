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

## Metrics

Metrics shown in the Namespace, Class and Method Metrics pages (hover a column header there for the full definition). A metric listed for several levels is rolled up: the class value sums or averages its methods, the namespace value its classes.

| Metric | Description | For |
|---|---|---|
| Classes, Abstract, Interfaces, Traits, Total types | Count of each kind of type, and all of them together | Namespace |
| Type | Class, Abstract Class, Final Class, Interface or Trait | Class |
| Kind | Method, Function or Native Function | Method/function |
| Visibility | public, protected or private | Method |
| Static, Static class | Static method; class that only has static methods | Method, Class |
| Built-in | PHP native function called by the project | Method/function |
| Readonly class | Declared readonly | Class |
| Doc comment | Doc block above the class or method | Class, Method |
| Extends, Implements, Uses traits | Parent class, interfaces and traits | Class |
| Children | Classes that extend this one | Class |
| Methods (Total, Public, Protected, Private) | Number of methods, by visibility | Class, Namespace |
| Cyclomatic (total, avg, max) | Independent paths through the code; the minimum number of tests | Method, Class, Namespace |
| Cognitive (total, avg, max) | How hard the code is to read; nesting counts extra | Method, Class, Namespace |
| Pure / Pure % | No side effects; share of pure methods | Method, Class, Namespace |
| Ends script | Calls `exit` or `die`, or never returns | Method/function, Class (count) |
| Hotspot score | Cyclomatic x incoming calls: complex code many depend on | Method, Class, Namespace |
| Params | Number of parameters | Method/function |
| Defaults | Parameters with a default value | Method/function |
| By-reference params | Parameters passed by reference | Method/function, Class (total) |
| Object params | Parameters typed with a class | Method/function, Class (total) |
| Constructor params | Parameters of the constructor, mostly dependencies | Class |
| Sensitive params | Uses `#[SensitiveParameter]` | Method/function, Class (count) |
| Return type | Declared or inferred return type | Method/function |
| Signature | Full signature | Method/function |
| Props read, Props written (and lists) | Properties the method reads or writes | Method |
| Globals (and list) | Superglobals, `global` keyword and constants used | Method |
| Calls out (Fan-out), Calls out native | Calls made, and how many go to native functions | Method/function, Namespace |
| Calls in (Fan-in) | Calls received | Method/function, Namespace |
| Calls internal, Internal ratio | Calls inside the namespace; internal / (internal + out), cohesion | Namespace |
| Calls out list, Calls in list | Which methods are called, and which call this one | Method/function |
| Ca (Afferent coupling) | Classes or namespaces that call this one | Class, Namespace |
| Ce (Efferent coupling) | Classes or namespaces this one calls | Class, Namespace |
| Instability | Ce / (Ca + Ce); 0 stable, 1 unstable | Class, Namespace |
| Abstractness | Share of interfaces and abstract classes | Class, Namespace |
| Main seq. distance | abs(abstractness + instability - 1); far from 0 is rigid or useless | Class, Namespace |
| MPC | Message passing coupling: calls to other classes | Class |
| DAC | Data abstraction coupling: properties typed with another class | Class |
| Classes used, Classes using this | The classes this one calls, and those that call it | Class |
| Namespaces used, Namespaces using this | The namespaces this one calls, and those that call it | Namespace |
| In cycle | Part of a dependency cycle | Namespace |
| LCOM | Lack of cohesion of methods; 0 is cohesive | Class |
| Mutable props, Readonly props, Props written | Properties that can change, that cannot, and that some method writes | Class |
| Superglobals, global keyword, Constants | Uses of global state | Class |
| God class | LCOM >= 0.8, cyclomatic total above 40 and Ce above 10 | Class |
| Data class | Several mutable properties, few methods, almost no logic | Class |
| Stateless service | No mutable properties and mostly pure methods | Class |
| Unused candidate | Nothing calls it (or extends it); possibly dead code | Method/function, Class |

## Graphs

| Graph | Description | For |
|---|---|---|
| Zone of Pain (scatter) | Efferent coupling (Ce) against total cyclomatic complexity; red is Ce > 10 and complexity > 40 | Class |
| Class Health Radar | LCOM, purity, average complexity, Ce and Ca of the chosen classes | Class |
| Method Hotspots (scatter) | Fan-out against cyclomatic complexity, dot size is fan-in; red is fan-out > 10 and complexity > 15 | Method/function |
| Method Health Radar | Cyclomatic, cognitive, fan-out, fan-in, parameters, props written and globals used | Method/function |
| Call Graph | Nodes joined by arrows from caller to callee, thicker for more calls; the focus shows all its incoming and outgoing calls | Namespace, class or method (you choose the level) |
| Dependency Matrix | Who depends on whom as a matrix; cycles, hubs, layers and heavy dependencies in color | Namespace, class, method, plus shared properties, variables and constants |
| Maltese Cross | Matrix of database tables against the methods that read (R) and write (W) them | Method/function and SQL table or procedure |
| Firebase Cross | Matrix of Firebase paths against the methods that overwrite, change, append, delete or read them | Method/function and Firebase path |
| Dependency Cycles | Groups that depend on each other in a loop, with an example path and the calls behind it | Namespace, class or method |
| Change Impact | Everything that calls a method, directly or through other methods: what to retest before changing it | Method/function |

## SQL

The plugin also reads the SQL a method holds, in string literals, concatenations of literals and class constants, heredocs and interpolated strings, and the Sqler `insert()`, `update()` and `junctionTable()` helpers. It records SELECT, INSERT, UPDATE, DELETE, REPLACE, TRUNCATE, CALL and CREATE, ALTER, DROP, RENAME by table or procedure (a table named by a variable shows as `{?}`). The text is read, not run or fully parsed, and it counts for the method that holds it. The helper names are in `src/PHPStan/SqlUsageFinder.php`. A database analyzed before this only needs the analysis run again: the `method_sql` table is created when missing.

## Firebase

Projects using kreait/firebase-php (the latest version) get the same for their Firebase data: the plugin reads the call chains of each method (`getReference('a')->getChild('b')->set(...)`), the variables and typed parameters holding a reference, and the references passed to transactions, bulk writers and batches. It covers the Realtime Database, Firestore and Cloud Storage, and records the path, whether the call reads or writes, and what it does to the path: overwrites it and everything under it, changes only the children it names, appends a new child, or deletes it. A part of a path only known at run time is `{?}`. Firebase Report lists them by path, Firebase Cross puts the paths against the methods. Needs the analysis run again, as for the SQL.

## Limits

- Calls are recorded against the class of the variable they were made on, so methods called through an interface or a parent class can look unused.
- Properties are tracked only through `$this`, `self::` and `static::`.
- Calls outside any method or function, dynamic calls and callbacks by name are not recorded.
