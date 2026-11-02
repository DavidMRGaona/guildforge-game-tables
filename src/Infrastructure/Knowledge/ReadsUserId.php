<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Knowledge;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The id the module's rules take, or null for something that is not a signed-in account.
 */
trait ReadsUserId
{
    private function userId(object $user): ?string
    {
        return $user instanceof Authenticatable ? (string) $user->getAuthIdentifier() : null;
    }
}
