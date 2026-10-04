<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Components\DiscussionControl\DiscussionControl;
use App\Components\DiscussionControl\DiscussionControlFactory;


/**
 * Public discussion.
 */
final class DiscussionPresenter extends BasePresenter
{
	public function __construct(
		private readonly DiscussionControlFactory $discussionControlFactory,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->prepareHeading('Diskuse');
	}


	protected function createComponentDiscussion(): DiscussionControl
	{
		return $this->discussionControlFactory->create()
			->setTeamId($this->teamId)
			->setTeamName($this->teamSession->getTeamName())
			->setThread(DiscussionControl::MainThread);
	}
}
