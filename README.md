# Ana a static analysis tool


## Setup database

- Set credentials in config/config.php
- Run create_db.php or db/db_schema.sql
- Edit phpstan.neon to point to the project to analyze

## Run

- vendor\bin\phpstan clear-result-cache
- vendor\bin\phpstan analyse 


## Pages

Open `src/index.php`

## Saved layouts
Table layouts (visible columns, sort order; `db/default_layouts.sql` seeds three practical ones per page) are saved in the `user_layouts` table, shared by every project and every user of this installation. `db_schema.sql` does not drop it, so re-running `create_db.php` keeps them. Layouts an earlier version kept in the browser are moved to the database the first time the page opens.
