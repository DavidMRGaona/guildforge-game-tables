<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
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
 * @property Carbon|null $email_confirmed_at
 * @property Carbon|null $confirmation_expires_at
 * @property string|null $confirmation_token
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $reminder_sent_at
 * @property GuestConfirmationMode|null $confirmation_mode
 * @property string|null $fingerprint
 * @property bool $from_roster
 * @property ParticipantCancellationReason|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        'email_confirmed_at',
        'confirmation_expires_at',
        'confirmation_token',
        'confirmation_sent_at',
        'confirmation_mode',
        'fingerprint',
        'reminder_sent_at',
        'from_roster',
        'cancellation_reason',
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
            'email_confirmed_at' => 'datetime',
            'confirmation_expires_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'confirmation_mode' => GuestConfirmationMode::class,
            'reminder_sent_at' => 'datetime',
            'from_roster' => 'boolean',
            'cancellation_reason' => ParticipantCancellationReason::class,
        ];
    }

    /**
     * Leaves out the guests whose email is not confirmed and who were not given a seat (mode
     * wait): they take no seat until they confirm. Null-safe on purpose: a row without a mode, or
     * with an account, is always kept.
     *
     * @param  Builder<ParticipantModel>  $query
     * @return Builder<ParticipantModel>
     */
    public function scopeHoldingOrConfirmed(Builder $query): Builder
    {
        return $query->where(fn (Builder $keep): Builder => $keep
            ->whereNotNull('user_id')
            ->orWhereNotNull('email_confirmed_at')
            ->orWhereNull('confirmation_mode')
            ->orWhere('confirmation_mode', '<>', GuestConfirmationMode::Wait->value));
    }

    /**
     * Users, and guests whose email is confirmed: leaves out every unconfirmed guest, whatever
     * their mode (an unconfirmed guest on the waiting list holds its place in hold mode).
     *
     * @param  Builder<ParticipantModel>  $query
     * @return Builder<ParticipantModel>
     */
    public function scopeWithConfirmedEmail(Builder $query): Builder
    {
        return $query->where(fn (Builder $keep): Builder => $keep
            ->whereNotNull('user_id')
            ->orWhereNotNull('email_confirmed_at'));
    }

    /**
     * The same rule as scopeHoldingOrConfirmed(), for loaded rows: an unconfirmed guest without a seat.
     */
    public function isUnconfirmedWaiting(): bool
    {
        return $this->user_id === null
            && $this->email_confirmed_at === null
            && $this->confirmation_mode === GuestConfirmationMode::Wait;
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

        return trim($this->first_name.' '.$this->last_name);
    }
}
