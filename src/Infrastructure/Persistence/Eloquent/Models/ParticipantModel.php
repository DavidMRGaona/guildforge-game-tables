<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;

/**
 * @property string $id
 * @property string $game_table_id
 * @property string|null $user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $cancellation_token
 * @property ParticipantRole $role
 * @property ParticipantStatus $status
 * @property int|null $waiting_list_position
 * @property string|null $notes
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read UserModel|null $user
 * @property-read GameTableModel $gameTable
 */
final class ParticipantModel extends Model
{
    use HasUuids;

    protected $table = 'gametables_participants';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'game_table_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cancellation_token',
        'role',
        'status',
        'waiting_list_position',
        'notes',
        'confirmed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'status' => ParticipantStatus::class,
            'waiting_list_position' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<GameTableModel, $this>
     */
    public function gameTable(): BelongsTo
    {
        return $this->belongsTo(GameTableModel::class, 'game_table_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * Get the display name for this participant.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user !== null) {
            return $this->user->name;
        }

        return trim($this->first_name . ' ' . $this->last_name);
    }
}
