<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class CampaignNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Campaign with ID '{$id}' not found.");
    }
}
