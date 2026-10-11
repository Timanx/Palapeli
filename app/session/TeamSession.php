<?php

declare(strict_types=1);

namespace App\Session;

use App\Utils\AppConstants;
use Nette\Http\Session;
use Nette\Http\SessionSection;


/**
 * Logged-in team stored in the session (section "team", keys "teamId" and "teamName").
 * The storage format is the same as in the previous version, so existing sessions survive upgrades.
 */
final class TeamSession
{
	private const Section = 'team';


	public function __construct(
		private readonly Session $session,
	) {
	}


	public function isLoggedIn(): bool
	{
		return $this->getTeamId() !== null;
	}


	public function isOrg(): bool
	{
		return $this->getTeamId() === AppConstants::OrgTeamId;
	}


	public function getTeamId(): ?int
	{
		$id = $this->read('teamId');
		return $id === null || $id === '' ? null : (int) $id;
	}


	public function getTeamName(): ?string
	{
		$name = $this->read('teamName');
		return $name === null || $name === '' ? null : (string) $name;
	}


	public function logIn(int $teamId, string $teamName): void
	{
		$section = $this->section();
		$section->set('teamId', $teamId);
		$section->set('teamName', $teamName);
	}


	public function logOut(): void
	{
		if ($this->session->hasSection(self::Section)) {
			$section = $this->section();
			$section->remove('teamId');
			$section->remove('teamName');
		}
	}


	private function read(string $key): mixed
	{
		return $this->session->hasSection(self::Section)
			? $this->section()->get($key)
			: null;
	}


	private function section(): SessionSection
	{
		return $this->session->getSection(self::Section);
	}
}
