# Database

MariaDB/MySQL, tables in `utf8` (`utf8_czech_ci`, team names `utf8_bin`), connection charset
`utf8mb4`. Full structure: [schema.sql](schema.sql). Access goes through the models in
`app/models` using raw SQL (`Nette\Database\Explorer::query()`); there are no migrations.

## Relations

```
years (year) ─┬─< teamsyear >── teams (id) ──< discussion (team_id, nullable)
              ├─< results   >──┘        └──< reports (team_id)
              ├─< ciphers ──> files (cipher_image_id, solution_image_id, pdf_file_id)
              ├─< updates
              └─< reports
log (team_id, year – both nullable, no FKs) ──> log_type
vw_team_start_datetime = start (checkpoint 0) arrival of each team
```

## Tables

### `years` – game editions

| Column | Meaning |
|---|---|
| `year` (PK) | edition number (1 = 2012, 12 = 2024, …) |
| `calendar_year`, `date` | derived from `game_start` when saved in the administration |
| `word_numbering` | edition in words ("dvanáctý") – used on the reports page |
| `game_start`, `game_end` | game time; drive "has the game started/ended" everywhere |
| `registration_start`, `registration_end` | registration window |
| `checkpoint_count` | number of checkpoints **including start (0) and finish (count − 1)** |
| `has_finish_cipher` | teams must solve a cipher/password at the finish |
| `hint_for_start_exists` | (kept, currently unused by the web) |
| `team_limit` | max. playing teams; later registrations are substitutes; `NULL` = unlimited |
| `entry_fee`, `entry_fee_account` (`number/bankcode`), `entry_fee_deadline`, `entry_fee_return_deadline` | payment info |
| `last_info_time` | when the last information is sent (informative) |
| `results_public` | results are published (set by organizers) |
| `results_final` | (unused by the web) |
| `show_tester_notification` | show the "we are looking for testers" box |
| `is_current` | the current edition (exactly one row) |
| `afterparty_location`, `afterparty_time`, `finish_location`, `finish_open_time` | shown in Palainfo after the game |

### `teams` – team accounts (across editions)

`id`, `name` (unique by convention, binary collation → case sensitive), `password`
(RIPEMD-160 hex, see [security.md](security.md)), `phone1`, `phone2`, `email1`, `email2`
(second contacts are empty strings when not given). Team `id = -1` is the organizer account.

### `teamsyear` – registration of a team to an edition

| Column | Meaning |
|---|---|
| `team_id`, `year` (PK) | |
| `paid` | `0` unpaid, `1` paid, `2` pays at the start (`App\Models\PaymentStatus`) |
| `member1`–`member4` | team members for this edition |
| `registered` | registration time; decides who is playing vs. substitute |
| `end_time` | time (TIME) when the team finished or quit the game; `NULL` = still playing |
| `finalized` | organizers checked the team card |

Playing teams = the first `team_limit` registrations ordered by `registered`.
Variable symbol for the entry fee = `calendar_year` followed by `team_id` (e.g. `2024383`).

### `results` – passage of a team through checkpoints

One row per (`team_id`, `checkpoint_number`, `year`).

| Column | Meaning |
|---|---|
| `entry_datetime` | arrival (team entered the checkpoint code in Palainfo) |
| `exit_datetime` | departure (team set it, solved the password, or took the total hint) |
| `used_hint` | `1` total hint taken, `0` solved, `NULL` not filled in |
| `entry_time`, `exit_time` | legacy TIME columns, not used by the current code |

### `ciphers` – cipher on a checkpoint

PK (`year`, `checkpoint_number`). `name`, `cipher_description` (HTML), `solution_description`
(HTML), `solution`, `dead_solution` (text revealed with the total hint, falls back to `solution`),
`code` (code found on the checkpoint, entered into Palainfo), `specification` ("upřesnítko",
shown on the team card), `checkpoint_close_time`, `has_password_solution` (the solution is a
password typed into Palainfo; then `solution` must be stored webalized – lower/upper case and
diacritics are ignored), file references to `files`.

### `files`

Uploaded cipher files: `path` (relative to `www/`, e.g. `cipher_images/12/3/`) + `name`
(e.g. `img_s03.png`, `sol_s03.png`, `pdf_s03.pdf`).

### `log` and `log_type`

Palainfo log. `type_id` → `log_type` (`App\Models\LogType`):

| id | name | written when |
|---|---|---|
| 1 | Příchod na stanoviště | team entered a checkpoint code |
| 2 | Otevření totálky | team took a total hint |
| 3 | Ukončení hry | team quit the game |
| 4 | Zpráva organizátorům | (not written by the web) |
| 5 | Zpráva od organizátorů | location revealed by a password solution; organizer messages |
| 6 | Uzavření stanoviště | organizer messages |
| 7 | Začátek hry | organizer messages |
| 8 | Konec hry | organizer messages |

Rows with `team_id IS NULL` / `year IS NULL` are broadcast to all teams (administration →
"Zpráva"). A row with `message` is shown as is (HTML), otherwise a text is generated from the type.

### `discussion`

Posts: `name` (author), `team_id` (logged-in team) or `unlogged_team_name`, `created`, `message`
(HTML: tags stripped except for organizers, new lines converted to `<br>`), `thread`
(`main`, `chat`, `sifry_<year>_<checkpoint>`).

### `updates`

News of an edition: `date`, `year`, `message` (HTML, new lines converted to `<br>`).

### `reports`

Links to reports written by teams: `year`, `link`, `team_id`, `name`, `description`.

### `vw_team_start_datetime` (view)

Start time of every team (arrival to checkpoint 0). Used only for the 10th edition (2022),
which had individual starts – its results are computed relative to the start
(`AppConstants::IndividualStartYear`).

## Data notes

- Empty optional dates saved by the old administration are stored as `0000-00-00 00:00:00`.
  The new year form saves `NULL` instead.
- `teamsyear.end_time` is a TIME column although it is set from the current date-time; only the
  "is it NULL" information is used.
