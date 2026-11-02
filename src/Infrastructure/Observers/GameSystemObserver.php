<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

/**
 * A game system in use is never deleted, whoever asks: it would take its tables and campaigns
 * with it. The panel explains it and offers to deactivate the system; the foreign keys refuse a
 * delete that skips the model.
 */
final class GameSystemObserver
{
    public function deleting(GameSystemModel $gameSystem): bool
    {
        return ! $gameSystem->isInUse();
    }
}
