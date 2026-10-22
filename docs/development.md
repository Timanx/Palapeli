# Development

## Local setup

1. PHP 8.4 (`pdo_mysql`, `mbstring`, `fileinfo`, `gd`), MySQL/MariaDB (e.g. XAMPP).
2. `composer install`
3. `app/config/config.neon` with database credentials; local overrides (another database, Tracy
   strict mode, a test mailer) go to `app/config/config.local.neon`, which is git-ignored:

   ```neon
   database:
   	dsn: 'mysql:host=localhost;dbname=palapeli_test'
   	user: root
   	password: ''

   tracy:
   	strictMode: yes   # turn notices and warnings into exceptions
   ```

4. Serve `www/`. With Apache use the bundled `.htaccess`; with the PHP built-in server use a
   router script that serves existing files and sends everything else to `index.php`:

   ```php
   <?php // router.php
   $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
   if ($path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
   	return false;
   }
   $_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
   require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
   ```

   ```bash
   php -S 127.0.0.1:8000 -t www router.php
   ```

Debug mode is on automatically for localhost.

Tip: work on a copy of the database – Palainfo and the administration write data. The view
`vw_team_start_datetime` contains a `DEFINER` and the schema name, so strip both when copying:

```bash
mysql -u root -e "CREATE DATABASE palapeli_test CHARACTER SET utf8 COLLATE utf8_czech_ci"
mysqldump -u root --skip-lock-tables master | sed -E -e 's/DEFINER=`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g' -e 's/`master`\.//g' | mysql -u root palapeli_test
```

## Static analysis

```bash
composer phpstan          # PHPStan level 6 with the Nette extension (phpstan.neon)
composer lint:latte       # Latte 3 syntax check of all templates
```

`latte-lint` reports "Unknown class constant App\…" warnings because it does not load the
application's autoloader; they are harmless.

## Testing

### Palainfo tests (PHPUnit)

```bash
composer test                                        # all tests
vendor/bin/phpunit --filter RegularCipherTest        # one class
vendor/bin/phpunit --filter "testSolving@password.*" # matrix cases whose name starts with "password"
php -d xdebug.mode=off vendor/bin/phpunit            # ~1/3 faster when Xdebug runs in debug mode
```

The tests play Palainfo the way teams do: every step is a real request handled by the whole
application (router, presenter, components, forms, Latte templates, session, redirects with flash
messages), run **in the PHPUnit process** – no web server. A test creates an edition with its
ciphers and teams, plays and checks the screens (headings, offered forms, messages), the stored
`results`, `log` and `teamsyear` rows and the standings.

- **Database:** a dedicated database (`palapeli_phpunit`) is **dropped and created** from
  `docs/schema.sql` at the start of every run, so keep `schema.sql` up to date with `migrations/`.
  Every test runs in a transaction that is rolled back; tests start with empty tables and do not
  depend on each other. Connection: environment variables `PALAPELI_TEST_DB_HOST` (`127.0.0.1`),
  `PALAPELI_TEST_DB_NAME` (`palapeli_phpunit`, must contain "test" or "phpunit"),
  `PALAPELI_TEST_DB_USER` (`root`), `PALAPELI_TEST_DB_PASSWORD` (empty). `config.neon` is not used.
- **Time:** instead of a fake clock, `Game::travel($minutes)` moves all stored times of the edition
  (arrivals, pickups, departures, log, game start and end) to the past – the same as if the time
  went on. `game_end` moves too, so travel far only after `Game::setGameEnd()`.
- **Outdated pages:** `Team::secondPhone()` is another phone of the same team, `Team::submitOutdated()`
  submits a form from a page loaded earlier and `Team::forge()` submits a form the screen does not
  offer – the server must refuse what the current state does not allow.
- Tests must not print anything (`echo`, `var_dump`): once output is sent, PHP refuses to start
  the sessions of the simulated requests.

| Test | Covers |
|---|---|
| `RegularCipherTest` | every combination of the cipher options on a regular checkpoint (location/password × no wait/wait + pickup code/wait without pickup code × entry message × specification and closing time × own hint text/solution) – 48 ciphers × arrival, solving, total hint, outdated page |
| `StartTest` | the start: specification, start code, entry message, checkpoint without a code, `hint_for_start_exists`, password start cipher, wait at the start |
| `FinishTest` | the finish without a finish cipher, finish cipher at the finish / password from the last cipher, wait at the finish, order of finishers, a finish cipher without a password |
| `DepartureTest` | typed time, empty = now, corrections, before the arrival, a time in the future = previous day, invalid time, after the hint |
| `CipherWaitTest` | countdown, pickup before the countdown ends, two phones |
| `GameStateTest` | before the start, team of another edition, anonymous visitor, after the end of the game, quitting, messages from organizers |
| `WholeGameTest` | whole games of five teams for all 8 combinations of finish cipher × hint for the start × klouzavý start, checked against the standings (incl. trailing hints) and the scorecard |

Typed departure times are relative to the current time, so the tests pass at any time of day.
Not covered: the JavaScript on AKCE (countdown turning green, confirmation screens) – the tests
check the HTML and data it starts from.

### Other parts

The rest of the web was verified manually by end-to-end scenarios against a copy of the production
database (public pages of several editions, registration, payments, forgotten password,
cancellation, every administration page and form). The most valuable next tests are integration
tests of the models (statistics, team limit/substitutes) and of the administration forms, written
the same way as the Palainfo tests (`tests/Support`).

## Conventions

- `declare(strict_types=1)`, typed properties and return types, constructor property promotion,
  `readonly` dependencies; tabs for indentation (Nette coding standard).
- Models are stateless and take the edition as a parameter.
- Form success handlers are private methods attached as first-class callables
  (`$form->onSuccess[] = $this->formSucceeded(...)`) with `array $values`.
- New constants: PascalCase (`AppConstants::OrgTeamId`), enums for closed sets of values.
- Text in **Raleway that contains digits** must use lining figures (all digits on the same
  baseline) – Raleway's default digits are old-style:

  ```css
  -webkit-font-feature-settings: "lnum" 1;
  -moz-font-feature-settings: "lnum" 1;
  font-feature-settings: "lnum" 1;
  ```

  It is set globally on `html` in `styles.css` (form controls are told to inherit it) and on
  `body` in `palainfo.css`, so new elements get it automatically – do not override
  `font-feature-settings` without keeping `"lnum" 1`.
- When changing static CSS, bump `$version` in both layouts (`@layout.latte`,
  `PalaInfo/@palainfo-layout.latte`) so browsers do not use a cached stylesheet.
- The footer has a fixed height (sticky footer): `.footer` / `.wrapper:after` height and the
  `.wrapper` negative margin in `responsive.css` must match `handleMailForm()` in
  `Footer/default.latte`.
- Update `docs/` together with the code.

## Yearly maintenance

- Create the new edition in Administrace → Ročníky (mark it current), fill in ciphers.
- Add the photo album link to `templates/Game/photos.latte`.
- Equipment and rules texts are in `templates/Info/*.latte`.
