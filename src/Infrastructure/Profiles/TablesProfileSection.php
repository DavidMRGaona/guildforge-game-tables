<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Profiles;

use App\Application\Exceptions\NothingToDrawException;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Profiles\ProfileRegion;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\GameTables\Application\DTOs\ProfileTableItemDTO;
use Modules\GameTables\Application\Services\ProfileActivityQueryInterface;

/**
 * «Mesas» on the public profile (game-tables#282): the next tables the owner directs and, as far as
 * the viewer may see the players, those they play in. Decided by ProfileActivityQueryInterface.
 */
final readonly class TablesProfileSection implements ProfileSectionInterface
{
    public const string KEY = 'game-tables.tables';

    /** Tables per list: a profile is drawn on every visit */
    public const int LIMIT = 5;

    public function __construct(
        private ProfileActivityQueryInterface $activity,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('game-tables::messages.profile_sections.tables.label');
    }

    public function description(): string
    {
        return __('game-tables::messages.profile_sections.tables.description');
    }

    public function component(): string
    {
        return 'components/profileSections/TablesProfileSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Main;
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
        $viewerId = $viewer instanceof Authenticatable ? (string) $viewer->getAuthIdentifier() : null;

        $directing = $this->activity->upcomingTablesRunBy($owner->id, self::LIMIT);
        $playing = $this->activity->upcomingTablesPlayedBy($owner->id, $viewerId, self::LIMIT);

        if ($directing === [] && $playing === []) {
            throw NothingToDrawException::make();
        }

        return [
            'directing' => array_map($this->item(...), $directing),
            'playing' => array_map($this->item(...), $playing),
        ];
    }

    /**
     * @return array{id: string, title: string, url: string, startsAt: string|null, gameSystem: string}
     */
    private function item(ProfileTableItemDTO $table): array
    {
        return [
            'id' => $table->id,
            'title' => $table->title,
            'url' => $table->url,
            'startsAt' => $this->timezone->iso($table->startsAt),
            'gameSystem' => $table->gameSystemName,
        ];
    }
}
