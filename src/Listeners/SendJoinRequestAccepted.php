<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\CampaignJoinRequestAcceptedNotification;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Listeners\Concerns\DescribesRosterSessions;

final readonly class SendJoinRequestAccepted
{
    use DescribesRosterSessions;

    public function __construct(
        private GameTableRepositoryInterface $tables,
        private CampaignRepositoryInterface $campaigns,
        private GameTableSettingsReader $settings,
    ) {}

    public function handle(CampaignJoinRequestAccepted $event): void
    {
        if (! $this->settings->isCampaignNotificationEnabled('join_request_accepted')) {
            return;
        }

        $user = UserModel::query()->whereKey($event->userId)->whereNull('anonymized_at')->first();

        if ($user === null) {
            return;
        }

        $campaign = $this->campaigns->find(new CampaignId($event->campaignId));

        if ($campaign === null) {
            return;
        }

        Notification::route('mail', $user->email)->notify(new CampaignJoinRequestAcceptedNotification(
            userName: $user->name,
            campaignTitle: $campaign->title,
            campaignSlug: $campaign->slug,
            sessions: $this->describeSessions($this->tables, $event->sessions),
        ));
    }
}
