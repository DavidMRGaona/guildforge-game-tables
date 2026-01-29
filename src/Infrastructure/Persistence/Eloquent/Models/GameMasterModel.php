<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\Concerns\HasGameMasterAttributes;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property GameMasterRole $role
 * @property string|null $custom_title
 * @property bool $notify_by_email
 * @property bool $is_name_public
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \App\Infrastructure\Persistence\Eloquent\Models\UserModel|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignModel> $campaigns
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GameTableModel> $gameTables
 */
final class GameMasterModel extends Model
{
    use HasGameMasterAttributes;
    use HasUuids;

    protected $table = 'gametables_game_masters';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
    ];

    /**
     * @return BelongsToMany<CampaignModel, $this>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(
            CampaignModel::class,
            'gametables_campaign_gm',
            'game_master_id',
            'campaign_id',
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * @return BelongsToMany<GameTableModel, $this>
     */
    public function gameTables(): BelongsToMany
    {
        return $this->belongsToMany(
            GameTableModel::class,
            'gametables_table_gm',
            'game_master_id',
            'game_table_id',
        )->withPivot('source', 'excluded', 'sort_order')->withTimestamps();
    }
}
