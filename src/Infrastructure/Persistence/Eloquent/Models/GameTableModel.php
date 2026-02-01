<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasSlug;

/**
 * @property string $id
 * @property string $game_system_id
 * @property string|null $campaign_id
 * @property string|null $event_id
 * @property string $created_by
 * @property string $title
 * @property string|null $slug
 * @property Carbon $starts_at
 * @property int $duration_minutes
 * @property TableType $table_type
 * @property TableFormat $table_format
 * @property TableStatus $status
 * @property int $min_players
 * @property int $max_players
 * @property int $max_spectators
 * @property string|null $synopsis
 * @property string|null $location
 * @property string|null $online_url
 * @property int|null $minimum_age
 * @property string $language
 * @property array<string>|null $genres
 * @property Tone|null $tone
 * @property ExperienceLevel|null $experience_level
 * @property CharacterCreation|null $character_creation
 * @property array<string>|null $safety_tools
 * @property array<string>|null $custom_warnings
 * @property RegistrationType $registration_type
 * @property int $members_early_access_days
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 * @property bool $auto_confirm
 * @property bool $accepts_registrations_in_progress
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property string|null $notes
 * @property string|null $notification_email
 * @property string|null $image_public_id
 * @property FrontendCreationStatus|null $frontend_creation_status
 * @property string|null $moderation_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class GameTableModel extends Model
{
    use DeletesCloudinaryImages;
    use HasSlug;
    use HasUuids;

    /**
     * @var array<string>
     */
    protected array $cloudinaryImageFields = ['image_public_id'];

    protected $table = 'gametables_tables';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'game_system_id',
        'campaign_id',
        'event_id',
        'created_by',
        'title',
        'slug',
        'starts_at',
        'duration_minutes',
        'table_type',
        'table_format',
        'status',
        'min_players',
        'max_players',
        'max_spectators',
        'synopsis',
        'location',
        'online_url',
        'minimum_age',
        'language',
        'genres',
        'tone',
        'experience_level',
        'character_creation',
        'safety_tools',
        'custom_warnings',
        'registration_type',
        'members_early_access_days',
        'registration_opens_at',
        'registration_closes_at',
        'auto_confirm',
        'accepts_registrations_in_progress',
        'is_published',
        'published_at',
        'notes',
        'notification_email',
        'image_public_id',
        'frontend_creation_status',
        'moderation_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
            'table_type' => TableType::class,
            'table_format' => TableFormat::class,
            'status' => TableStatus::class,
            'min_players' => 'integer',
            'max_players' => 'integer',
            'max_spectators' => 'integer',
            'minimum_age' => 'integer',
            'genres' => 'array',
            'tone' => Tone::class,
            'experience_level' => ExperienceLevel::class,
            'character_creation' => CharacterCreation::class,
            'safety_tools' => 'array',
            'custom_warnings' => 'array',
            'registration_type' => RegistrationType::class,
            'members_early_access_days' => 'integer',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'auto_confirm' => 'boolean',
            'accepts_registrations_in_progress' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
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
     * @return BelongsTo<CampaignModel, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CampaignModel::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<EventModel, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    /**
     * @return BelongsToMany<ContentWarningModel, $this>
     */
    public function contentWarnings(): BelongsToMany
    {
        return $this->belongsToMany(
            ContentWarningModel::class,
            'gametables_table_content_warnings',
            'game_table_id',
            'content_warning_id',
        );
    }

    /**
     * @return HasMany<ParticipantModel, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ParticipantModel::class, 'game_table_id');
    }

    /**
     * Get all game masters directly associated with this table (both inherited and local).
     *
     * @return BelongsToMany<GameMasterModel, $this>
     */
    public function gameMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            GameMasterModel::class,
            'gametables_table_gm',
            'game_table_id',
            'game_master_id',
        )->withPivot('source', 'excluded', 'sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    /**
     * Get only local game masters (added directly to this table).
     *
     * @return BelongsToMany<GameMasterModel, $this>
     */
    public function localGameMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            GameMasterModel::class,
            'gametables_table_gm',
            'game_table_id',
            'game_master_id',
        )->withPivot('source', 'excluded', 'sort_order')
            ->withTimestamps()
            ->wherePivot('source', 'local')
            ->wherePivot('excluded', false)
            ->orderByPivot('sort_order');
    }

    /**
     * Get inherited game masters that have been excluded from this table.
     *
     * @return BelongsToMany<GameMasterModel, $this>
     */
    public function excludedGameMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            GameMasterModel::class,
            'gametables_table_gm',
            'game_table_id',
            'game_master_id',
        )->withPivot('source', 'excluded', 'sort_order')
            ->withTimestamps()
            ->wherePivot('excluded', true);
    }

    /**
     * Get the effective game masters for this table (inherited from campaign + local, minus excluded).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, GameMasterModel>
     */
    public function getEffectiveGameMastersAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        // Get excluded GM IDs for this table
        $excludedIds = $this->excludedGameMasters()->pluck('gametables_game_masters.id')->toArray();

        // Start with campaign GMs if linked to a campaign
        if ($this->campaign_id !== null) {
            $campaignGms = $this->campaign?->gameMasters()
                ->whereNotIn('gametables_game_masters.id', $excludedIds)
                ->get() ?? collect();
        } else {
            $campaignGms = collect();
        }

        // Add local GMs
        $localGms = $this->localGameMasters()->get();

        // Merge and return unique by ID
        return $campaignGms->merge($localGms)->unique('id')->values();
    }

    /**
     * Ensure members_early_access_days is never null.
     *
     * @return Attribute<int, int>
     */
    protected function membersEarlyAccessDays(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): int => (int) ($value ?? 0),
        );
    }

    /**
     * Get the entity type for slug redirects.
     */
    public function getSlugEntityType(): string
    {
        return 'game_table';
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
