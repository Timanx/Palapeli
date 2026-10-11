<?php

declare(strict_types=1);

namespace App\Models;


/**
 * Types of Palainfo log entries (table log_type, column log.type_id).
 */
enum LogType: int
{
	case EnterCheckpoint = 1;
	case OpenDead = 2;
	case EndGame = 3;
	case MessageToOrg = 4;
	case MessageFromOrg = 5;
	case CloseCheckpoint = 6;
	case GameStart = 7;
	case GameFinish = 8;
}
