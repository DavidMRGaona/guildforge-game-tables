<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $website_url
 * @property string|null $logo_url
 * @property string|null $country
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PublisherModel extends Model
{
    use HasUuids;

    protected $table = 'gametables_publishers';

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
        'website_url',
        'logo_url',
        'country',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GameSystemModel, $this>
     */
    public function gameSystems(): HasMany
    {
        return $this->hasMany(GameSystemModel::class, 'publisher_id');
    }
}
