<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\CampaignInvitationAcceptedNotification;

final readonly class NotifyDirectionOfInvitationAccepted
{
    public function __construct(
        private CampaignDirectionInterface $direction,
        private CampaignRepositoryInterface $campaigns,
        private GameTableSettingsReader $settings,
    ) {}

    public function handle(CampaignInvitationAccepted $event): void
    {
        if (! $this->settings->isCampaignNotificationEnabled('invitation_accepted')) {
            return;
        }

        $user = UserModel::query()->whereKey($event->userId)->whereNull('anonymized_at')->first();

        if ($user === null) {
            return;
        }

        $campaign = $this->campaigns->find(new CampaignId($event->campaignId));

        if (!$campaign instanceof \Modules\GameTables\Domain\Entities\Campaign) {
            return;
        }

        foreach ($this->direction->recipients($event->campaignId) as $recipient) {
            Notification::route('mail', $recipient->email)->notify(new CampaignInvitationAcceptedNotification(
                userName: $recipient->name,
                campaignId: $event->campaignId,
                campaignTitle: $campaign->title,
                playerName: $user->name,
            ));
        }
    }
}
