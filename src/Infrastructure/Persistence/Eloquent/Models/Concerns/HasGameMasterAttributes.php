<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\Concerns;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\GameTables\Domain\Enums\GameMasterRole;

/**
 * Trait for shared game master model logic.
 *
 * @property string $id
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
 * @property-read UserModel|null $user
 * @property-read string $display_name
 */
trait HasGameMasterAttributes
{
    /**
     * Initialize the trait for an instance.
     */
    public function initializeHasGameMasterAttributes(): void
    {
        $this->mergeFillable([
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
        ]);

        $this->mergeCasts([
            'role' => GameMasterRole::class,
            'notify_by_email' => 'boolean',
            'is_name_public' => 'boolean',
        ]);
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

        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
