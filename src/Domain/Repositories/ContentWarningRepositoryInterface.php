<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\ContentWarning;
use Modules\GameTables\Domain\Enums\WarningSeverity;
use Modules\GameTables\Domain\Exceptions\ContentWarningNotFoundException;
use Modules\GameTables\Domain\ValueObjects\ContentWarningId;

interface ContentWarningRepositoryInterface
{
    /**
     * Save a content warning (create or update).
     */
    public function save(ContentWarning $contentWarning): void;

    /**
     * Find a content warning by ID.
     */
    public function find(ContentWarningId $id): ?ContentWarning;

    /**
     * Find a content warning by ID or throw an exception.
     *
     * @throws ContentWarningNotFoundException
     */
    public function findOrFail(ContentWarningId $id): ContentWarning;

    /**
     * Find a content warning by name.
     */
    public function findByName(string $name): ?ContentWarning;

    /**
     * Delete a content warning by ID.
     */
    public function delete(ContentWarningId $id): void;

    /**
     * Get all active content warnings.
     *
     * @return array<ContentWarning>
     */
    public function getActive(): array;

    /**
     * Get content warnings by severity.
     *
     * @return array<ContentWarning>
     */
    public function getBySeverity(WarningSeverity $severity): array;

    /**
     * Get all content warnings.
     *
     * @return array<ContentWarning>
     */
    public function all(): array;

    /**
     * Check if a content warning exists by name.
     */
    public function existsByName(string $name): bool;

    /**
     * Find multiple content warnings by their IDs.
     *
     * @param array<ContentWarningId> $ids
     *
     * @return array<ContentWarning>
     */
    public function findByIds(array $ids): array;
}
