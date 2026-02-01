<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasSlug;

/**
 * @property string $id
 * @property string $game_system_id
 * @property string $created_by
 * @property string $title
 * @property string|null $slug
 * @property string|null $description
 * @property CampaignFrequency|null $frequency
 * @property CampaignStatus $status
 * @property int|null $session_count
 * @property int $current_session
 * @property bool $accepts_new_players
 * @property int|null $max_players
 * @property string|null $image_public_id
 * @property bool $is_published
 * @property FrontendCreationStatus|null $frontend_creation_status
 * @property string|null $moderation_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CampaignModel extends Model
{
    use DeletesCloudinaryImages;
    use HasSlug;
    use HasUuids;

    /**
     * @var array<string>
     */
    protected array $cloudinaryImageFields = ['image_public_id'];

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
        'slug',
        'description',
        'frequency',
        'status',
        'session_count',
        'current_session',
        'accepts_new_players',
        'max_players',
        'image_public_id',
        'is_published',
        'frontend_creation_status',
        'moderation_notes',
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
            'frontend_creation_status' => FrontendCreationStatus::class,
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

    /**
     * @return BelongsToMany<GameMasterModel, $this>
     */
    public function gameMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            GameMasterModel::class,
            'gametables_campaign_gm',
            'campaign_id',
            'game_master_id',
        )->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    /**
     * Get the entity type for slug redirects.
     */
    public function getSlugEntityType(): string
    {
        return 'campaign';
    }
}
