<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Activity;

use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

/**
 * The game system a session or a campaign was played with, as activity facts carry it: its id, so
 * «three different systems» survives a rename, and its name to show. Every game system is listed,
 * inactive ones too, since older tables still played them.
 */
final readonly class GameSystemActivityAttribute
{
    public const string KEY = 'game_system';

    public function __construct(
        private GameSystemRepositoryInterface $systems,
    ) {}

    public function attribute(): ActivityAttributeDTO
    {
        return new ActivityAttributeDTO(self::KEY, __('game-tables::messages.activity.game_system'));
    }

    /**
     * @return list<ActivityAttributeValueDTO>
     */
    public function values(string $key): array
    {
        if ($key !== self::KEY) {
            return [];
        }

        return array_values(array_map(
            static fn (GameSystem $system): ActivityAttributeValueDTO => new ActivityAttributeValueDTO($system->id->value, $system->name),
            array_filter($this->systems->all(), static fn (GameSystem $system): bool => trim($system->name) !== ''),
        ));
    }

    /**
     * The attributes of a fact played with this system: none without one.
     *
     * @return array<string, ActivityAttributeValueDTO>
     */
    public static function of(?GameSystemModel $system): array
    {
        if (! $system instanceof GameSystemModel || trim($system->name) === '') {
            return [];
        }

        return [self::KEY => new ActivityAttributeValueDTO((string) $system->getKey(), $system->name)];
    }
}
