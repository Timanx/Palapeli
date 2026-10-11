<?php

declare(strict_types=1);

namespace App\Utils;

use Nette;


/**
 * Application wide constants.
 */
final class AppConstants
{
	use Nette\StaticClass;

	/** ID of the special "team" the organizers log in with. */
	public const OrgTeamId = -1;

	public const OrgEmail = 'organizatori@palapeli.cz';

	/** Sender used for all e-mails sent by the web. */
	public const OrgMailFrom = 'Palapeli Web <organizatori@palapeli.cz>';

	/** Placeholder shown in time inputs that have no value yet. */
	public const EmptyTimeValue = '--:--';

	/** Answer to the anti-spam question "how many puzzle pieces does the Palapeli logo have". */
	public const CaptchaAnswer = 4;
}
