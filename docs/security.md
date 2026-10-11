# Security

## Authentication

- Teams log in with team name and password; the team is stored in the session
  (`TeamSession`). There is no lockout or rate limiting.
- The organizer account is the team with ID `-1`.
- Passwords are stored as **unsalted RIPEMD-160** hashes (kept for compatibility with existing
  accounts). Verification uses a constant-time comparison.
- Forgotten password: a link to `Team:resetPassword` is e-mailed to the team; the password changes
  only when the team sets a new one there. The link carries the team ID, the expiry (24 hours) and
  an HMAC-SHA256 token keyed with the team's current password hash (`PasswordHasher::createResetToken`).
  Nothing is stored; the link stops working after the expiry or once the password changes, so it can
  be used only once. (Previously a new random password was generated and e-mailed in plain text,
  so anybody who knew a team name could change its password.)

### Recommended: migrate to `password_hash()`

`teams.password` is `varchar(150)`, enough for bcrypt/argon2. A transparent migration needs only
`PasswordHasher` and the login handler:

1. `hash()` → `password_hash($password, PASSWORD_DEFAULT)`.
2. `verify()` → if the stored hash is 40 hex characters, compare the RIPEMD-160 digest; otherwise
   `password_verify()`.
3. After a successful legacy login, store `hash($password)` (re-hash) for the team.

## Authorization

| Area | Rule | Where enforced |
|---|---|---|
| Administration | organizer team only | `AdministrationPresenter::startup()` – non-organizers get the `forbidden` view, their form submissions HTTP 403 |
| Palainfo | logged-in team registered in the current edition, game started | layout (display) + `PalaInfoPresenter::startup()` (403 for form submissions) + `BaseControl::requireTeamId()` |
| Team pages | logged-in team | templates + `TeamPresenter::requireLoggedTeam()` for the edit and cancel forms |

> Before the rewrite the administration was protected **only in templates**: anybody could submit
> the administration forms (payments, ciphers, editions, …) directly. This is fixed.

Forms do not use CSRF tokens (`$form->addProtection()`); adding them to the administration and
team forms is recommended.

## Output escaping

Latte escapes output by default. `|noescape` is used for:

- texts written by organizers (cipher descriptions, news, organizer discussion posts, log
  messages),
- texts from teams that were stripped of HTML tags on input (team and member names, discussion
  posts) – printed only inside element content,
- constants (colors).

Team names in HTML attributes and JavaScript (scorecard tooltips, menu title) were printed
unescaped before, which allowed script injection through a team name; they are escaped now.

## Other notes

- Debug mode is no longer forced on in production ([configuration.md](configuration.md#debug-mode-tracy)).
- The contact form uses the visitor-supplied address as `From`, which may be rejected by SPF on
  the receiving side; consider sending from the organizers' address with only `Reply-To` set.
- Uploaded cipher files are renamed (`img_s03.png`), only the sanitized extension of the client
  file name is kept. Uploads are available to organizers only.
- `www/.htaccess` denies access to dot-files; `app/`, `log/`, `temp/` must not be served (the root
  `.htaccess` rewrites everything to `www/`).
