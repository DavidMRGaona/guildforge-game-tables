<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\GameTables\Application\Services\ProfileActivityQueryInterface;

/**
 * «Mesas dirigidas» (game-tables#282): every listed table, past ones included, the owner directs
 * under a public name. The pages show it to anybody, so it is the same count for every viewer; none
 * hides the counter.
 */
final readonly class TablesRunProfileStat implements ProfileStatInterface
{
    public const string KEY = 'game-tables.tables_run';

    public function __construct(
        private ProfileActivityQueryInterface $activity,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('game-tables::messages.profile_sections.tables_run');
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

    public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
    {
        $count = $this->activity->countTablesRunBy($owner->id);

        return $count > 0 ? new ProfileStatDTO($count) : null;
    }
}
