<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

/**
 * The visible name of the site's association member role (site settings, «socio» by default),
 * for the texts that name it: «Solo el rol :role». Each site chooses that role, so no text
 * writes «socios» by hand.
 */
interface AssociationRoleNameInterface
{
    /** Its display name, or a generic name when no usable role is configured */
    public function displayName(): string;
}
