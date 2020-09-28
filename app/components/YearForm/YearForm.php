<?php
use App\Models\UpdatesModel;
use App\Models\YearsModel;
use Nette\Application\UI;

class YearForm extends BaseControl
{
    /** @var YearsModel */
    private $yearsModel;

    private $createNew = false;

    public function __construct(YearsModel $yearsModel)
    {
        parent::__construct();
        $this->yearsModel = $yearsModel;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/year.latte');
        $this->template->selectedYear = $this->year;
        $this->template->createNew = $this->createNew;
        $this->template->render();
    }

    public function createComponentYearForm()
    {
        $form = new UI\Form;

        $form->addHidden('is_new', $this->createNew);

        $form->addText('year', 'Ročník:')
            ->setType('number')
            ->addRule(UI\Form::MIN, 'Hodnota ročníku musí být alespoň 1.', 1)
            ->setRequired();
        $form->addText('game_start', 'Čas začátku:')
            ->setType('datetime-local');

        $form->addText('game_end', 'Čas konce:')
            ->setType('datetime-local');

        $form->addText('word_numbering', 'Ročník slovně (např. „první“):');

        $form->addText('registration_start', 'Začátek registrace:')
            ->setType('datetime-local');

        $form->addText('registration_end', 'Konec registrace:')
            ->setType('datetime-local');

        $form->addText('checkpoint_count', 'Počet stanovišť:')
            ->setType('number')
            ->setRequired(true)
            ->addRule(UI\Form::MIN, 'Počet stanovišť musí být alespoň 0.', 0);

        $form->addText('entry_fee', 'Startovné (v Kč):')
            ->setType('number');

        $form->addText('entry_fee_account', 'Účet pro platbu startovného:');

        $form->addText('entry_fee_deadline', 'Deadline zaplacení startovného:')
            ->setType('datetime-local');

        $form->addText('entry_fee_return_deadline', 'Vrácení startovného při zrušení účasti do:')
            ->setType('datetime-local');

        $form->addText('last_info_time', 'Čas rozeslání posledních informací:')
            ->setType('datetime-local');

        $form->addText('team_limit', 'Limit počtu týmů:')
            ->setType('number');

        $form->addCheckbox('results_public', 'Výsledky publikované:');
        $form->addCheckbox('show_tester_notification', 'Zobrazit notifikaci o hledání testerů:');
        $form->addCheckbox('is_current', 'Je aktuální:');
        $form->addCheckbox('has_finish_cipher', 'Má cílovou šifru:');
        $form->addCheckbox('hint_for_start_exists', 'Má nápovědu na startovní šifru:');

        $form->addText('afterparty_location', 'Místo konání afterparty:');
        $form->addText('afterparty_time', 'Začátek afterparty:')->setType('time');

        $form->addText('finish_location', 'Místo cíle:');
        $form->addText('finish_open_time', 'Otevření cíle:')->setType('time');

        $form->addSubmit('send', 'ULOŽIT ROČNÍK');

        if (!$this->createNew) {
            $this->yearsModel->setYear($this->year);
            $yearData = $this->yearsModel->getYearData();

            if ($yearData) {
                $defaultValues = [];

                foreach ($yearData as $key => $value) {
                    if ($value instanceof \Nette\Utils\DateTime) {
                        $defaultValues[$key] = $value->format('Y-m-d\TH:i');
                    } elseif ($value instanceof DateInterval) {
                        $defaultValues[$key] = $value->format('%H:%I');
                    } else {
                        $defaultValues[$key] = $value;
                    }
                }

                $form->setDefaults($defaultValues);
            }
        }

        $form->onSuccess[] = [$this, 'saveYear'];
        return $form;
    }

    public function saveYear(UI\Form $form, array $values)
    {
        $isNew = $values['is_new'];

        $values['date'] = substr($values['game_start'],0, 4);
        $values['calendar_year'] = substr($values['game_start'],0, 10);

            if ($isNew) {
                $this->yearsModel->addYear($values);
                $this->flashMessage('Ročník byl úspěšně vložen.', 'success');
            } else {
                $this->yearsModel->editYear($values);
                $this->flashMessage('Ročník byl úspěšně upraven.', 'success');
            }

        $this->presenter->redirect('this');
    }

    public function createNew(): void
    {
        $this->createNew = true;
    }
}
