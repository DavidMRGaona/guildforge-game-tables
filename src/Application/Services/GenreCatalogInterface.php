<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\GenreDTO;

/**
 * The genres tables and campaigns choose from, read once per request or queued job.
 *
 * Tables and campaigns store keys: a key the catalog no longer knows (or a malformed one) is
 * left out wherever the genres are shown, never an error. Long-lived services resolve the
 * catalog when they use it, never in their constructor, so a worker never keeps the genres of
 * a past job.
 */
interface GenreCatalogInterface
{
    /**
     * The genres offered now, in the catalog's order
     *
     * @return list<GenreDTO>
     */
    public function active(): array;

    /**
     * The genres of the stored keys, in the catalog's order, inactive ones included (the record
     * has them); unknown, malformed and repeated keys are left out
     *
     * @param  array<mixed>  $keys
     * @return list<GenreDTO>
     */
    public function resolve(array $keys): array;

    /**
     * Key => name of what a form offers: the active genres plus the ones the record already has,
     * in the catalog's order
     *
     * @param  array<mixed>  $keptKeys
     * @return array<string, string>
     */
    public function options(array $keptKeys = []): array;

    /**
     * The keys a form accepts: the active ones plus the ones the record already has
     *
     * @param  array<mixed>  $keptKeys
     * @return list<string>
     */
    public function acceptedKeys(array $keptKeys = []): array;
}
