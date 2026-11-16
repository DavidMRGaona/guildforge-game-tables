<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Profiles;

use App\Application\Exceptions\NothingToDrawException;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Domain\Profiles\ProfileRegion;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\GameTables\Application\DTOs\ProfileCampaignItemDTO;
use Modules\GameTables\Application\Services\ProfileActivityQueryInterface;

/**
 * «Campañas» on the public profile (game-tables#282): the campaigns going on that the owner directs
 * and, as far as the viewer may see the roster, those they play in. Decided by
 * ProfileActivityQueryInterface.
 */
final readonly class CampaignsProfileSection implements ProfileSectionInterface
{
    public const string KEY = 'game-tables.campaigns';

    /** Campaigns listed: a profile is drawn on every visit */
    public const int LIMIT = 5;

    public function __construct(
        private ProfileActivityQueryInterface $activity,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('game-tables::messages.profile_sections.campaigns.label');
    }

    public function description(): string
    {
        return __('game-tables::messages.profile_sections.campaigns.description');
    }

    public function component(): string
    {
        return 'components/profileSections/CampaignsProfileSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Aside;
    }

    public function order(): int
    {
        return 30;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function props(ProfileOwnerDTO $owner, ?Authenticatable $viewer): array
    {
        $campaigns = $this->activity->activeCampaignsOf(
            $owner->id,
            $viewer,
            self::LIMIT,
        );

        if ($campaigns === []) {
            throw NothingToDrawException::make();
        }

        return [
            'campaigns' => array_map(static fn (ProfileCampaignItemDTO $campaign): array => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'url' => $campaign->url,
                'gameSystem' => $campaign->gameSystemName,
                'status' => $campaign->status->label(),
                'role' => $campaign->role,
            ], $campaigns),
        ];
    }
}
