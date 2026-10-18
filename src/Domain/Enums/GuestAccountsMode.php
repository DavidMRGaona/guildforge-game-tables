<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum GuestAccountsMode: string
{
    /** A guest whose email belongs to an account is asked to sign in. */
    case RequireLogin = 'require_login';

    /** A guest may register with an account's email unless that account is already registered. */
    case AllowGuest = 'allow_guest';
}
