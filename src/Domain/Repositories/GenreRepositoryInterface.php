<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\Genre;
use Modules\GameTables\Domain\ValueObjects\GenreKey;

interface GenreRepositoryInterface
{
    /**
     * Save a genre (create or update)
     */
    public function save(Genre $genre): void;

    public function findByKey(GenreKey $key): ?Genre;

    /**
     * Every genre, active or not, in the catalog's order (position, then name)
     *
     * @return list<Genre>
     */
    public function all(): array;
}
