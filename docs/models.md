# Models and services

All in `app/models`, namespace `App\Models`, registered in `services.neon` and autowired.
Database models extend `BaseModel` (holds `Nette\Database\Explorer`) and are **stateless** –
every method that works with edition data takes the edition number `int $year`.

Rows are returned as `Nette\Database\Row` (`$row->column` or `$row['column']`); "not found" is
`null`. Methods with `fetchAssoc()` return arrays keyed as documented in their PHPDoc.

## YearsModel – table `years`

| Method | Returns |
|---|---|
| `getCurrentYearData()` | current edition (fallback: newest by date); throws when there is none |
| `getCurrentYearNumber()`, `getCalendarYear($year)` | numbers |
| `getYearData($year)`, `getEndgameData($year)` | `?Row` |
| `getCheckpointCount`, `hasFinishCipher`, `getTeamLimit` | per-edition values |
| `isRegistrationOpen`, `hasRegistrationStarted`, `hasGameStarted`, `hasGameEnded` | evaluated by the database clock |
| `getRegistrationStart($year)` | formatted "1. 12. 2025 v 20:00" |
| `getArchiveSwitchData()`, `getYearNames()` | lists of editions |
| `isTeamInCurrentYear($teamId)` | team registered in the current edition |
| `addYear($values)`, `editYear($values)` | save; `is_current` is moved in a transaction |

## TeamsModel – tables `teams`, `teamsyear`

- Lists: `getPlayingTeams`, `getPlayingTeamsByRegistration`, `getStandbyTeams`,
  `getPlayingTeamsIds`, `getFirstStandby`, `getTeamsPaymentStatus`, `getUnpaidTeamsData`,
  `getPlayingTeamsEmails` (playing = first `team_limit` by registration time).
- Team: `getTeamId($name)`, `getTeamName`, `getTeamData($teamId, $year)`, `getEmailsByName`,
  `isNameTaken`, `getPasswordHash`, `getMostRecentTeamYearData`.
- Registration: `isTeamRegistered`, `registerTeam`, `deleteTeamRegistration`,
  `getTeamRegistrationOrder`, `getTeamsCount`.
- Changes: `addNewTeam`, `updatePassword`, `updateTeamMembers`, `updateTeamContactInfo`.
- Payment: `getTeamPaymentStatus` (`?PaymentStatus`), `editTeamPayment`,
  `countTeamsWithPaymentStatus`.
- Game: `teamEnded`, `hasTeamEnded`, `isTeamFinalized`, `finalizeTeam`.

## ResultsModel – table `results`

- Standings and scorecard: `getTeamStandings`, `getCompleteResults`, `getResultsPublic`,
  `publishResults`, `removeTrailingHints` (special handling of the 10th edition – relative times).
- Statistics: `getStatsData`, `getFastestSolution`, `getTeamsFilledIds`, `getTeamsArrivedCount`,
  `getTeamsContinuedIds`, `getUsedHintsCount`, `getTeamsWithFilledStatus`.
- Palainfo: `getFirstEmptyCheckpoint`, `getLastCheckpointNumber`, `getLastCheckpointData`,
  `hasTeamLeft`, `hasTeamOpenedDead`, `getTeamResults`, `getCheckpointEntryTimes`.
- `insertResultsRow($teamId, $year, $checkpoint, $entryTime, $exitTime, $usedHint)` – creates
  the row if missing and updates the given values (`null` = keep, `''` = clear).
- `whereIsWho($year)`.

## CiphersModel – table `ciphers`

`getCipher` (with file paths), `upsertCipher`, `attachFile(…, CipherFileType, $fileId)`,
`getDeadSolution`, `checkCode`, `checkSolution`, `getCheckpointCloseTimes`, `getSpecifications`,
`getSpecification`, `hasCheckpointPasswordSolution`.

## Other models

| Model | Table | Methods |
|---|---|---|
| `DiscussionModel` | `discussion` | `getAll`, `getAllByThread`, `getThreads`, `insertPost` |
| `FilesModel` | `files` | `getFileId`, `insertFile` (reuses an existing record) |
| `LogModel` | `log`, `log_type` | `log(LogType|int, …)`, `getLogsForTeam`, `getLogTypes` |
| `ReportsModel` | `reports` | `getReports` (grouped by year), `insertReport` |
| `UpdatesModel` | `updates` | `getUpdates`, `addUpdate` |

## Enums

| Enum | Backing | Cases |
|---|---|---|
| `PaymentStatus` | int (`teamsyear.paid`) | `Unpaid` 0, `Paid` 1, `AtStart` 2; `label()`, `options()` |
| `LogType` | int (`log.type_id`) | `EnterCheckpoint` 1 … `GameFinish` 8 |
| `CipherFileType` | string (column name) | `CipherImage`, `SolutionImage`, `Pdf`; `filePrefix()` |

## Services

| Service | Purpose |
|---|---|
| `PasswordHasher` | `hash()`, `verify()`, `createResetToken()`, `verifyResetToken()` – the only place that knows the password scheme |
| `PaymentQrCodeGenerator` | `createPaymentString()` (SPD format), `createDataUri()` (PNG) |
| `CipherFileStorage` | stores uploads to `www/cipher_images/<year>/<checkpoint>/` and registers them in `files` |
| `App\Session\TeamSession` | logged-in team: `getTeamId`, `getTeamName`, `isLoggedIn`, `isOrg`, `logIn`, `logOut` |
| `App\Session\YearSelection` | browsed edition: `getYear`, `getCalendarYear`, `select` |
