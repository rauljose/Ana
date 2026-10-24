# Ana a static analysis tool


## Setup database
- Set credentials in create_db.php and run it
- Set credentials in ExportMetricsRule.php

## Run
vendor\bin\phpstan clear-result-cache
vendor\bin\phpstan analyse 
## Project name
Set `anaProjectName` in phpstan.neon. It is saved in the `project` table and shown in the menu of every page.
Existing databases need the new table: run create_db.php again.

## Pages
Open `src/index.php` through a PHP web server. The top menu links to every page.
