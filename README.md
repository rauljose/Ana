# Ana a static analysis tool


## Setup database
- Set credentials in create_db.php and run it
- Set credentials in ExportMetricsRule.php

## Run
vendor\bin\phpstan clear-result-cache
vendor\bin\phpstan analyse 
## Project name
Set `anaProjectName` in phpstan.neon. It is saved in the `project` table and shown in the menu of every page.
Existing databases need the new tables and columns (project, doc_comment, read/write access): run create_db.php again, then analyse again.

## Pages
Open `src/index.php` through a PHP web server. The top menu links to every page.

## Saved layouts
Table layouts (visible columns, sort order; `db/default_layouts.sql` seeds three practical ones per page) are saved in the `user_layouts` table, shared by every project and every user of this installation. `db_schema.sql` does not drop it, so re-running `create_db.php` keeps them. Layouts an earlier version kept in the browser are moved to the database the first time the page opens.
