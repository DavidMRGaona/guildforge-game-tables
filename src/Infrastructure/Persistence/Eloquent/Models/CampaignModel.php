<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasSlug;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\Tone;

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
 * @property RosterAccess $roster_access
 * @property string|null $language
 * @property ExperienceLevel|null $experience_level
 * @property int|null $min_players
 * @property TableFormat|null $table_format
 * @property string|null $location
 * @property string|null $online_url
 * @property string|null $schedule_notes
 * @property array<string>|null $genres
 * @property Tone|null $tone
 * @property CharacterCreation|null $character_creation
 * @property array<string>|null $safety_tools
 * @property array<string>|null $custom_warnings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CampaignModel extends Model
{
    use DeletesCloudinaryImages;
    use HasSlug;
    use HasUuids;
    use StoresInstantsInStorageTimezone;

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
        'roster_access',
        'language',
        'experience_level',
        'min_players',
        'table_format',
        'location',
        'online_url',
        'schedule_notes',
        'genres',
        'tone',
        'character_creation',
        'safety_tools',
        'custom_warnings',
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
            'roster_access' => RosterAccess::class,
            'experience_level' => ExperienceLevel::class,
            'min_players' => 'integer',
            'table_format' => TableFormat::class,
            'genres' => 'array',
            'tone' => Tone::class,
            'character_creation' => CharacterCreation::class,
            'safety_tools' => 'array',
            'custom_warnings' => 'array',
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
     * @return BelongsTo<UserModel, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            UserModel::class,
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
     * @return BelongsToMany<UserModel, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(
            UserModel::class,
            'gametables_campaign_players',
            'campaign_id',
            'user_id',
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<ContentWarningModel, $this>
     */
    public function contentWarnings(): BelongsToMany
    {
        return $this->belongsToMany(
            ContentWarningModel::class,
            'gametables_campaign_content_warnings',
            'campaign_id',
            'content_warning_id',
        );
    }

    /**
     * @return HasMany<CampaignInvitationModel, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CampaignInvitationModel::class, 'campaign_id');
    }

    /**
     * @return HasMany<CampaignJoinRequestModel, $this>
     */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(CampaignJoinRequestModel::class, 'campaign_id');
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
     * The campaigns the public listing shows: published and not cancelled. The site search offers the
     * same ones, so both read this rule.
     *
     * @param  Builder<CampaignModel>  $query
     * @return Builder<CampaignModel>
     */
    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNotIn('status', [CampaignStatus::Cancelled->value]);
    }

    /**
     * Get the entity type for slug redirects.
     */
    public function getSlugEntityType(): string
    {
        return 'campaign';
    }
}
