<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $publisher_id
 * @property string|null $edition
 * @property int|null $year
 * @property string|null $logo_url
 * @property string|null $website_url
 * @property string $game_master_title
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PublisherModel|null $publisher
 */
final class GameSystemModel extends Model
{
    use HasUuids;

    protected $table = 'gametables_game_systems';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'publisher_id',
        'edition',
        'year',
        'logo_url',
        'website_url',
        'game_master_title',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PublisherModel, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(PublisherModel::class, 'publisher_id');
    }

    /**
     * @return HasMany<GameTableModel, $this>
     */
    public function gameTables(): HasMany
    {
        return $this->hasMany(GameTableModel::class, 'game_system_id');
    }

    /**
     * @return HasMany<CampaignModel, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(CampaignModel::class, 'game_system_id');
    }
}
