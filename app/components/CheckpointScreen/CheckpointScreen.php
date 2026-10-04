<?php

declare(strict_types=1);

namespace App\Components\CheckpointScreen;

use App\Components\BaseControl;
use App\Components\EndgameMessages;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;


/**
 * Palainfo "PŘÍCHODY" screen: order in which teams arrived to the checkpoint the team is on.
 */
final class CheckpointScreen extends BaseControl
{
	use EndgameMessages;


	public function __construct(
		private readonly ResultsModel $resultsModel,
		private readonly YearsModel $yearsModel,
		private readonly TeamsModel $teamsModel,
	) {
	}


	public function render(): void
	{
		$teamId = $this->requireTeamId();
		$this->flashEndgameMessages($teamId);

		$checkpoint = $this->resultsModel->getLastCheckpointNumber($teamId, $this->year);

		$this->renderTemplate(__DIR__ . '/checkpointScreen.latte', [
			'checkpointNumber' => $checkpoint,
			'checkpointData' => $checkpoint === null
				? null
				: $this->resultsModel->getCheckpointEntryTimes($this->year, $checkpoint, arrivedOnly: true),
			'checkpointCount' => $this->yearsModel->getCheckpointCount($this->year),
			'current' => $teamId,
		]);
	}
}
