# UI components

Components live in `app/components/<Name>/` – class `<Name>.php`, factory interface
`<Name>Factory.php` (implemented by Nette DI, registered in `services.neon`) and a Latte template.
All extend `App\Components\BaseControl`.

## BaseControl

- `public protected(set) int $year` – edition the component works with (PHP 8.4 asymmetric
  visibility), set by `setYear()`.
- `public protected(set) ?int $teamId` – logged-in team, set by `setTeamId()`.
- `requireTeamId()` – returns the team ID or throws `ForbiddenRequestException` (403).
- `renderTemplate($file, $params)` – renders a template with the given variables.

Presenters configure components when creating them, e.g.

```php
protected function createComponentActionScreen(): ActionScreen
{
	return $this->actionScreenFactory->create()
		->setTeamId($this->teamId)
		->setYear($this->currentYear);
}
```

Flash messages of a component are rendered by its own template (`$flashes`).

## Palainfo screens (used by `PalaInfoPresenter`, current edition)

### ActionScreen – "AKCE"

The main game screen, see [game-logic.md](game-logic.md#palainfo-flow-component-actionscreen).

| Form | Purpose |
|---|---|
| `codeInput` | checkpoint code or password solution |
| `exitTimeInput`, `exitTimeNow` | departure time / departure now |
| `askForDead` | total hint (confirmed by the checkpoint code) |
| `askForEnd` | quit the game (confirmed by `EndCode`) |

The specification ("upřesnítko") of the checkpoint the team is heading to is shown above the forms.
`setDefaultScreen(DeadScreen|EndScreen)` opens a confirmation screen after the page loads.
`hasReachedFinish()` (static) tells whether a team that ended the game completed it.

### CardScreen – "KARTA"

Read-only team card (disabled inputs) with closing times and specifications of checkpoints.

### CheckpointScreen – "PŘÍCHODY"

Order of arrivals to the checkpoint the team is on; the team's own row is highlighted.

### InfoScreen – "INFO"

The team's log and broadcast messages, newest first.

`CardScreen` and `CheckpointScreen` use the trait `EndgameMessages`, which adds the
"you finished / you quit, finish location, afterparty" messages.

## Administration components (used by `AdministrationPresenter`, selected edition)

### TeamCard

Select a team (grouped into "Nevyplněné/Vyplněné týmy"), show its card, finalize it. The selected
team is the component parameter `team` (URL `?teamCard-team=383`). Saving the card is disabled
unless `cardEditing: true` (constructor argument `$editingEnabled`).

### CheckpointCard

Select a checkpoint (optionally ordered by arrival to the previous one) and show arrivals of all
teams; teams that did not visit the previous checkpoint are highlighted. Uses presenter parameters
`checkpoint` and `previous`. Saving is controlled by `cardEditing` as well.

### TeamMessage

Broadcast message with a log type to all teams (Palainfo INFO screen).

### UpdatesForm

Add news to an edition (date, edition, text; new lines become `<br>`).

### YearForm

Edit the selected edition or create a new one (`setCreateNew()`). Date-time inputs use
`addDateTime()`/`addTime()` and return strings in database format; `calendar_year` and `date`
are derived from the game start.

## DiscussionControl

Discussion with a form for new posts. Threads:

| Thread | Where |
|---|---|
| `DiscussionControl::MainThread` (`main`) | Diskuse |
| `cipherThread($year, $checkpoint)` (`sifry_<year>_<checkpoint>`) | below each cipher |
| `ChatThread` (`chat`) | organizers' chat |
| `AnyThread` (`any`) | administration – all posts, the author picks the thread |

Anonymous visitors must answer the anti-spam question. HTML is stripped from posts except for
organizers; posts are displayed unescaped.

## SVG templates

`components/SVG/*.svg` are icons included from templates (`{include ../../../components/SVG/pay_ok.svg, dim => '50px'}`);
`.cdr` files in `components/SVG/Corel` and `cdr/` are CorelDRAW sources.
