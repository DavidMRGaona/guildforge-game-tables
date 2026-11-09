<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Publications;

use App\Application\Publications\DTOs\PublicationTopicDTO;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;

/**
 * The game system of a table or campaign as a publication topic: keyed by its id, so a rename
 * keeps the same topic, and labelled with its current name.
 */
final readonly class GameSystemPublicationTopic
{
    public const string KEY_PREFIX = 'game-system.';

    public function __construct(
        private GameSystemRepositoryInterface $systems,
    ) {}

    /**
     * @return list<PublicationTopicDTO> empty when the system no longer exists
     */
    public function of(GameSystemId $id): array
    {
        $system = $this->systems->find($id);

        return $system instanceof GameSystem
            ? [new PublicationTopicDTO(self::KEY_PREFIX.$system->id->value, $system->name)]
            : [];
    }
}
