<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $user_id
 * @property JoinRequestStatus $status
 * @property string|null $message
 * @property string|null $decision_reason
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CampaignJoinRequestModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'gametables_campaign_join_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'campaign_id',
        'user_id',
        'status',
        'message',
        'decision_reason',
        'decided_by',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JoinRequestStatus::class,
            'decided_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
