<?php

declare(strict_types=1);

namespace App\Components\UpdatesForm;

use App\Components\BaseControl;
use App\Models\UpdatesModel;
use Nette\Application\UI\Form;


/**
 * Administration: adding news ("aktuality") to the Info section.
 */
final class UpdatesForm extends BaseControl
{
	public function __construct(
		private readonly UpdatesModel $updatesModel,
	) {
	}


	public function render(): void
	{
		$this->renderTemplate(__DIR__ . '/updates.latte');
	}


	protected function createComponentNewUpdateForm(): Form
	{
		$form = new Form;
		$form->addTextArea('message', 'Text aktuality:', null, 5);
		$form->addDate('date', 'Datum:')
			->setFormat('Y-m-d')
			->setDefaultValue(new \DateTimeImmutable)
			->setRequired();
		$form->addInteger('year', 'Ročník:')
			->setDefaultValue($this->year)
			->addRule($form::Min, 'Hodnota ročníku musí být alespoň 1.', 1)
			->setRequired();
		$form->addSubmit('send', 'PŘIDAT AKTUALITU');
		$form->onSuccess[] = $this->newUpdateFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array{message: string, date: string, year: int} $values
	 */
	private function newUpdateFormSucceeded(Form $form, array $values): void
	{
		$this->updatesModel->addUpdate($values['year'], $values['date'], $values['message']);

		$this->flashMessage('Aktualita byla úspěšně vložena.', 'success');
		$this->redirect('this');
	}
}
