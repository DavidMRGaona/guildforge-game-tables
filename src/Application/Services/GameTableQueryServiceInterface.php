<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;

interface GameTableQueryServiceInterface
{
    /**
     * Get published game tables paginated.
     *
     * @param  array<string>|null  $gameSystemIds
     * @return array<GameTableListDTO>
     */
    public function getPublishedTablesPaginated(
        int $page,
        int $perPage,
        ?array $gameSystemIds = null,
        ?string $format = null,
        ?string $status = null,
        ?string $eventId = null,
        ?string $campaignId = null,
    ): array;

    /**
     * Get total count of published game tables.
     *
     * @param  array<string>|null  $gameSystemIds
     */
    public function getPublishedTablesTotal(
        ?array $gameSystemIds = null,
        ?string $format = null,
        ?string $status = null,
        ?string $eventId = null,
        ?string $campaignId = null,
    ): int;

    /**
     * Get upcoming game tables for calendar.
     *
     * @return array<GameTableListDTO>
     */
    public function getUpcomingTables(\DateTimeInterface $from, \DateTimeInterface $to): array;

    /**
     * Find a published game table by ID.
     */
    public function findPublished(string $id): ?GameTableResponseDTO;

    /**
     * Find a published game table by slug.
     */
    public function findPublishedBySlug(string $slug): ?GameTableResponseDTO;

    /**
     * Get game systems with active tables.
     *
     * @return array<array{id: string, name: string, count: int}>
     */
    public function getGameSystemsWithTables(): array;

    /**
     * Get events that have active game tables.
     *
     * @return array<array{id: string, title: string, count: int}>
     */
    public function getEventsWithTables(): array;
}
