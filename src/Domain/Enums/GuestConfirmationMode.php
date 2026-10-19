<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum GuestConfirmationMode: string
{
    /** The unconfirmed guest holds a seat until the deadline. */
    case Hold = 'hold';

    /** The unconfirmed guest holds nothing: the seat is placed when they confirm. */
    case Wait = 'wait';
}
