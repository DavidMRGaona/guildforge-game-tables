<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A game master directing a campaign. A model of its own so attaching and detaching fire model
 * events: whoever mirrors a campaign's direction elsewhere hears about it.
 *
 * @property string $campaign_id
 * @property string $game_master_id
 */
final class CampaignGameMasterPivot extends Pivot
{
    public const string TABLE = 'gametables_campaign_gm';

    public $incrementing = false;

    protected $table = self::TABLE;
}
