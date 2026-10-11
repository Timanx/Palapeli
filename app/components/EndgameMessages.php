<?php

declare(strict_types=1);

namespace App\Components;

use App\Components\ActionScreen\ActionScreen;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;


/**
 * Flash messages shown on Palainfo screens (KARTA, PŘÍCHODY) once the team finished or quit the game.
 *
 * @property-read ResultsModel $resultsModel
 * @property-read TeamsModel $teamsModel
 * @property-read YearsModel $yearsModel
 */
trait EndgameMessages
{
	private function flashEndgameMessages(int $teamId): void
	{
		if (!$this->teamsModel->hasTeamEnded($teamId, $this->year)) {
			return;
		}

		$endgame = $this->yearsModel->getEndgameData($this->year);
		$nextCheckpoint = $this->resultsModel->getFirstEmptyCheckpoint($teamId, $this->year);

		if (ActionScreen::hasReachedFinish($nextCheckpoint, (int) $endgame?->checkpoint_count, (bool) $endgame?->has_finish_cipher)) {
			$this->flashMessage('Hru jste úspěšně dokončili! Gratulujeme.', 'success');
		} else {
			$this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
			$this->flashMessage(sprintf('Pozice cíle: %s (otevřen od %s)', $endgame?->finish_location, $endgame?->finish_open_time), 'info');
		}

		if ($endgame?->afterparty_location) {
			$this->flashMessage(sprintf('Místo konání afterparty: %s (od %s)', $endgame->afterparty_location, $endgame->afterparty_time), 'info');
		}
	}
}
