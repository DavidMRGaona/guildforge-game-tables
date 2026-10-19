<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

/**
 * What following the confirmation link did to a guest registration.
 */
enum GuestConfirmationOutcome: string
{
    /** The email is confirmed and the registration announced. */
    case Confirmed = 'confirmed';

    /** The link had already been followed: nothing changes. */
    case AlreadyConfirmed = 'already_confirmed';

    /** Unknown token, or a registration no longer active or past its deadline. */
    case Invalid = 'invalid';

    /** Registrations closed before the guest confirmed: the registration expired. */
    case Closed = 'closed';

    /** A guest without a seat found no room left when confirming: the registration expired. */
    case FilledUp = 'filled_up';
}
