# Palapeli – developer documentation

Web of **Palapeli**, a one-day winter puzzle hunt ("šifrovačka") organized in Brno by team
KVÍK!. The site handles everything around one game edition ("ročník"):

- public information – news, rules, recommended equipment, results, ciphers with solutions and
  statistics, photos and reports from past editions (archive of all editions),
- team accounts – registration to an edition, editing members and contacts, entry fee payment
  (with a QR code), cancellation, forgotten password,
- **Palainfo** – the in-game web app: teams enter checkpoint codes, ask for total hints, see the
  order of arrivals and messages from organizers,
- administration for organizers – news, ciphers, payments, results, team/checkpoint cards,
  messages to teams, editions.

The user interface is in Czech; the code and this documentation are in English.

## Contents

| Document | What is inside |
|---|---|
| [architecture.md](architecture.md) | Tech stack, directory layout, request lifecycle, conventions |
| [configuration.md](configuration.md) | Config files, parameters, debug mode, e-mail, database connection |
| [deployment.md](deployment.md) | Requirements, installation, upgrade from the PHP 7 version (checklist) |
| [database.md](database.md) | Tables, their meaning and relations ([schema.sql](schema.sql)) |
| [game-logic.md](game-logic.md) | Checkpoints, codes, total hints, ending the game, results and statistics |
| [palainfo-audit.md](palainfo-audit.md) | Audit of Palainfo: game variants (klouzavý start, password ciphers, finish password), known bugs, proposed fixes |
| [presenters.md](presenters.md) | All pages: presenters, actions, templates, access rules |
| [components.md](components.md) | UI components (Palainfo screens, admin cards, forms, discussion) |
| [models.md](models.md) | Models and services – the data access API |
| [security.md](security.md) | Authentication, authorization, escaping, known weaknesses |
| [development.md](development.md) | Running locally, static analysis, testing approach |
| [php84-migration.md](php84-migration.md) | What changed in the PHP 8.4 rewrite and why (incl. fixed bugs) |

## Quick start

```bash
composer install
cp app/config/config.neon.example app/config/config.neon   # fill in database credentials
```

Then serve the `www/` directory (Apache with the bundled `.htaccess`, or the PHP built-in server
as described in [development.md](development.md)).

Requirements: PHP 8.4 with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`; MySQL/MariaDB.
