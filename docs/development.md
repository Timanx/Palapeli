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

There is no automated test suite in the repository yet (`nette/tester` is installed as a dev
dependency). The rewrite was verified by end-to-end scenarios run against a copy of the
production database:

- all public pages for several editions (incl. the 10th edition with individual starts and the
  oldest editions),
- registration, login, editing, payment page with QR code, forgotten password (e-mail content),
  cancellation with the substitute notification,
- the whole Palainfo flow: start code, departures, regular codes, password cipher, total hint,
  wrong confirmation codes, quitting the game, all four screens,
- every administration page and form: news, payments, team message, report, edition edit/create
  (round trip without changes), cipher with file upload, team/checkpoint cards, results, discussion,
- refusal of anonymous form submissions to the administration and Palainfo.

When adding tests, the most valuable are integration tests of the models against a test database
(results ordering, statistics, team limit/substitutes) and of `ActionScreen` handlers.

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
