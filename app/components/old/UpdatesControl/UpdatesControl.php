<?php
use Nette\Application\UI\Control;
use Nette\Application\UI;

class UpdatesControl extends Control
{
    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function render()
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/updates.latte');
        $template->render();
    }

    public function createComponentNewUpdateForm()
    {
        $form = new UI\Form;
        $form->addTextArea('message', 'Text aktuality:', NULL, 5);
        $form->addText('date', 'Datum:')
            ->setType('date')
            ->setDefaultValue(date('Y-m-d', time()))
            ->setRequired();
        $form->addText('year', 'Ročník:')
            ->setType('number')
            ->setDefaultValue(\App\Presenters\BasePresenter::CURRENT_YEAR)
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
}