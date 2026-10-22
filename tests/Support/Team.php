<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Components\ActionScreen\ActionScreen;
use Nette\Database\Connection;


/**
 * A team playing the game on a phone: every action opens the AKCE screen and uses the form on it,
 * as a player would. When the form is not on the screen, the action fails the test.
 */
final class Team
{
	public readonly Browser $phone;
	private ?Page $lastPage = null;


	public function __construct(
		public readonly Game $game,
		private readonly Connection $db,
		public readonly int $id,
		public readonly string $name,
	) {
		$this->phone = new Browser($db);
		$this->phone->logIn($id, $name);
	}


	/**
	 * Another phone of the same team, logged in separately.
	 */
	public function secondPhone(): self
	{
		return new self($this->game, $this->db, $this->id, $this->name);
	}


	public function akce(): Page
	{
		return $this->lastPage = $this->phone->open('/pala-info/');
	}


	public function card(): Page
	{
		return $this->phone->open('/pala-info/card');
	}


	public function arrivals(): Page
	{
		return $this->phone->open('/pala-info/checkpoint');
	}


	public function info(): Page
	{
		return $this->phone->open('/pala-info/info');
	}


	/**
	 * The page the team saw last (an outdated page when other phones acted meanwhile).
	 */
	public function lastPage(): Page
	{
		return $this->lastPage ?? throw new \LogicException('The team has not opened any page yet.');
	}


	/**
	 * Types into the main input of AKCE: a checkpoint code, a pickup code or a password.
	 */
	public function enterCode(string $code): Page
	{
		return $this->lastPage = $this->phone->submit($this->akce(), 'codeInput', ['code' => $code]);
	}


	/**
	 * Sets the departure from the current checkpoint: a typed time "H:i", "" = empty input, null = "TEĎ".
	 */
	public function setDeparture(?string $time = null): Page
	{
		$page = $this->akce();
		return $this->lastPage = $time === null
			? $this->phone->submit($page, 'exitTimeNow')
			: $this->phone->submit($page, 'exitTimeInput', ['exitTime' => $time]);
	}


	/**
	 * Takes the total hint, confirmed by the code of the current checkpoint.
	 */
	public function askForHint(string $code): Page
	{
		return $this->lastPage = $this->phone->submit($this->akce(), 'askForDead', ['code' => $code]);
	}


	public function quit(string $code = ActionScreen::EndCode): Page
	{
		return $this->lastPage = $this->phone->submit($this->akce(), 'askForEnd', ['code' => $code]);
	}


	/**
	 * Submits a form of the page the team saw last (e.g. a page left open on another phone).
	 * @param array<string, string> $values
	 */
	public function submitOutdated(Page $page, string $form, array $values = []): Page
	{
		return $this->lastPage = $this->phone->submit($page, $form, $values);
	}


	/**
	 * Submits a form of AKCE that the current screen does not offer (as from a very old or forged page).
	 * @param array<string, string> $values
	 */
	public function forge(string $form, array $values = []): Page
	{
		return $this->lastPage = $this->phone->forge($form, $values);
	}


	/**
	 * Enters the code of the checkpoint and gets the cipher: waits when needed and enters the pickup code.
	 */
	public function arriveAt(int $checkpoint): Page
	{
		$cipher = $this->game->cipher($checkpoint);
		$page = $this->enterCode((string) $cipher->code);
		if ($cipher->hasWait() && $cipher->pickupCode !== null) {
			$this->game->travel($cipher->waitMinutes);
			$page = $this->enterCode($cipher->pickupCode);
		}

		return $page;
	}


	/**
	 * Leaves the checkpoint the regular way: the password, or the departure now.
	 */
	public function solve(int $checkpoint): Page
	{
		$cipher = $this->game->cipher($checkpoint);
		$isPassword = $cipher->password || ($checkpoint === $this->game->getFinish() && $this->game->hasFinishCipher);
		return $isPassword
			? $this->enterCode((string) $cipher->solution)
			: $this->setDeparture();
	}


	/**
	 * Plays checkpoints from..to (inclusive): arrives at each and solves it (except the finish without a finish cipher).
	 */
	public function play(int $from, int $to): Page
	{
		$page = $this->akce();
		for ($i = $from; $i <= $to; $i++) {
			$page = $this->arriveAt($i);
			if ($i !== $this->game->getFinish() || $this->game->hasFinishCipher) {
				$page = $this->solve($i);
			}
		}

		return $page;
	}


	/**
	 * @return array<int, \Nette\Database\Row>
	 */
	public function results(): array
	{
		return $this->game->getResults($this->id);
	}


	/** @phpstan-impure */
	public function result(int $checkpoint): ?\Nette\Database\Row
	{
		return $this->game->getResult($this->id, $checkpoint);
	}


	/** @phpstan-impure */
	public function hasEnded(): bool
	{
		return $this->game->hasTeamEnded($this->id);
	}


	/**
	 * Texts of the INFO screen (the team's log) without the "H:i - " time, newest first.
	 * @return list<string>
	 */
	public function infoMessages(): array
	{
		return array_map(
			static fn(string $text): string => (string) preg_replace('~^\d{2}:\d{2} - ~', '', $text),
			$this->info()->getTexts('.messages-container .flash'),
		);
	}
}
