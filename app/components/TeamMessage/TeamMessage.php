<?php

declare(strict_types=1);

namespace App\Components\TeamMessage;

use App\Components\BaseControl;
use App\Models\LogModel;
use Nette\Application\UI\Form;


/**
 * Administration: broadcast message to all teams; it appears on the Palainfo INFO screen.
 */
final class TeamMessage extends BaseControl
{
	public function __construct(
		private readonly LogModel $logModel,
	) {
	}


	public function render(): void
	{
		$this->renderTemplate(__DIR__ . '/teamMessage.latte', [
			'selectedYear' => $this->year,
		]);
	}


	protected function createComponentTeamMessageForm(): Form
	{
		$form = new Form;
		$form->addSelect('logtype', 'Typ zprávy', $this->logModel->getLogTypes());
		$form->addTextArea('message', 'Zpráva');
		$form->addSubmit('send', 'ODESLAT ZPRÁVU TÝMŮM');
		$form->onSuccess[] = $this->teamMessageFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array{logtype: int, message: string} $values
	 */
	private function teamMessageFormSucceeded(Form $form, array $values): void
	{
		$this->logModel->log((int) $values['logtype'], null, null, $this->year, $values['message']);

		$this->flashMessage('Zpráva týmům byla úspěšně odeslána', 'success');
		$this->redirect('this');
	}
}
