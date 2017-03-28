<?php
use Nette\Application\UI\Control;
use Nette\Application\UI;

class TeamCardControl extends BaseControl
{
    private $team;

    public function __construct(Nette\Database\Context $database, \Nette\Http\Session $session)
    {
        parent::__construct($database, $session);
    }

    public function setYear($year)
    {
        $this->selectedYear = $year;
    }

    public function setCalendarYear($year)
    {
        $this->selectedCalendarYear = $year;
    }

    public function render()
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/teamCard.latte');

        $template->checkpointCount = $this->database->query('
            SELECT checkpoint_count
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetchField();

        $this->team = (isset($_GET['teamCard-team']) ? $_GET['teamCard-team'] : NULL);

        $template->teamName = $this->database->query('
            SELECT name FROM teams WHERE id = ?
        ', $this->team)->fetchField('name');

        $template->team = $this->team;
        $template->selectedYear = $this->selectedYear;
        $template->selectedCalendarYear = $this->selectedCalendarYear;
        $template->render();
    }

    public function createComponentSelectTeamForm()
    {
        $teams = $this->database->query('
            SELECT name, teams.id, (MAX(results.exit_time) IS NOT NULL AND MAX(results.exit_time) != \'00:00\' || EXISTS (SELECT 1 FROM results r WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL)) AS team_filled
            FROM teams
            LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
            LEFT JOIN results ON results.year = ? AND teams.id = results.team_id
            WHERE teamsyear.year = ?
            GROUP BY name, teams.id
            ORDER BY LTRIM(name) COLLATE utf8_czech_ci
        ', $this->selectedYear, $this->selectedYear)->fetchAll();

        $options = ['Nevyplněné týmy' => [], 'Vyplněné týmy' => []];
        foreach ($teams as $team) {
            $options[(!$team->team_filled ? 'Nevyplněné týmy' : 'Vyplněné týmy')][$team->id] = $team->name;
        }

        $form = new UI\Form;
        $form->addSelect('teams', null, $options, 1)->setPrompt('Vyberte tým')->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'teamSelected'];
        return $form;
    }

    public function teamSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['team' => $values['teams']]);
    }

    public function createComponentTeamCardForm()
    {
        $teamId = $_GET['teamCard-team'];
        $results = $this->database->query('
                SELECT TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time,TIME_FORMAT(results.exit_time, \'%H:%i\') AS exit_time, used_hint, checkpoint_number
                FROM results
                WHERE team_id = ? AND year = ?
                ORDER BY checkpoint_number
            ', $teamId, $this->selectedYear)->fetchAssoc('checkpoint_number');

        $gameStart = $this->database->query('
            SELECT TIME_FORMAT(game_start, \'%H:%i\') AS game_start
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetch()->game_start;

        $checkpointCount = $this->database->query('
                SELECT checkpoint_count
                FROM years
                WHERE year = ?
            ', $this->selectedYear)->fetch()->checkpoint_count;

        $maxCheckpoint = (count($results) ? max(array_keys($results)) : $checkpointCount);

        $form = new UI\Form;

        for ($i = 0; $i < $checkpointCount; $i++) {
            $checkpoint = $form->addContainer('checkpoint' . $i);
            $checkpoint->addText('entryTime', ($i == 0 ? 'Začátek hry:' : ($i == $checkpointCount - 1 ? 'Příchod do cíle:' : 'Příchod na ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue((isset($results[$i]) && isset($results[$i]['entry_time']) ? $results[$i]['entry_time'] : ($i == 0 && isset($gameStart) ? $gameStart : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE)));
            $checkpoint->addText('exitTime', ($i == 0 ? 'Odchod ze startu:' : ($i == $checkpointCount - 1 ? 'Vyřešení cílového hesla:' : 'Odchod z ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue((isset($results[$i]) && isset($results[$i]['exit_time']) ? $results[$i]['exit_time'] : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE));;
            if ($i != $checkpointCount - 1) {
                $checkpoint->addCheckbox('usedHint')->setDefaultValue((isset($results[$i]) && isset($results[$i]['used_hint']) ? $results[$i]['used_hint'] : 0))->setRequired(false);
            }

        }
        $form->addSubmit('send', 'ODESLAT KARTU TÝMU');
        $form->onSuccess[] = [$this, 'teamCardFormSucceeded'];
        return $form;
    }


    public function teamCardFormSucceeded(UI\Form $form, array $values)
    {
        $teamId = $_GET['team'];
        foreach ($values as $number => $checkpoint) {
            $number = substr($number, 10);

            if (isset($checkpoint['usedHint']) &&
                $checkpoint['usedHint']
                ||
                $checkpoint['exitTime'] != '' &&
                $checkpoint['exitTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE
                ||
                $checkpoint['entryTime'] != '' &&
                $checkpoint['entryTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE


            ) {
                if ($checkpoint['exitTime'] == \App\Presenters\BasePresenter::EMPTY_TIME_VALUE || strlen($checkpoint['exitTime']) == 0) {
                    $checkpoint['exitTime'] = NULL;
                }
                if ($checkpoint['entryTime'] == \App\Presenters\BasePresenter::EMPTY_TIME_VALUE || strlen($checkpoint['entryTime']) == 0) {
                    $checkpoint['entryTime'] = NULL;
                }

                $this->database->query('
                INSERT INTO results (team_id, year, checkpoint_number, entry_time, exit_time, used_hint) VALUES
                (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE entry_time = ?, exit_time = ?, used_hint = ?
            ', $teamId, $this->selectedYear, $number, (strlen($checkpoint['entryTime']) == 0 ? NULL : $checkpoint['entryTime']), (strlen($checkpoint['exitTime']) == 0 ? NULL : $checkpoint['exitTime']), (isset($checkpoint['usedHint']) && $checkpoint['usedHint'] ? 1 : 0), (strlen($checkpoint['entryTime']) == 0 ? NULL : $checkpoint['entryTime']), (strlen($checkpoint['exitTime']) == 0 ? NULL : $checkpoint['exitTime']), (isset($checkpoint['usedHint']) && $checkpoint['usedHint'] ? 1 : 0));
            }

            //Handle finish
            if ($number == count($values) - 1 && $checkpoint['exitTime'] != '' && $checkpoint['exitTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE) {
                $this->database->query('
                INSERT INTO results (team_id, year, checkpoint_number, entry_time) VALUES
                (?, ?, ?, ?) ON DUPLICATE KEY UPDATE entry_time = ?
            ', $teamId, $this->selectedYear, ((int)$number + 1), $checkpoint['exitTime'], $checkpoint['exitTime']);
            }
        }

        $this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }
}