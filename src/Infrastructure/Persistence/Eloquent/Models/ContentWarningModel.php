<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\WarningSeverity;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $label
 * @property string|null $description
 * @property WarningSeverity $severity
 * @property string|null $icon
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ContentWarningModel extends Model
{
    use HasUuids;

    protected $table = 'gametables_content_warnings';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'slug',
        'label',
        'description',
        'severity',
        'icon',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => WarningSeverity::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<GameTableModel, $this>
     */
    public function gameTables(): BelongsToMany
    {
        return $this->belongsToMany(
            GameTableModel::class,
            'gametables_table_content_warning',
            'content_warning_id',
            'game_table_id',
        );
    }
}
