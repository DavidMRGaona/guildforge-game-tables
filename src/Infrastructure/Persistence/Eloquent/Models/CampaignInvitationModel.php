<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $email
 * @property string|null $invited_by_user_id
 * @property string $inviter_name
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property int $send_count
 * @property Carbon $last_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CampaignInvitationModel extends Model
{
    use HasUuids;

    protected $table = 'gametables_campaign_invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'campaign_id',
        'email',
        'invited_by_user_id',
        'inviter_name',
        'token_hash',
        'expires_at',
        'send_count',
        'last_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'send_count' => 'integer',
            'last_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CampaignModel, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CampaignModel::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'invited_by_user_id');
    }
}
