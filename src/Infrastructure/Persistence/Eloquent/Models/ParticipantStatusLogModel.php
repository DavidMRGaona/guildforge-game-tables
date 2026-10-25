<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\StatusLogActorKind;

/**
 * One change to a registration's status (ParticipantStatusObserver). Rows are only ever inserted,
 * and pruned 12 months after their table started (game-tables:prune-participant-status-log).
 *
 * @property string $id
 * @property string $game_table_id
 * @property string|null $participant_id
 * @property string|null $participant_user_id
 * @property string|null $guest_name
 * @property string|null $actor_user_id
 * @property StatusLogActorKind $actor_kind
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property-read UserModel|null $participantUser
 * @property-read UserModel|null $actor
 */
final class ParticipantStatusLogModel extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'gametables_participant_status_log';

    protected $keyType = 'string';

    public $incrementing = false;

    // Microseconds, so the changes of one request keep their order (see the migration)
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_table_id',
        'participant_id',
        'participant_user_id',
        'guest_name',
        'actor_user_id',
        'actor_kind',
        'from_status',
        'to_status',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_kind' => StatusLogActorKind::class,
            'created_at' => 'datetime',
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
    public function participantUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'participant_user_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'actor_user_id');
    }
}
