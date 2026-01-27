<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Exceptions\GameSystemNotFoundException;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;

interface GameSystemRepositoryInterface
{
    /**
     * Save a game system (create or update).
     */
    public function save(GameSystem $gameSystem): void;

    /**
     * Find a game system by ID.
     */
    public function find(GameSystemId $id): ?GameSystem;

    /**
     * Find a game system by ID or throw an exception.
     *
     * @throws GameSystemNotFoundException
     */
    public function findOrFail(GameSystemId $id): GameSystem;

    /**
     * Find a game system by slug.
     */
    public function findBySlug(string $slug): ?GameSystem;

    /**
     * Delete a game system by ID.
     */
    public function delete(GameSystemId $id): void;

    /**
     * Get all active game systems.
     *
     * @return array<GameSystem>
     */
    public function getActive(): array;

    /**
     * Get all game systems.
     *
     * @return array<GameSystem>
     */
    public function all(): array;

    /**
     * Check if a game system exists by slug.
     */
    public function existsBySlug(string $slug): bool;
}
