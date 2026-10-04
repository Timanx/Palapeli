<?php

declare(strict_types=1);

namespace App\Components\YearForm;

use App\Components\BaseControl;
use App\Models\YearsModel;
use Nette\Application\UI\Form;


/**
 * Administration: editing the selected game edition or creating a new one.
 */
final class YearForm extends BaseControl
{
	private const DateTimeFormat = 'Y-m-d H:i:s';
	private const TimeFormat = 'H:i';

	private bool $createNew = false;


	public function __construct(
		private readonly YearsModel $yearsModel,
	) {
	}


	/**
	 * Switches the form to creating a new edition (otherwise the selected one is edited).
	 */
	public function setCreateNew(bool $createNew = true): static
	{
		$this->createNew = $createNew;
		return $this;
	}


	public function render(): void
	{
		$this->renderTemplate(__DIR__ . '/year.latte', [
			'selectedYear' => $this->year,
			'createNew' => $this->createNew,
		]);
	}


	protected function createComponentYearForm(): Form
	{
		$form = new Form;
		$form->addHidden('is_new', $this->createNew ? '1' : '');

		$form->addInteger('year', 'Ročník:')
			->addRule($form::Min, 'Hodnota ročníku musí být alespoň 1.', 1)
			->setRequired();
		$form->addDateTime('game_start', 'Čas začátku:')->setFormat(self::DateTimeFormat);
		$form->addDateTime('game_end', 'Čas konce:')->setFormat(self::DateTimeFormat);
		$form->addText('word_numbering', 'Ročník slovně (např. „první“):');
		$form->addDateTime('registration_start', 'Začátek registrace:')->setFormat(self::DateTimeFormat);
		$form->addDateTime('registration_end', 'Konec registrace:')->setFormat(self::DateTimeFormat);
		$form->addInteger('checkpoint_count', 'Počet stanovišť:')
			->setRequired()
			->addRule($form::Min, 'Počet stanovišť musí být alespoň 0.', 0);
		$form->addInteger('entry_fee', 'Startovné (v Kč):');
		$form->addText('entry_fee_account', 'Účet pro platbu startovného:');
		$form->addDateTime('entry_fee_deadline', 'Deadline zaplacení startovného:')->setFormat(self::DateTimeFormat);
		$form->addDateTime('entry_fee_return_deadline', 'Vrácení startovného při zrušení účasti do:')->setFormat(self::DateTimeFormat);
		$form->addDateTime('last_info_time', 'Čas rozeslání posledních informací:')->setFormat(self::DateTimeFormat);
		$form->addInteger('team_limit', 'Limit počtu týmů:');

		$form->addCheckbox('results_public', 'Výsledky publikované:');
		$form->addCheckbox('show_tester_notification', 'Zobrazit notifikaci o hledání testerů:');
		$form->addCheckbox('is_current', 'Je aktuální:');
		$form->addCheckbox('has_finish_cipher', 'Má cílovou šifru:');
		$form->addCheckbox('hint_for_start_exists', 'Má nápovědu na startovní šifru:');

		$form->addText('afterparty_location', 'Místo konání afterparty:');
		$form->addTime('afterparty_time', 'Začátek afterparty:')->setFormat(self::TimeFormat);
		$form->addText('finish_location', 'Místo cíle:');
		$form->addTime('finish_open_time', 'Otevření cíle:')->setFormat(self::TimeFormat);

		$form->addSubmit('send', 'ULOŽIT ROČNÍK');

		if (!$this->createNew && ($yearData = $this->yearsModel->getYearData($this->year))) {
			$defaults = [];
			foreach ($yearData as $column => $value) {
				// TIME columns are returned as DateInterval
				$defaults[$column] = $value instanceof \DateInterval ? $value->format('%H:%I') : $value;
			}

			$form->setDefaults($defaults);
		}

		$form->onSuccess[] = $this->saveYear(...);
		return $form;
	}


	/**
	 * @param array<string, mixed> $values
	 */
	private function saveYear(Form $form, array $values): void
	{
		$isNew = (bool) $values['is_new'];
		$gameStart = $values['game_start'];

		$values['calendar_year'] = $gameStart === null ? null : (int) substr($gameStart, 0, 4);
		$values['date'] = $gameStart === null ? null : substr($gameStart, 0, 10);
		foreach (['entry_fee_account', 'afterparty_location', 'finish_location'] as $column) {
			$values[$column] = $values[$column] === '' ? null : $values[$column];
		}

		if ($isNew) {
			$this->yearsModel->addYear($values);
			$this->flashMessage('Ročník byl úspěšně vložen.', 'success');
		} else {
			$this->yearsModel->editYear($values);
			$this->flashMessage('Ročník byl úspěšně upraven.', 'success');
		}

		$this->getPresenter()->redirect('this');
	}
}
