# Ana a static analysis tool


## Setup database

- Set credentials in config/config.php
- Run create_db.php or db/db_schema.sql
- Edit phpstan.neon to point to the project to analyze

## Run
```sh
vendor\bin\phpstan clear-result-cache

vendor\bin\phpstan analyse 
```



## View

Open `src/index.php`

