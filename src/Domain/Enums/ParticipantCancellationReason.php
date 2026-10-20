<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum ParticipantCancellationReason: string
{
    case LeftRoster = 'left_roster';
    case SessionAbsence = 'session_absence';
}
