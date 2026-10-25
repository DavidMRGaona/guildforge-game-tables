<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Events\CampaignJoinRequestRejected;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\CampaignJoinRequestRejectedNotification;

final readonly class SendJoinRequestRejected
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private GameTableSettingsReader $settings,
    ) {}

    public function handle(CampaignJoinRequestRejected $event): void
    {
        if (! $this->settings->isCampaignNotificationEnabled('join_request_rejected')) {
            return;
        }

        $user = UserModel::query()->whereKey($event->userId)->whereNull('anonymized_at')->first();

        if ($user === null) {
            return;
        }

        $campaign = $this->campaigns->find(new CampaignId($event->campaignId));

        if (! $campaign instanceof Campaign) {
            return;
        }

        Notification::route('mail', $user->email)->notify(new CampaignJoinRequestRejectedNotification(
            userName: $user->name,
            campaignTitle: $campaign->title,
            campaignSlug: $campaign->slug,
            reason: $event->reason,
        ));
    }
}
