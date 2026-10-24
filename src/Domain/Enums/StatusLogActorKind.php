<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

/**
 * Who made a change in the registration history: an account acting on someone's registration (the
 * organisation, a director), the participant on their own registration (a guest has no account),
 * or the module by itself (promotions, expiries).
 */
enum StatusLogActorKind: string
{
    case Account = 'account';
    case Participant = 'participant';
    case System = 'system';
}
