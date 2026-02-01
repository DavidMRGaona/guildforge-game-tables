<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;

final readonly class ProfileCreatedTablesDataProvider
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    /**
     * Get created tables data for a user's profile page.
     *
     * @return array{tables: array<array<string, mixed>>, drafts: array<array<string, mixed>>, total: int}|null
     */
    public function getDataForUser(?string $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        $tables = $this->gameTableRepository->getByCreator($userId);

        if (count($tables) === 0) {
            return null;
        }

        $published = [];
        $drafts = [];

        foreach ($tables as $table) {
            $dto = GameTableResponseDTO::fromEntity($table);
            $data = $this->formatTableForProfile($dto);

            if ($table->isPublished) {
                $published[] = $data;
            } else {
                $drafts[] = $data;
            }
        }

        return [
            'tables' => $published,
            'drafts' => $drafts,
            'total' => count($tables),
        ];
    }

    /**
     * Format a table DTO for the profile display.
     *
     * @return array<string, mixed>
     */
    private function formatTableForProfile(GameTableResponseDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'title' => $dto->title,
            'slug' => $dto->slug,
            'gameSystemName' => $dto->gameSystemName,
            'startsAt' => $dto->startsAt?->format('c'),
            'status' => $dto->status->value,
            'statusLabel' => $dto->status->label(),
            'statusColor' => $dto->status->color(),
            'isPublished' => $dto->isPublished,
            'tableFormat' => $dto->tableFormat->value,
            'tableFormatLabel' => $dto->tableFormat->label(),
            'eventId' => $dto->eventId,
            'eventTitle' => $dto->eventTitle,
            'minPlayers' => $dto->minPlayers,
            'maxPlayers' => $dto->maxPlayers,
            'currentPlayers' => count(array_filter(
                $dto->participants,
                fn ($p) => $p->role === 'player' && in_array($p->status, ['confirmed', 'pending'], true),
            )),
        ];
    }
}
