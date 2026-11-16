<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Enums\CampaignStatus;

/**
 * A campaign on a public profile: what its public page already shows, and whether the owner directs
 * it or plays in it.
 */
final readonly class ProfileCampaignItemDTO
{
    public const string ROLE_DIRECTOR = 'director';

    public const string ROLE_PLAYER = 'player';

    /**
     * @param  self::ROLE_*  $role
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $gameSystemName,
        public CampaignStatus $status,
        public string $role,
    ) {}
}
