# Deployment

## Requirements

- PHP **8.4** with extensions `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (QR code PNG), `session`
- MySQL 5.7+ / MariaDB 10.4+ (the production database is MariaDB; queries use MySQL specific
  functions such as `TIME_FORMAT`, `GROUP_CONCAT`, `ON DUPLICATE KEY UPDATE`)
- Apache with `mod_rewrite` (the root `.htaccess` rewrites to `www/`, `www/.htaccess` routes
  everything to `index.php`), or any web server routing non-file requests to `www/index.php`
- writable directories: `temp/`, `log/`, `www/cipher_images/` (created on the first upload)

## Fresh installation

1. `composer install --no-dev --optimize-autoloader`
2. `cp app/config/config.neon.example app/config/config.neon` and fill in credentials
   ([configuration.md](configuration.md)).
3. Create the database from [schema.sql](schema.sql) and insert at least one row into `years`
   with `is_current = 1`, the organizer team (`teams.id = -1`) and the rows of `log_type`
   (see [database.md](database.md)).
4. Point the web server document root to the project root (or `www/`).

## Upgrade from the PHP 7 / Nette 2.4 version – checklist

The rewrite needs no database migration. On the server:

1. **Switch PHP to 8.4.** The old code does not run on PHP 8.1+ at all (Nette 2.4 fails during
   class loading), so code and PHP must be switched together.
2. Deploy the code and run `composer install --no-dev --optimize-autoloader`
   (`vendor/` is not versioned; it is rebuilt from `composer.json`).
3. **Edit `app/config/config.neon`:** replace
   ```neon
   options:
   	- charset: utf-8
   ```
   with
   ```neon
   options:
   	charset: utf8mb4
   ```
4. **Delete `temp/cache/`** (compiled Nette 2.4 containers and templates are incompatible).
5. Decide about debug mode – it is now off for non-local visitors unless `PALAPELI_DEBUG` says
   otherwise ([configuration.md](configuration.md#debug-mode-tracy)).
6. Smoke test: homepage, `/game/results/`, login, `/pala-info/`, administration.

Existing sessions keep working (the session format is unchanged), so logged-in teams stay
logged in. Team passwords are unchanged.

## Rollback

Restore the previous code (and its `vendor/`), the old `config.neon` options line and switch
back to the previous PHP version; clear `temp/cache/`. The database is compatible both ways.
