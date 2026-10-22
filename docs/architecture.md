# Architecture

## Tech stack

| Part | Library | Version |
|---|---|---|
| Language | PHP | 8.4 (`declare(strict_types=1)` everywhere) |
| MVC framework | nette/application, nette/di, nette/bootstrap | 3.3 |
| Templates | latte/latte | 3.1 |
| Forms | nette/forms | 3.3 |
| Database | nette/database (raw SQL through `Explorer::query()`) | 3.2 |
| E-mail | nette/mail | 4.2 |
| Debugging / logging | tracy/tracy | 2.12 |
| QR codes | endroid/qr-code | 6.1 |
| Dev tools | phpstan/phpstan (+ phpstan-nette), phpunit/phpunit 12 (tests), nette/tester | 2.x |

The front end is server-rendered HTML with plain CSS (`www/css`) and a little jQuery 1.12
(loaded from Google CDN) for menus and the homepage puzzle animation. Client-side form
validation uses `netteForms.min.js`.

## Directory layout

```
app/
  Bootstrap.php          builds the DI container (debug mode, Tracy, RobotLoader, configs)
  config/
    common.neon          framework configuration (versioned)
    services.neon        service registrations (versioned)
    config.neon          credentials, not versioned – see config.neon.example
    config.local.neon    optional local overrides, not versioned
  router/RouterFactory.php
  presenters/            one presenter per web section, templates in presenters/templates/<Presenter>/
  components/            reusable UI components, each in its own folder with its template
  models/                database models, enums and small services
  session/               typed wrappers of session sections (logged-in team, browsed year)
  utils/                 constants (AppConstants, Colors) and helpers (Utils)
  less/                  LESS sources (compiled CSS is committed in www/css)
www/                     document root: index.php, CSS, images, uploaded cipher files
log/, temp/              Tracy logs and caches (must be writable)
docs/                    this documentation
```

Classes are found by **RobotLoader** (it scans `app/`), so the lower-case directory names do not
have to match namespaces. Only `App\Bootstrap` is autoloaded by Composer (classmap), because it
is needed before RobotLoader exists.

### Namespaces

| Namespace | Contents |
|---|---|
| `App` | `Bootstrap`, `RouterFactory` |
| `App\Presenters` | presenters |
| `App\Components\<Name>` | component `<Name>` and its factory interface `<Name>Factory` |
| `App\Components` | `BaseControl`, `EndgameMessages` trait |
| `App\Models` | models (`*Model`), enums (`PaymentStatus`, `LogType`, `CipherFileType`), services (`PasswordHasher`, `PaymentQrCodeGenerator`, `CipherFileStorage`) |
| `App\Session` | `TeamSession`, `YearSelection` |
| `App\Utils` | `AppConstants`, `Colors`, `Utils` |

## Request lifecycle

1. `www/index.php` calls `App\Bootstrap::boot()`, creates the container and runs
   `Nette\Application\Application`.
2. The single route `<presenter>/<action>[/<id>]` (default `Homepage:default`) maps the URL,
   e.g. `/game/ciphers?checkpoint=3&year=11` → `GamePresenter::actionCiphers(3, 11)` +
   `renderCiphers(3)`.
3. `BasePresenter::startup()` reads the logged-in team and the **browsed year** from the
   session (defaulting to the current edition).
4. Action, signal (form submission) and `beforeRender()` run. `BasePresenter::beforeRender()`
   fills variables used by the layout (menu, archive switch, team name, current/selected year).
5. `render<View>()` adds page specific data; the template
   `presenters/templates/<Presenter>/<view>.latte` is rendered inside `templates/@layout.latte`
   (Palainfo has its own layout `PalaInfo/@palainfo-layout.latte`).
6. Errors: `ErrorPresenter` forwards 4xx errors to `Error4xxPresenter` (pages
   `templates/Error/<code>.latte`), other exceptions are logged and `Error/500.phtml` is shown.

## Key concepts

### Editions (years)

Every piece of game data belongs to an edition identified by its number (`years.year`, e.g. 12)
with a calendar year (`years.calendar_year`, e.g. 2024). Exactly one edition is marked
`is_current`.

Visitors browse one edition at a time – the **selected year** stored in the session
(`App\Session\YearSelection`). The archive links in the footer and the arrows on some pages
switch it through `ArchivePresenter`; visiting the homepage resets it to the current edition.
Palainfo always works with the current edition.

### Stateless models

Models receive the year as an explicit argument (`$teamsModel->getPlayingTeams($year)`).
(The previous version stored it in a mutable `setYear()` state shared by all users of the
singleton service, which made results depend on call order.)

### Teams and login

There is no `Nette\Security` user; a logged-in team is stored in the session section `team`
(`teamId`, `teamName`) and accessed through `App\Session\TeamSession`. Organizers log in as the
special team with ID `-1` (`AppConstants::OrgTeamId`). See [security.md](security.md).

### Components

Larger UI parts are components created by generated factories (interfaces registered in
`services.neon`). The creating presenter passes the year and the team to the component
(`->setYear()->setTeamId()`); see [components.md](components.md).

### Templates (Latte 3)

- Templates use auto-escaping; `|noescape` is used only for trusted HTML (organizer-written
  texts, stored HTML of news/discussion posts that was stripped of tags on input) and for
  constants.
- Shared fragments: `Menu/*.latte`, `Footer/default.latte`, `Extras/*.latte`, SVG icons in
  `components/SVG/*.svg` (included as templates, some take a `dim` or `color` parameter).
- Constants are referenced by full class name, e.g. `{App\Utils\Colors::Game}`.
