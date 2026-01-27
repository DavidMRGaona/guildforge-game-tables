<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;

/**
 * @property string $id
 * @property string $game_system_id
 * @property string $created_by
 * @property string $title
 * @property string|null $description
 * @property CampaignFrequency|null $frequency
 * @property CampaignStatus $status
 * @property int|null $session_count
 * @property int $current_session
 * @property bool $accepts_new_players
 * @property int|null $max_players
 * @property bool $is_published
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CampaignModel extends Model
{
    use HasUuids;

    protected $table = 'gametables_campaigns';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'game_system_id',
        'created_by',
        'title',
        'description',
        'frequency',
        'status',
        'session_count',
        'current_session',
        'accepts_new_players',
        'max_players',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => CampaignFrequency::class,
            'status' => CampaignStatus::class,
            'session_count' => 'integer',
            'current_session' => 'integer',
            'accepts_new_players' => 'boolean',
            'max_players' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GameSystemModel, $this>
     */
    public function gameSystem(): BelongsTo
    {
        return $this->belongsTo(GameSystemModel::class, 'game_system_id');
    }

    /**
     * @return BelongsTo<\App\Infrastructure\Persistence\Eloquent\Models\UserModel, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            \App\Infrastructure\Persistence\Eloquent\Models\UserModel::class,
            'created_by',
        );
    }

    /**
     * @return HasMany<GameTableModel, $this>
     */
    public function gameTables(): HasMany
    {
        return $this->hasMany(GameTableModel::class, 'campaign_id');
    }

    /**
     * @return BelongsToMany<\App\Infrastructure\Persistence\Eloquent\Models\UserModel, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Infrastructure\Persistence\Eloquent\Models\UserModel::class,
            'gametables_campaign_players',
            'campaign_id',
            'user_id',
        )->withTimestamps();
    }
}
