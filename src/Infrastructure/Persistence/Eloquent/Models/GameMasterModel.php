<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\GameMasterRole;

/**
 * @property string $id
 * @property string $game_table_id
 * @property string|null $user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property GameMasterRole $role
 * @property string|null $custom_title
 * @property bool $notify_by_email
 * @property bool $is_name_public
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read UserModel|null $user
 * @property-read GameTableModel $gameTable
 */
final class GameMasterModel extends Model
{
    use HasUuids;

    protected $table = 'gametables_game_masters';

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
        'role',
        'custom_title',
        'notify_by_email',
        'is_name_public',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => GameMasterRole::class,
            'notify_by_email' => 'boolean',
            'is_name_public' => 'boolean',
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
     * Get the display name for this game master.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user !== null) {
            return $this->user->name;
        }

        return trim($this->first_name . ' ' . $this->last_name);
    }
}
