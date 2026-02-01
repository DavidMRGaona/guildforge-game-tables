<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;

/**
 * @property string $event_id
 * @property bool $tables_enabled
 * @property SchedulingMode $scheduling_mode
 * @property array<array{label: string, start_time: string, end_time: string, max_tables: int|null}>|null $time_slots
 * @property LocationMode $location_mode
 * @property string|null $fixed_location
 * @property array{access_level: string, allowed_roles?: array<string>|null, required_permission?: string|null}|null $eligibility_override
 * @property bool $early_access_enabled
 * @property \Carbon\Carbon|null $creation_opens_at
 * @property array{access_type: string, allowed_roles?: array<string>|null, required_permission?: string|null, days_before_opening: int}|null $early_access_tier
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read EventModel $event
 */
final class EventGameTableConfigModel extends Model
{
    protected $table = 'game_tables_event_configs';

    protected $primaryKey = 'event_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tables_enabled' => false,
        'scheduling_mode' => 'free',
        'location_mode' => 'free',
        'early_access_enabled' => false,
    ];

    protected $fillable = [
        'event_id',
        'tables_enabled',
        'scheduling_mode',
        'time_slots',
        'location_mode',
        'fixed_location',
        'eligibility_override',
        'early_access_enabled',
        'creation_opens_at',
        'early_access_tier',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tables_enabled' => 'boolean',
            'scheduling_mode' => SchedulingMode::class,
            'time_slots' => 'array',
            'location_mode' => LocationMode::class,
            'eligibility_override' => 'array',
            'early_access_enabled' => 'boolean',
            'creation_opens_at' => 'datetime',
            'early_access_tier' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventModel, self>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }
}
