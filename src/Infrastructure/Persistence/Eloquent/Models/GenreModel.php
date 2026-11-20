<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $color one of Domain\Enums\GenreColor; read through GenreColor::tryFrom(), so a value written by other means never breaks the catalog
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class GenreModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'gametables_genres';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'key',
        'name',
        'color',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Tables and campaigns that have this genre: while there is any, it is deactivated, never deleted.
     *
     * @return array{tables: int, campaigns: int}
     */
    public function usage(): array
    {
        return [
            'tables' => GameTableModel::query()->whereJsonContains('genres', $this->key)->count(),
            'campaigns' => CampaignModel::query()->whereJsonContains('genres', $this->key)->count(),
        ];
    }

    public function isInUse(): bool
    {
        return GameTableModel::query()->whereJsonContains('genres', $this->key)->exists()
            || CampaignModel::query()->whereJsonContains('genres', $this->key)->exists();
    }
}
