<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Events\CampaignJoinRequested;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\CampaignJoinRequestReceivedNotification;

final readonly class NotifyDirectionOfJoinRequest
{
    public function __construct(
        private CampaignDirectionInterface $direction,
        private CampaignRepositoryInterface $campaigns,
        private GameTableSettingsReader $settings,
        private CampaignJoinRequestRepositoryInterface $requests,
    ) {}

    public function handle(CampaignJoinRequested $event): void
    {
        if (! $this->settings->isCampaignNotificationEnabled('join_request_received')) {
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

        $request = $this->requests->find(new JoinRequestId($event->requestId));

        foreach ($this->direction->recipients($event->campaignId) as $recipient) {
            Notification::route('mail', $recipient->email)->notify(new CampaignJoinRequestReceivedNotification(
                userName: $recipient->name,
                campaignId: $event->campaignId,
                campaignTitle: $campaign->title,
                playerName: $user->name,
                message: $request?->message,
            ));
        }
    }
}
