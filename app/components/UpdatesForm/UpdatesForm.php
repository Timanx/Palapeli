<?php
use App\Models\UpdatesModel;
use Nette\Application\UI;

class UpdatesForm extends BaseControl
{
    /** @var UpdatesModel */
    private $updatesModel;

    public function __construct(UpdatesModel $updatesModel)
    {
        parent::__construct();
        $this->updatesModel = $updatesModel;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/updates.latte');
        $this->template->render();
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
            ->setDefaultValue($this->year)
            ->addRule(UI\Form::MIN, 'Hodnota ročníku musí být alespoň 1.', 1)
            ->setRequired();
        $form->addSubmit('send', 'PŘIDAT AKTUALITU');
        $form->onSuccess[] = [$this, 'newUpdateFormSucceeded'];
        return $form;
    }

    public function newUpdateFormSucceeded(UI\Form $form, array $values)
    {
        $this->updatesModel->setYear($values['year']);
        $this->updatesModel->addUpdate($values['date'], $values['message']);

        $this->flashMessage('Aktualita byla úspěšně vložena.', 'success');
        $this->presenter->redirect('this');
    }
}
