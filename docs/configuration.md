# Configuration

## Config files

Loaded by `App\Bootstrap` in this order (later files override earlier ones):

| File | Versioned | Purpose |
|---|---|---|
| `app/config/common.neon` | yes | application/session/DI settings, router, parameters |
| `app/config/services.neon` | yes | models, services, component factories |
| `app/config/config.neon` | **no** | database credentials, mailer (copy from `config.neon.example`) |
| `app/config/config.local.neon` | **no** | optional – loaded only when it exists (local development overrides) |

`app/config/.gitignore` ignores everything except the versioned files.

### config.neon

```neon
database:
	dsn: 'mysql:host=localhost;dbname=master'
	user: '...'
	password: '...'
	options:
		charset: utf8mb4
```

> **Upgrading from the PHP 7 version:** the old file contained
> `options: [- charset: utf-8]` (a list item, not a key). Nette 2.4 silently ignored it and
> used `utf8mb4`; Nette 3 fails with *"preg_match(): Argument #2 ($subject) must be of type
> string, int given"*. Replace it with `charset: utf8mb4` as above. The old file may also contain
> `application`, `session` and `services: router` sections – they are now in `common.neon` and
> can be removed (keeping them is harmless).

### Parameters (`common.neon`)

| Parameter | Default | Meaning |
|---|---|---|
| `cardEditing` | `false` | Allows organizers to save team cards and checkpoint cards in the administration. Disabled because teams enter all data in Palainfo. |
| `wwwDir`, `rootDir` | set by Bootstrap | Paths; `wwwDir` is where uploaded cipher files are stored. |

## Debug mode (Tracy)

Debug mode shows detailed error pages and the Tracy bar; in production errors are logged to
`log/` and visitors see the error pages from `presenters/templates/Error/`.

Debug mode is decided by the environment variable `PALAPELI_DEBUG`:

| Value | Debug mode |
|---|---|
| not set / empty | on only for requests from localhost (Nette default detection) |
| `1` | always on |
| `0` | always off |
| anything else | comma separated list of IP addresses (optionally `secret@ip`) that get debug mode |

Example for Apache: `SetEnv PALAPELI_DEBUG 0` in the virtual host or `.htaccess`.

> The previous version had debug mode hard-coded **on**, which exposed stack traces (including
> SQL and configuration values) to every visitor. It is now off for remote visitors by default.

## E-mail

E-mails are sent through the `Nette\Mail\Mailer` service. Without configuration it is
`SendmailMailer` (PHP `mail()`), the same as before. SMTP can be configured in `config.neon`:

```neon
mail:
	smtp: true
	host: smtp.example.com
	port: 465
	username: ...
	password: ...
	encryption: ssl
```

E-mails sent by the application:

| When | To | Sender |
|---|---|---|
| contact form in the footer | organizers | visitor's e-mail (if given) |
| team cancels its participation | organizers | `Palapeli Web <organizatori@palapeli.cz>` |
| a playing team cancels and there are substitutes | first substitute team (Bcc organizers) | same |
| forgotten password | team's e-mails (new password in the body) | same |

Addresses are defined in `App\Utils\AppConstants`.

## Session

`session: expiration: 14 days` – a team stays logged in for two weeks. Session sections:
`team` (logged-in team) and `selected` (browsed edition).
