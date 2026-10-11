# PHP 8.4 rewrite – what changed

The application was written for PHP 7.1 and Nette 2.4. Nette 2.4 does not run on PHP 8.1+ (class
loading fails on deprecations, its file cache cannot be read), so the whole stack was upgraded and
the code rewritten. Deployment steps: [deployment.md](deployment.md#upgrade-from-the-php-7--nette-24-version--checklist).

## Dependencies

| Package | Before | After |
|---|---|---|
| PHP | ≥ 7.1 | ≥ 8.4 |
| nette/application, di, bootstrap, forms, database | 2.4 | 3.2–3.3 |
| nette/http / utils / mail / robot-loader | 2.4 | 3.4 / 4.1 / 4.2 / 4.1 |
| latte/latte | 2.11 | 3.1 |
| tracy/tracy | 2.4 | 2.12 |
| endroid/qr-code | 3.9 | 6.1 |
| nette/finder, safe-stream, security, caching (direct) | required | removed (unused) |
| phpstan + phpstan-nette | – | dev |

## Code changes

- `declare(strict_types=1)`, typed properties/parameters/returns everywhere; constructor property
  promotion and `readonly` dependencies; enums (`PaymentStatus`, `LogType`, `CipherFileType`);
  `match`; first-class callables; PHP 8.4 asymmetric visibility (`BaseControl::$year`) and
  `new Foo()->method()` chaining.
- Namespaces for components (`App\Components\<Name>`), factory interfaces renamed
  `I<Name>Factory` → `<Name>Factory` with typed `create()`.
- `App\Bootstrap` class instead of `app/bootstrap.php`; configuration split into
  `common.neon` / `services.neon` / `config.neon` (+ optional `config.local.neon`).
- **Stateless models**: the year is a method argument instead of `setYear()` state on shared
  services.
- Session access wrapped in `TeamSession` and `YearSelection` (same session keys as before).
- Shared constants moved from `BasePresenter` to `App\Utils\AppConstants` and `App\Utils\Colors`.
- `BasePresenter::render()` (called manually by every page) replaced by `startup()` +
  `beforeRender()`; state changes moved from render methods to action methods
  (`Team:logout`, `Homepage:default`, `Game:ciphers`).
- Direct `$_GET` access replaced by presenter/component parameters.
- New `PasswordHasher`, `PaymentQrCodeGenerator`, `CipherFileStorage` services.
- Nette 3 API updates: `Explorer` instead of `Context`, `fetch()`/`fetchField()` return `null`
  instead of `false`, `setHtmlAttribute()`, `Form::Email`/`MaxLength`/…, `addInteger()`,
  `addDate()`, `addDateTime()`, `addTime()`, injected `Mailer` instead of `new SendmailMailer`.
- Latte 3: `{ifCurrent}` replaced by `$presenter->isLinkCurrent()`; null-safe access in the cipher
  template; `$color`/`$current` defaults in the layout.
- Removed dead code: `MainMenuPresenter` (had no template), unused model methods
  (`getTeamsFilledCount`, `getUnfilledTeamsData`, `getTesterNotificationDisplay`,
  `hasRegistrationEnded`, `getRegistrationEnd`, `getOrgRelevantLogs`),
  unused template variables (`lastYear`, `hintForStartExists`).

## Behaviour changes and fixed bugs

Security:

- **Administration forms were not protected on the server** – anybody could submit them. Now only
  the organizer team can (HTTP 403 otherwise).
- Palainfo forms can only be submitted by teams of the current edition (previously an anonymous
  request crashed with a database error).
- **Debug mode was always on in production** (Tracy error pages with SQL and configuration for
  everybody). It is now on for localhost only, configurable by `PALAPELI_DEBUG`.
- Team names were printed unescaped into HTML attributes and JavaScript on the scorecard and in the
  menu (script injection through a team name). Escaped now.
- The forgotten-password generator used `srand(time())` + `rand()`; it now uses a CSPRNG.
- The team name in the forgotten-password HTML e-mail is escaped.
- Uploaded file names are built only from the sanitized extension (a client file name without an
  extension used to be stored as given).
- A checkpoint without a configured code no longer accepts an empty code.
- The Archive redirect only accepts absolute presenter destinations.

Functional fixes:

- **Substitute notification never worked**: after a playing team cancelled, the check "was the
  team playing?" ran after its registration had been deleted, so the first substitute never got the
  e-mail. The check now runs before the deletion.
- "Platba na startu" text on the payment page was unreachable (the template tested the unpaid state
  twice).
- Visiting the homepage reset only the browsed year, not its calendar year – after browsing the
  archive the payment page and the team table showed a wrong variable symbol.
- Opening a cipher link with a year computed the calendar year as `year + 2011` (wrong since 2022);
  it is now read from the database.
- Palainfo used the browsed (possibly archive) edition to decide whether the game has started; it
  now always uses the current edition.
- After a wrong confirmation code, Palainfo was supposed to re-open the confirmation screen, but the
  parameter was never compared correctly (and the total-hint case opened the "end game" screen).
  Both screens re-open correctly now.
- The end-of-game handling after a correct code could run twice (duplicate congratulation and
  end-time update); "you finished the game" was decided by a hard-coded checkpoint number 12 –
  now by the edition's checkpoint count.
- An invalid departure time crashed Palainfo; it shows an error message now.
- Afterparty messages are not shown when the afterparty location is empty.
- Registering as a substitute showed "Tým  byl…" without the team name.
- Administration menu: "Výsledky" was highlighted on the team table page instead of results.
- Finalizing a team card returns to the same team (redirect after POST).
- Cipher close time is edited with a time input and saved as `NULL` when empty (it used to save an
  empty string → "Zavírá v 00:00").
- The edition form saves empty dates/times/numbers as `NULL` instead of empty strings (stored as
  `0000-00-00 00:00:00` by MariaDB in non-strict mode); empty afterparty/finish locations and
  account as `NULL`.
- Editing an edition and moving the "current" flag happen in one transaction.
- Playing-team count, payment counts etc. no longer depend on the order in which shared models
  were configured (stateless models).

Kept as before (intentionally):

- Password hashing scheme (see [security.md](security.md) for a migration plan).
- Team and checkpoint card editing in the administration stays disabled (now a config parameter
  `cardEditing`).
- HTML tags are stripped from non-organizer discussion posts (the old code effectively stripped all
  tags, too).
- URLs, templates, CSS, texts and the database schema.
