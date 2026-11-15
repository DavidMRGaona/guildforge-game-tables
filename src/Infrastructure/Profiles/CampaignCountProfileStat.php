<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\GameTables\Application\Services\ProfileActivityQueryInterface;

/**
 * «Campañas» (game-tables#282): every listed campaign, finished ones included, the owner directs
 * under a public name or plays in as far as the viewer may see the roster. Nothing they may see
 * hides the counter, rather than a 0 that would say the owner plays in none.
 */
final readonly class CampaignCountProfileStat implements ProfileStatInterface
{
    public const string KEY = 'game-tables.campaign_count';

    public function __construct(
        private ProfileActivityQueryInterface $activity,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('game-tables::messages.profile_sections.campaign_count');
    }

    public function order(): int
    {
        return 40;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
    {
        $count = $this->activity->countCampaignsOf($owner->id, $viewer instanceof Authenticatable ? (string) $viewer->getAuthIdentifier() : null);

        return $count > 0 ? new ProfileStatDTO($count) : null;
    }
}
