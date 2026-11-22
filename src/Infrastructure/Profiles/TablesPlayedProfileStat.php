<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\DescribesProfileStat;
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\GameTables\Application\Services\ProfileActivityQueryInterface;

/**
 * «Partidas jugadas» (game-tables#325): every listed table already started, campaign sessions
 * included, where the owner holds a confirmed player seat. A total names no table, so it is the same
 * for every viewer, as the activity sources rule allows for the player role; which tables they were
 * shows only in «Últimas partidas», to who may see the players. None hides the counter.
 */
final readonly class TablesPlayedProfileStat implements DescribesProfileStat, ProfileStatInterface
{
    public const string KEY = 'game-tables.tables_played';

    public function __construct(
        private ProfileActivityQueryInterface $activity,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('game-tables::messages.profile_sections.tables_played.label');
    }

    public function description(): string
    {
        return __('game-tables::messages.profile_sections.tables_played.description');
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

    public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
    {
        $count = $this->activity->countTablesPlayedBy($owner->id);

        return $count > 0 ? new ProfileStatDTO($count) : null;
    }
}
