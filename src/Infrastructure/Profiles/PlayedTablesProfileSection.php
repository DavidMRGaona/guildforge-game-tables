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
 * «Últimas partidas» on the public profile (game-tables#325): the latest tables already started where
 * the owner held a player seat, shown only to who may see the players (the owner and who manages that
 * table or that campaign's roster), as «Mesas» does with the upcoming ones. A section of its own, so
 * the owner can hide it apart from «Mesas».
 */
final readonly class PlayedTablesProfileSection implements ProfileSectionInterface
{
    public const string KEY = 'game-tables.played-tables';

    /** Tables listed: a profile is drawn on every visit */
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
        return __('game-tables::messages.profile_sections.played_tables.label');
    }

    public function description(): string
    {
        return __('game-tables::messages.profile_sections.played_tables.description');
    }

    public function component(): string
    {
        return 'components/profileSections/PlayedTablesProfileSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Main;
    }

    public function order(): int
    {
        return 35;
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
        $tables = $this->activity->recentTablesPlayedBy($owner->id, $viewer, self::LIMIT);

        if ($tables === []) {
            throw NothingToDrawException::make();
        }

        return [
            'tables' => array_map(fn (ProfileTableItemDTO $table): array => [
                'id' => $table->id,
                'title' => $table->title,
                'url' => $table->url,
                'startsAt' => $this->timezone->iso($table->startsAt),
                'gameSystem' => $table->gameSystemName,
            ], $tables),
        ];
    }
}
