# Game logic

## Checkpoints

An edition has `checkpoint_count` checkpoints numbered from 0:

| Number | Meaning | Short label |
|---|---|---|
| `0` | start | S |
| `1` … `count − 2` | regular checkpoints | number |
| `count − 1` | finish (with or without a finish cipher, `has_finish_cipher`) | C |
| `count` | "finish password solved" – only in editions with a finish cipher, stored as one more checkpoint | – |

**Finish variants:**

- **No finish cipher** (`has_finish_cipher = 0`) – entering the finish code completes the game.
- **Finish cipher** (`has_finish_cipher = 1`) – after the finish code the team solves the finish
  cipher and types its password (`ciphers.solution` of `count − 1`) into Palainfo; that completes
  the game. It is stored as the departure from the finish **and** a row for checkpoint `count`
  (entry = exit = solve time), so it counts as a separate checkpoint in the standings. The finish
  cipher is always a password cipher (regardless of `has_password_solution`); there is no total
  hint for it. Edition 12 predates the row `count` – its standings use the arrival to the finish.
  The password does not have to come from a cipher handed out at the finish. Two setups:
  - **cipher at the finish** – the finish row holds the finish cipher, its solution is the password;
  - **password from the last cipher** (e.g. edition 6, edition 12) – the last regular cipher
    (`count − 2`) leads to the finish *and* contains the password. Configure `count − 2` as a normal
    location cipher (not "Řešením je heslo") and put the password into "Řešení" of the finish row,
    ideally with an `entry_message` like "Zadejte heslo, které vám vyšlo z poslední šifry." The team
    can enter the password only after it entered the finish code. Whether the total hint on the
    last cipher also gives away the password is up to its `dead_solution` text.

**Klouzavý start** (`years.individual_start`) – every team starts whenever it enters the start
code; standings and the scorecard use times relative to that moment. `game_end` and closing times
are common for everybody.

## Palainfo flow (component `ActionScreen`)

Palainfo is available only to teams registered in the current edition, after `game_start`.
Teams that finished or quit, and everybody after `game_end`, cannot do any action (checked on the
server too, because an outdated page on another phone can still submit the forms).

1. **Next checkpoint** = `MAX(checkpoint_number with arrival) + 1`, or 0 when the team has not
   started (`ResultsModel::getFirstEmptyCheckpoint`).
2. **Entering a checkpoint code** – compared case-insensitively with `ciphers.code` of the next
   checkpoint (a checkpoint without a code accepts nothing). On success the arrival is stored
   (`results.entry_datetime`, `used_hint = 0`), a log entry is written and the team sees its
   order of arrival and the checkpoint's `entry_message` (if any, also written to the log).
   Re-entering the code of the previous checkpoint says it is already entered.
3. **Password ciphers** (`has_password_solution`, and the finish cipher) – after arriving to such a
   checkpoint, the input switches to "ŘEŠENÍ ŠIFRY" and the departure cannot be set. The answer and
   `ciphers.solution` are both webalized before comparing (case, diacritics, spaces and
   punctuation are ignored: `Tajné heslo!` = `tajne-heslo`). A correct answer stores the departure
   and reveals the location of the next checkpoint (`dead_solution`), also written to the log.
   The finish password completes the game (see above).
4. **Departure** – the team can set the departure time from the current checkpoint (a time
   input, or "TEĎ" = now). Empty input means now; a time more than 10 minutes in the future is
   taken as the previous day (departure before midnight); a departure before the arrival is refused.
5. **Total hint ("totálka")** – available on checkpoints `1 … count − 2`, on the start cipher too
   when `years.hint_for_start_exists` is set, never on the finish; once, and not for a password cipher the team has already solved; the team must
   re-enter the code of the current checkpoint. The departure is set to now, `used_hint = 1`, the
   location of the next checkpoint is shown and logged.
6. **Quitting** – the team types `JEZIMADEMDOM` (`ActionScreen::EndCode`);
   `teamsyear.end_time` is set and Palainfo shows the finish and afterparty location.

A wrong confirmation code redirects back with `?defaultScreen=0|1`, which re-opens the
corresponding confirmation screen.

Other Palainfo screens: **KARTA** (team card: arrivals, departures, hints, closing times and
specifications of checkpoints – read only), **PŘÍCHODY** (order of arrivals to the team's current
checkpoint), **INFO** (log: own events + broadcast messages from organizers).

## Results (`ResultsModel::getTeamStandings`)

Teams are ordered by:

1. highest reached checkpoint **minus** number of total hints,
2. highest reached checkpoint,
3. arrival time to the highest checkpoint (with klouzavý start relative to the team's start).

A total hint counts only when the team reached the next checkpoint before the end of the game.
After the game organizers run "Odstranit totálky týmů, které nedošly na další stanoviště"
(`ResultsModel::removeTrailingHints`), which clears hints of teams that did not, and then publish
the results (`years.results_public`). Public pages show results only after the game ended and
they were published (organizers see the scorecard earlier).

## Statistics

- **Cipher page** (Hra → Šifry): number of teams that arrived, ended there, used the hint, and
  the fastest solution (shortest time between arrival and departure without a hint).
- **Stats page**: for each checkpoint how many teams solved the cipher, took the hint, ended
  there, or have no data. A team that reached a later checkpoint is counted as "no data" on
  earlier checkpoints where it has no record.

## Registration and substitutes

- Registration is open between `registration_start` and `registration_end`.
- New teams register with the form; existing teams are registered automatically when they log in
  during the registration period (members are copied from their most recent edition) or via
  "Zaregistrovat" links.
- With `team_limit` set, teams registered over the limit are substitutes. When a **playing** team
  cancels its participation, the first substitute gets an e-mail that a place is free.
- The anti-spam question (registration, discussion for anonymous visitors) expects the number of
  puzzle pieces in the logo: **4**.

## Entry fee payment

The payment page shows the amount, account, deadline and the variable symbol
`<calendar year><team id>`, plus a QR code in the Czech "QR Platba" (SPD) format generated by
`PaymentQrCodeGenerator`. Substitutes are not asked to pay. Organizers set payment states in the
administration (Platby týmů).
