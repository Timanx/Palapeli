# Presenters and pages

All presenters live in `app/presenters` (namespace `App\Presenters`), templates in
`app/presenters/templates/<Presenter>/<view>.latte`. URLs follow the route
`<presenter>/<action>[/<id>]` with dashes (`PalaInfo:checkpoint` → `/pala-info/checkpoint`).

"Selected year" = the edition the visitor browses ([architecture.md](architecture.md#editions-years)).

## BasePresenter (abstract)

Common ancestor of all page presenters.

- `injectBase()` – `YearsModel`, `TeamSession`, `YearSelection`, `Mailer`.
- `startup()` – loads `$teamId` (logged-in team) and `$selectedYear` / `$selectedCalendarYear`.
- `beforeRender()` – layout variables: `teamName`, `teamNameUpper`, `teamId`, `orgLogged`,
  `isTeamInCurrentYear`, `selectedYear`, `selectedCalendarYear`, `currentYear`,
  `currentCalendarYear`, `isSelectedYearCurrent`, `showTesterNotification`, `hasFinishCipher`
  (of the selected year), `archiveData`, `heading`.
- `prepareHeading($heading)` – page heading (upper-cased) and `<title>`.
- `selectYear($year, ?$calendarYear)` – switches the browsed edition.
- Component `mailForm` – contact form in the footer (e-mail to organizers).

Layout `templates/@layout.latte`: section color by presenter (Team blue, Game green, Info red,
Discussion yellow, Administration black), menu (`Menu/menu.latte` + submenus), flash messages,
footer (`Footer/default.latte`) with the archive year switch, contact links and the mail form.

## HomepagePresenter – `/`

`actionDefault` resets the browsed edition to the current one; the page has no menu
(`hideMenu`) and shows four puzzle pieces linking to Hra, Diskuse, Info and Tým (`hover.js`
animates them).

## InfoPresenter – `/info/…`

| View | Heading | Content |
|---|---|---|
| `default` | Aktuality | news of the selected year (`updates`) |
| `rules` | Pravidla | rules generated from the **current** year's data (dates, fee, team limit) |
| `equipment` | Doporučená výbava | static list |

## TeamPresenter – `/team/…`

| View / action | Heading | Content |
|---|---|---|
| `default` | Přihlášení | login form, link to forgotten password |
| `registration` | Registrace | registration form (only for the current year while registration is open) |
| `edit` | Úprava údajů | edit members, contacts, password (organizers can edit their account any time) |
| `payment` | Platba startovného | payment state, instructions, QR code |
| `cancel` | Zrušení účasti | cancel the registration (current year, before the game) |
| `password` | Zapomenuté heslo | e-mail a link for setting a new password |
| `resetPassword` | Nové heslo | page of the e-mailed link (`?team&expires&token`): set a new password |
| `actionLogout` / `logout` | Odhlášení | logs the team out |
| `actionRegisterLogged` | – | registers the logged-in team to the selected (current) year, redirects to `edit` |

Login outcomes: organizer → administration; team registered in the selected year → `edit`;
selected year is not current → `Info:`; registration closed → `Info:`; otherwise the team is
registered automatically.

## GamePresenter – `/game/…` (selected year)

| View | Heading | Content |
|---|---|---|
| `default` | Seznam týmů | playing teams with payment icons, substitutes |
| `results` | Výsledky | standings (after the game and publication) |
| `scorecard` | Podrobné výsledky | arrival/departure times of all teams on all checkpoints, colored by hints |
| `ciphers` | Šifry | cipher, solution, statistics and the discussion thread of one checkpoint; hidden for the current year until the game ends (organizers see it) |
| `stats` | Statistiky | SVG chart of cipher difficulty |
| `photos` | Fotky | static links to photo albums (update the template every year) |
| `reports` | Reportáže | reports of all editions |

`actionCiphers(int $checkpoint = 0, ?int $year = null)` switches the browsed year when the link
carries one, so links to ciphers can be shared.

## DiscussionPresenter – `/discussion/`

Main discussion thread (`DiscussionControl`, thread `main`).

## ArchivePresenter – `/archive/?year=…&calendarYear=…&link=…`

Switches the browsed year and redirects to `link` (an absolute destination such as
`:Game:results`; anything else redirects to the homepage). `calendarYear` is optional – it is
looked up in the database.

## PalaInfoPresenter – `/pala-info/…`

Palainfo for teams of the **current** edition; own layout `PalaInfo/@palainfo-layout.latte` with
a bottom menu. The layout shows a message instead of the content when the team is not logged
in, not registered in the current edition, or the game has not started.

| View | Component |
|---|---|
| `default` (`?defaultScreen=0/1`) | `actionScreen` |
| `card` | `cardScreen` |
| `checkpoint` | `checkpointScreen` |
| `info` | `infoScreen` |

Form submissions (signals) from anyone else are refused with HTTP 403 in `startup()`.

## AdministrationPresenter – `/administration/…` (organizers only, selected year)

Visitors who are not logged in as the organizer team get the view `forbidden` (message with a
login link); their form submissions are refused with HTTP 403.

| View | Menu item | Content |
|---|---|---|
| `default` | Aktuality | add news (`UpdatesForm`) |
| `teamCard` | Karty týmů | `TeamCard` – one team's passage, finalize |
| `checkpointCard` | Stanoviště | `CheckpointCard` – arrivals of all teams to a checkpoint |
| `ciphers` | Šifry | edit cipher of a checkpoint incl. file uploads |
| `payments` | Platby týmů | payment state of all registered teams |
| `teamMessage` | Zpráva | broadcast message to Palainfo (`TeamMessage`) |
| `discussion` | Diskuse (vše) | all posts, reply into any thread |
| `chat` | Org chat | organizers' thread |
| `paymentMail` | Neplatiči | e-mails of unpaid playing teams (for copy & paste) |
| `teamMail` | Maily týmů | e-mails of all playing teams |
| `teamTable` | Tabulka týmů | all team data incl. variable symbols; `?orderByRegistration=1` |
| `results` | Výsledky | standings, button "publish" (after the game; removes hints of teams that did not reach the next checkpoint first) |
| `reports` | Reportáže | add a report link |
| `year` | Ročníky | edit the selected edition, `?createNew=1` creates a new one (`YearForm`) |
| `whereIsWho` | Kde je kdo | last checkpoint of every playing team (also teams that have not started) with arrival, departure and whether it finished or quit (and when); counts of teams on the route, finished, quit, not started |

## Error presenters

- `ErrorPresenter` (implements `IPresenter`) – forwards `BadRequestException` to
  `Error4xxPresenter`, logs other exceptions and renders `templates/Error/500.phtml`.
- `Error4xxPresenter` – `#[Requires(forward: true)]`; template `Error/<code>.latte` or
  `Error/4xx.latte`.
