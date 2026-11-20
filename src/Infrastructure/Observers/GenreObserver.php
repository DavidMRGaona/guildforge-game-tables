<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use Modules\GameTables\Application\Services\GenreCatalogInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

/**
 * A genre in use is never deleted, whoever asks: its tables and campaigns would lose it. The panel
 * explains it and offers to deactivate the genre. Tables keep keys in a JSON column, so no
 * foreign key backs this up: a table that takes the genre while it is being deleted keeps a key
 * the catalog no longer knows, which pages leave out.
 *
 * Any change drops the catalog this request or job already read, so what follows sees it.
 */
final class GenreObserver
{
    public function deleting(GenreModel $genre): bool
    {
        return ! $genre->isInUse();
    }

    public function saved(): void
    {
        app()->forgetInstance(GenreCatalogInterface::class);
    }

    public function deleted(): void
    {
        app()->forgetInstance(GenreCatalogInterface::class);
    }
}
