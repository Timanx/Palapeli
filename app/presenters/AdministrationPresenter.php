<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Http\FileUpload;


class AdministrationPresenter extends BasePresenter
{
    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Přidání aktuality');
    }

    public function renderTeamCard($team = null)
    {
        parent::render();
        $this->prepareHeading('Karta týmu');

        $this->template->checkpointCount = $this->database->query('
            SELECT checkpoint_count
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetch()->checkpoint_count;

        $this->template->teamName = $this->database->query('
            SELECT name FROM teams WHERE id = ?
        ', $team)->fetchField('name');

        $this->template->team = $team;
    }

    public function renderCheckpointCard($checkpoint = null, $previous = null)
    {
        parent::render();
        $this->prepareHeading('Karta stanoviště');

        $this->template->teamsCount = $this->database->query('
            SELECT COUNT(team_id) AS teams_count
            FROM teamsyear
            WHERE year = ?
        ', $this->selectedYear)->fetch()->teams_count;

        $this->template->checkpointCount = $this->database->query('
                SELECT checkpoint_count
                FROM years
                WHERE year = ?
            ', $this->selectedYear)->fetch()->checkpoint_count;

        $this->template->checkpoint = $checkpoint;
    }

    public function renderCiphers($checkpoint = null)
    {
        parent::render();
        $this->prepareHeading('Vkládání šifer');

        $this->template->checkpointCount = $this->database->query('
                SELECT checkpoint_count
                FROM years
                WHERE year = ?
            ', $this->selectedYear)->fetch()->checkpoint_count;

        $this->template->checkpoint = $checkpoint;
    }

    public function renderDiscussion()
    {
        parent::render();
        $this->prepareHeading('Kompletní diskuse');
    }

    public function renderChat()
    {
        parent::render();
        $this->prepareHeading('Organizátorský chat');
    }

    public function renderPayments()
    {
        parent::render();
        $this->prepareHeading('Platba startovného');
    }

    public function renderPaymentMail()
    {
        parent::render();
        $this->prepareHeading('E-maily týmů s nezaplaceným startovným');

        $this->template->emails = $emails =  $this->database->query('
                SELECT CASE WHEN teams.email2 IS NULL OR teams.email2 = \'\' THEN email1 ELSE CONCAT(email1, \', \', email2) END AS email
                FROM teams
                LEFT JOIN teamsyear ON teamsyear.team_id = teams.id
                WHERE year = ? AND paid = ?
                ORDER BY registered
                LIMIT ?
            ', $this->selectedYear, self::PAY_NOK, (self::TEAM_LIMIT > 0 ? self::TEAM_LIMIT : PHP_INT_MAX))->fetchAll();
    }

    protected function createComponentDiscussion() {
        return new \DiscussionControl($this->database, $this->session->getSection('team')->teamId, $this->session->getSection('team')->teamName, \DiscussionControl::ANY_THREAD);
    }

    protected function createComponentChat() {
        return new \DiscussionControl($this->database, $this->session->getSection('team')->teamId, $this->session->getSection('team')->teamName, \DiscussionControl::CHAT_THREAD);
    }

    public function createComponentNewUpdateForm()
    {
        $form = new UI\Form;
        $form->addTextArea('message', 'Text aktuality:', null, 5);
        $form->addText('date', 'Datum:')
            ->setType('date')
            ->setDefaultValue(date('Y-m-d', time()))
            ->setRequired();
        $form->addText('year', 'Ročník:')
            ->setType('number')
            ->setDefaultValue(self::CURRENT_YEAR)
            ->addRule(UI\Form::MIN, 'Hodnota ročníku musí být alespoň 1.', 1)
            ->setRequired();
        $form->addSubmit('send', 'PŘIDAT AKTUALITU');
        $form->onSuccess[] = [$this, 'newUpdateFormSucceeded'];
        return $form;
    }

    public function newUpdateFormSucceeded(UI\Form $form, array $values)
    {

        $this->database->query('
            INSERT INTO updates (date, year, message)
              VALUES (?, ?, ?)

        ', $values['date'], $values['year'], nl2br($values['message']));


        $this->flashMessage('Aktualita byla úspěšně vložena.', 'success');
        $this->redirect('this');
    }

    public function createComponentSelectTeamForm()
    {
        $teamId = (isset($_GET['team']) ? $_GET['team'] : null);
        $this->getYearData();
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
        $select = $form->addSelect('teams', null, $options, 1)->setPrompt('Vyberte tým')->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'teamSelected'];
        return $form;
    }

    public function teamSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['team' => $values['teams']]);
    }

    public function createComponentTeamCardForm()
    {
        $teamId = $_GET['team'];
        $this->getYearData();
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
            $checkpoint->addText('entryTime', ($i == 0 ? 'Začátek hry:' : ($i == $checkpointCount-1 ? 'Příchod do cíle:' : 'Příchod na ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue((isset($results[$i]) && isset($results[$i]['entry_time']) ? $results[$i]['entry_time'] : ($i == 0 && isset($gameStart) ? $gameStart : '--:--')));
            $checkpoint->addText('exitTime', ($i == 0 ? 'Odchod ze startu:' : ($i == $checkpointCount-1 ? 'Vyřešení cílového hesla:' : 'Odchod z ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue((isset($results[$i]) && isset($results[$i]['exit_time']) ? $results[$i]['exit_time'] : '--:--'));;
            if($i != $checkpointCount-1) {
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
        foreach($values as $number => $checkpoint) {
            $number = substr($number,10);

            if($checkpoint['entryTime'] != '' || (isset($checkpoint['usedHint']) && $checkpoint['usedHint'])) {

                $this->database->query('
                INSERT INTO results (team_id, year, checkpoint_number, entry_time, exit_time, used_hint) VALUES
                (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE entry_time = ?, exit_time = ?, used_hint = ?
            ', $teamId, $this->selectedYear, $number, ($checkpoint['entryTime'] == '' ? NULL : $checkpoint['entryTime']), ($checkpoint['exitTime'] == '' ? NULL : $checkpoint['exitTime']), (isset($checkpoint['usedHint']) && $checkpoint['usedHint'] ? 1 : 0), ($checkpoint['entryTime'] == '' ? NULL : $checkpoint['entryTime']), ($checkpoint['exitTime'] == '' ? NULL : $checkpoint['exitTime']), (isset($checkpoint['usedHint']) && $checkpoint['usedHint'] ? 1 : 0));
            }

            //Handle finish
            if ($number == count($values) - 1 && $checkpoint['exitTime'] != '') {
                $this->database->query('
                INSERT INTO results (team_id, year, checkpoint_number, entry_time) VALUES
                (?, ?, ?, ?) ON DUPLICATE KEY UPDATE entry_time = ?
            ', $teamId, $this->selectedYear, ((int)$number + 1), $checkpoint['exitTime'],$checkpoint['exitTime']);
            }
        }

        $this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }


    public function createComponentSelectCheckpointForm()
    {
        $checkpoint = (isset($_GET['checkpoint']) ? $_GET['checkpoint'] : null);
        $checked = (isset($_GET['previous']) ? $_GET['previous'] : null);
        $this->getYearData();

        $checkpointCount = $this->database->query('
                SELECT checkpoint_count
                FROM years
                WHERE year = ?
            ', $this->selectedYear)->fetch()->checkpoint_count;

        $options = [];
        for ($i = 0; $i <= $checkpointCount; $i++){
            $options[$i] = ($i == $checkpointCount - 1 ? 'Příchod do cíle' : ($i == $checkpointCount ? 'Vyřešení cílového hesla' : ($i == 0 ? 'Start' : $i . '. stanoviště')));
        }

        array_unshift($options, 'Vyberte stanoviště');

        $form = new UI\Form;

        $form->addCheckbox('previous', 'Řadit týmy podle příchodu na předchozí stanoviště')->setAttribute('onchange', 'this.form.submit()');
        $form->addSelect('checkpoint', '', $options, 1)->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'checkpointSelected'];
        return $form;
    }

    public function checkpointSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['checkpoint' => ($values['checkpoint'] == 0 ? 0 : $values['checkpoint'] - 1), 'previous' => $values['previous']]);
    }

    public function createComponentSelectOnlyCheckpointForm()
    {
        $checkpoint = (isset($_GET['checkpoint']) ? $_GET['checkpoint'] : null);
        $this->getYearData();

        $checkpointCount = $this->database->query('
                SELECT checkpoint_count
                FROM years
                WHERE year = ?
            ', $this->selectedYear)->fetch()->checkpoint_count;

        $options = [];
        for ($i = 0; $i < $checkpointCount; $i++){
            $options[$i] = ($i == $checkpointCount - 1 ? 'Cíl' : ($i == 0 ? 'Start' : $i . '. stanoviště'));
        }
        array_unshift($options, 'Vyberte stanoviště');


        $form = new UI\Form;
        $form->addSelect('checkpoint', '', $options, 1)->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'onlyCheckpointSelected'];
        return $form;
    }

    public function onlyCheckpointSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['checkpoint' => ($values['checkpoint'] == 0 ? 0 : $values['checkpoint'] - 1)]);
    }

    public function createComponentCheckpointCardForm()
    {
        $checkpoint = $_GET['checkpoint'];
        $previous = $_GET['previous'];
        $this->getYearData();
        if($previous) {
            $data = $this->database->query('
            SELECT TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time, teams.id, teams.name, previous_results.entry_time IS NOT NULL AS visited_previous
            FROM teamsyear
            LEFT JOIN results ON teamsyear.year = results.year AND teamsyear.team_id = results.team_id AND results.checkpoint_number = ?
            LEFT JOIN teams ON teamsyear.team_id = teams.id
            LEFT JOIN results AS previous_results ON previous_results.year = ? AND previous_results.checkpoint_number = ? AND previous_results.team_id = teamsyear.team_id
            WHERE teamsyear.year = ?
            ORDER BY results.entry_time ASC, previous_results.entry_time IS NOT NULL DESC, previous_results.entry_time ASC, LTRIM(name) COLLATE utf8_czech_ci ASC
        ', $checkpoint, $this->selectedYear, ($checkpoint == 0 ? 0 : $checkpoint - 1), $this->selectedYear)->fetchAll();
        } else {
            $data = $this->database->query('
            SELECT TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time, teams.id, teams.name, previous_results.entry_time IS NOT NULL AS visited_previous
            FROM teamsyear
            LEFT JOIN results ON teamsyear.year = results.year AND teamsyear.team_id = results.team_id AND results.checkpoint_number = ?
            LEFT JOIN teams ON teamsyear.team_id = teams.id
            LEFT JOIN results AS previous_results ON previous_results.year = ? AND previous_results.checkpoint_number = ? AND previous_results.team_id = teamsyear.team_id
            WHERE teamsyear.year = ?
            ORDER BY results.entry_time ASC, LTRIM(name) COLLATE utf8_czech_ci ASC
        ', $checkpoint, $this->selectedYear, ($checkpoint == 0 ? 0 : $checkpoint - 1), $this->selectedYear)->fetchAll();
        }

        $form = new UI\Form;

        for ($i = 0; $i < count($data); $i++) {
            $teamContainer = $form->addContainer('team' . $i);
            $teamName = $teamContainer->addText('entryTime', $data[$i]['name'])->setType('time')->setDefaultValue((isset($data[$i]['entry_time']) ? $data[$i]['entry_time'] : '--:--'));
            if($checkpoint > 1 && !$data[$i]['visited_previous']) {
                $teamName->getLabelPrototype()->addAttributes(['class' => 'dead', 'title' => 'Tým nemá vyplněný příchod na předchozím stanovišti']);
            }
            $teamContainer->addHidden('teamId', $data[$i]['id']);
            $teamContainer->addButton('currentTime', 'Teď')->setAttribute('onclick', 'submitCurrentTime(' . $i . ', this.form)');
            $teamContainer->addButton('inputtedTime', 'Zadáno')->setAttribute('onclick', 'this.form.submit()');
        }
        $form->addSubmit('send', 'ODESLAT KARTU STANOVIŠTĚ');
        $form->onSuccess[] = [$this, 'checkpointCardFormSucceeded'];
        return $form;
    }

    public function checkpointCardFormSucceeded(UI\Form $form, array $values)
    {
        $checkpointCount = $this->database->query('
                SELECT checkpoint_count
                FROM years
                WHERE year = ?
            ', $this->selectedYear)->fetch()->checkpoint_count;


        $checkpoint = $_GET['checkpoint'];
        foreach($values as $team) {
            if($team['entryTime'] != '') {

                $this->database->query('
                    INSERT INTO results (team_id, year, checkpoint_number, entry_time) VALUES
                    (?, ?, ?, ?) ON DUPLICATE KEY UPDATE entry_time = ?
                ', $team['teamId'], $this->selectedYear, $checkpoint, $team['entryTime'], $team['entryTime']
                );

                if($checkpoint == $checkpointCount) {
                    $this->database->query('
                        INSERT INTO results (team_id, year, checkpoint_number, exit_time) VALUES
                        (?, ?, ?, ?) ON DUPLICATE KEY UPDATE exit_time = ?
                    ', $team['teamId'], $this->selectedYear, $checkpoint - 1, $team['entryTime'], $team['entryTime']
                    );
                }

            } else {
                $this->database->query('
                    UPDATE results SET entry_time = NULL WHERE team_id = ? AND year = ? AND results.checkpoint_number = ?
                ', $team['teamId'], $this->selectedYear, $checkpoint);
            }
        }

        $this->flashMessage('Údaje z karty stanoviště byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }


    public function createComponentCipherForm()
    {
        $checkpoint = $_GET['checkpoint'];
        $this->getYearData();
        $data = $this->database->query('
            SELECT ciphers.name, cipher_description, solution_description, CONCAT_WS(\'/\',cipher_image.path, cipher_image.name) AS cipher_image, CONCAT(solution_image.path, solution_image.name) AS solution_image, ciphers.solution
            FROM ciphers
            LEFT JOIN files AS cipher_image ON cipher_image.id = ciphers.cipher_image_id
            LEFT JOIN files AS solution_image ON solution_image.id = ciphers.cipher_image_id
            WHERE year = ? AND checkpoint_number = ?
        ', $this->selectedYear, $checkpoint)->fetch();

        $form = new UI\Form;
        $form->elementPrototype->addAttributes(['enctype' => 'multipart/form-data']);

        $form->addText('name', 'Název šifry', null, 255)->setDefaultValue(isset($data->name) ? $data->name : null);
        $form->addTextArea('cipher_description', 'Popis zadání')->setDefaultValue(isset($data->cipher_description) ? $data->cipher_description : null);
        $form->addTextArea('solution_description', 'Popis řešení')->setDefaultValue(isset($data->solution_description) ? $data->solution_description : null);
        $form->addText('solution', 'Řešení', null, 1023)->setDefaultValue(isset($data->solution) ? $data->solution : null);
        $form->addUpload('cipher_image', 'Obrázek šifry');
        $form->addUpload('solution_image', 'Obrázek řešení');
        $form->addUpload('pdf_file', 'PDF soubor se šifrou');
        $form->addSubmit('send', 'VLOŽIT ŠIFRU');
        $form->onSuccess[] = [$this, 'cipherFormSucceeded'];
        return $form;
    }

    private function uploadFile(FileUpload $file, $path,  $target_name) {
        if($file->getError() == UPLOAD_ERR_OK) {
            $target_file = $path . $target_name;

            $tmpFile = $file->getTemporaryFile();

            move_uploaded_file($tmpFile, $target_file);

            $this->database->beginTransaction();

            $id = $this->database->query('
                SELECT id
                FROM files
                WHERE path = ? AND name = ?
            ', $path, $target_name)->fetchField();

            if(!$id) {
                $this->database->query('
                INSERT INTO files (path, name) VALUES (?, ?)
                ', $path, $target_name
                );

                $id = $this->database->getInsertId();
            }

            $this->database->commit();

            return $id;
        }

        return null;
    }

    public function cipherFormSucceeded(UI\Form $form, array $values)
    {
        $checkpoint = $_GET['checkpoint'];

        if(!file_exists('cipher_images')) {
            mkdir('cipher_images');
        }

        if(!file_exists('cipher_images/' . $this->selectedYear)) {
            mkdir('cipher_images/' . $this->selectedYear);
        }

        $target_dir = 'cipher_images/' . $this->selectedYear . '/' . $checkpoint . '/';
        if(!file_exists($target_dir)) {
            mkdir($target_dir);
        }

        $checkpointString = $checkpoint;
        if($checkpoint < 10) {
            $checkpointString = '0' . $checkpoint;
        }

        $pattern = '~.*(\.[^\.]*)~';
        $replacement = 's' . $checkpointString . '$1';

        $cipher_image_id = $this->uploadFile($values['cipher_image'], $target_dir, preg_replace($pattern, 'img_' . $replacement, $values['cipher_image']->getName()));
        $solution_image_id = $this->uploadFile($values['solution_image'], $target_dir, preg_replace($pattern, 'sol_' . $replacement, $values['solution_image']->getName()));
        $pdf_file_id = $this->uploadFile($values['pdf_file'], $target_dir, preg_replace($pattern, 'pdf_' . $replacement, $values['pdf_file']->getName()));

        $this->database->query('
            INSERT INTO ciphers (year, checkpoint_number, name, cipher_description,  solution_description, solution) VALUES
            (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, cipher_description = ?, solution_description = ?, solution = ?
        ', $this->selectedYear, $checkpoint, $values['name'], $values['cipher_description'], $values['solution_description'],
              $values ['solution'],
                $values['name'], $values['cipher_description'], $values['solution_description'],
            $values ['solution']
        );

        if($values['solution_image']->getError() == UPLOAD_ERR_OK) {
            $this->database->query('
                UPDATE ciphers
                SET solution_image_id = ?
                WHERE year = ? AND checkpoint_number = ?
            ', $solution_image_id, $this->selectedYear, $checkpoint);
        }

        if($values['cipher_image']->getError() == UPLOAD_ERR_OK) {
            $this->database->query('
                UPDATE ciphers
                SET cipher_image_id = ?
                WHERE year = ? AND checkpoint_number = ?
            ', $cipher_image_id, $this->selectedYear, $checkpoint);
        }

        if($values['pdf_file']->getError() == UPLOAD_ERR_OK) {
            $this->database->query('
                UPDATE ciphers
                SET pdf_file_id = ?
                WHERE year = ? AND checkpoint_number = ?
            ', $pdf_file_id, $this->selectedYear, $checkpoint);
        }

        $this->flashMessage('Šifra byla úspěšně vložena.', 'success');
        $this->redirect('this');
    }

    public function createComponentPayments()
    {
        $this->getYearData();
        $teams = $this->database->query('
            SELECT id, name, paid
            FROM teams
            LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
            WHERE teamsyear.year = ?
            ORDER BY LTRIM(name) COLLATE utf8_czech_ci
        ', $this->selectedYear)->fetchAll();

        $form = new UI\Form;
        foreach ($teams as $team) {
            $form->addRadioList($team->id, $team->name, [0 => 'Nezaplaceno', 1 => 'Zaplaceno', 2 => 'Platba na startu'])->setDefaultValue($team->paid);
        }
        $form->addSubmit('send', 'UPRAVIT PLATBY');


        $form->onSuccess[] = [$this, 'editPayments'];
        return $form;
    }

    public function editPayments(UI\Form $form, array $values)
    {
        foreach($values as $team_id => $paid) {
            $this->database->query('
                UPDATE teamsyear SET paid = ?
                WHERE team_id = ? AND year = ?
            ', $paid, $team_id, $this->selectedYear);
        }

        $this->flashMessage('Platby byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }

}