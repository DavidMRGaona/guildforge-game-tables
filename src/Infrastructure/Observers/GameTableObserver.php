<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Observer for game table model events.
 *
 * Note: With the unified GM structure, game masters are no longer copied from
 * campaigns. Instead, they are inherited via the campaign relationship, and
 * the effective_game_masters attribute handles the inheritance logic.
 */
final class GameTableObserver
{
    /**
     * Handle the game table "created" event.
     */
    public function created(GameTableModel $gameTable): void
    {
        // GM inheritance is now handled via the campaign relationship
        // and the effectiveGameMasters attribute on GameTableModel.
        // No copying is needed.
    }

    /**
     * Handle the game table "updated" event.
     */
    public function updated(GameTableModel $gameTable): void
    {
        // GM inheritance is automatic - no action needed.
    }
}
