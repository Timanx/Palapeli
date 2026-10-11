<?php

declare(strict_types=1);

namespace App\Components\YearForm;

use App\Components\BaseControl;
use App\Models\PaymentQrCodeGenerator;
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
			->setRequired()
			->setOption('description', 'Pořadové číslo ročníku (1. ročník = 2012). U existujícího ročníku neměňte – podle čísla se ročník ukládá.');
		$form->addDateTime('game_start', 'Čas začátku:')->setFormat(self::DateTimeFormat)
			->setOption('description', 'Od této chvíle mají týmy přístup do Palainfa (u klouzavého startu nejdřívější možný start). Z data se odvozuje kalendářní rok, a tím i variabilní symbol.');
		$form->addDateTime('game_end', 'Čas konce:')->setFormat(self::DateTimeFormat)
			->setOption('description', 'Potom už Palainfo nepřijímá kódy, hesla ani totálky – pro všechny týmy, i při klouzavém startu. Teprve po konci hry se na webu zobrazí šifry s řešeními a statistikami a lze zveřejnit výsledky.');
		$form->addText('word_numbering', 'Ročník slovně (např. „první“):')
			->setOption('description', 'Použije se na stránce reportáží.');
		$form->addDateTime('registration_start', 'Začátek registrace:')->setFormat(self::DateTimeFormat)
			->setOption('description', 'Od této chvíle se mohou týmy registrovat; dříve registrované týmy se zaregistrují samy přihlášením.');
		$form->addDateTime('registration_end', 'Konec registrace:')->setFormat(self::DateTimeFormat);
		$form->addInteger('checkpoint_count', 'Počet stanovišť:')
			->setRequired()
			->addRule($form::Min, 'Počet stanovišť musí být alespoň 0.', 0)
			->setOption('description', 'Včetně startu a cíle: start má číslo 0, cíl číslo „počet − 1“. Např. start + 10 stanovišť + cíl = 12. Cílové heslo se počítá jako další stanoviště navíc, do počtu se nezahrnuje.');
		$form->addInteger('entry_fee', 'Startovné (v Kč):')
			->setOption('description', 'Částka na stránce platby a v QR kódu.');
		$form->addText('entry_fee_account', 'Účet pro platbu startovného:')
			->setRequired(false)
			->addRule($form::Pattern, 'Účet zadejte ve tvaru [předčíslí-]číslo/kód banky, např. 123456789/0100.', PaymentQrCodeGenerator::AccountPattern)
			->setOption('description', 'Ve tvaru [předčíslí-]číslo/kód banky, např. 123456789/0100 nebo 19-2000145399/0800 (použije se i pro QR platbu).');
		$form->addDateTime('entry_fee_deadline', 'Deadline zaplacení startovného:')->setFormat(self::DateTimeFormat)
			->setOption('description', 'Zobrazí se na stránce platby a v pravidlech.');
		$form->addDateTime('entry_fee_return_deadline', 'Vrácení startovného při zrušení účasti do:')->setFormat(self::DateTimeFormat)
			->setOption('description', 'Zobrazí se na stránce platby, v pravidlech a týmu, který zaplatil a ruší účast. Zaplacené startovné se při zrušení účasti do tohoto termínu vrací na účet, ze kterého tým platil (e-mail o zrušení účasti organizátorům uvádí, zda vrátit). Prázdné = vrací se vždy.');
		$form->addDateTime('last_info_time', 'Čas rozeslání posledních informací:')->setFormat(self::DateTimeFormat)
			->setOption('description', 'Jen pro evidenci, web ho nikde nezobrazuje.');
		$form->addInteger('team_limit', 'Limit počtu týmů:')
			->setOption('description', 'Maximální počet hrajících týmů; další registrované týmy jsou náhradníci. Prázdné = bez limitu.');

		$form->addCheckbox('results_public', 'Výsledky publikované:')
			->setOption('description', 'Veřejně se výsledky zobrazí až po konci hry a po zaškrtnutí (organizátoři je vidí dřív). Před zveřejněním spusťte „Odstranit totálky týmů, které nedošly na další stanoviště“.');
		$form->addCheckbox('show_tester_notification', 'Zobrazit notifikaci o hledání testerů:')
			->setOption('description', 'Na webu se zobrazí výzva, že hledáme testery šifer.');
		$form->addCheckbox('is_current', 'Je aktuální:')
			->setOption('description', 'Ročník, se kterým pracuje registrace, Palainfo a úvodní stránka. Aktuální smí být jen jeden – zaškrtnutím se ostatním ročníkům příznak odebere.');
		$form->addCheckbox('has_finish_cipher', 'Má cílovou šifru:')
			->setOption('description', 'Zaškrtnuto: po zadání kódu cíle musí tým do Palainfa zadat cílové heslo („Řešení“ u cíle), teprve tím hru dokončí; vyřešení hesla se ve výsledcích počítá jako další stanoviště. Heslo může být řešením šifry vydané v cíli, nebo už poslední šifry. Nezaškrtnuto: tým dokončí hru zadáním kódu cíle.');
		$form->addCheckbox('hint_for_start_exists', 'Totálka na startovní šifru:')
			->setOption('description', 'Týmy si v Palainfu mohou vzít totálku i na startovní šifru; zobrazí se jim „Řešení v totálce“ startovní šifry. Na ostatní šifry kromě cíle je totálka vždy.');
		$form->addCheckbox('individual_start', 'Klouzavý start:')
			->setOption('description', 'Každý tým startuje, kdy chce – jeho čas začne běžet zadáním kódu startovní šifry. Výsledky se počítají od startu týmu. Konec hry a zavírací doby stanovišť platí pro všechny stejně.');

		$form->addText('afterparty_location', 'Místo konání afterparty:')
			->setOption('description', 'Zobrazí se v Palainfu týmům, které hru dokončily nebo ukončily, a všem po konci hry. Prázdné = afterparty se nezobrazí.');
		$form->addTime('afterparty_time', 'Začátek afterparty:')->setFormat(self::TimeFormat)
			->setOption('description', 'Prázdné = „dozvíte se v cíli“.');
		$form->addText('finish_location', 'Místo cíle:')
			->setOption('description', 'Zobrazí se v Palainfu týmům, které hru ukončily předčasně, a všem po konci hry.');
		$form->addTime('finish_open_time', 'Otevření cíle:')->setFormat(self::TimeFormat)
			->setOption('description', 'Zobrazí se spolu s místem cíle. Prázdné = 09:00.');

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
