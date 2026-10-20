<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\CampaignRosterRemovedNotification;

final readonly class SendRosterMemberRemoved
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private GameTableSettingsReader $settings,
    ) {}

    public function handle(CampaignRosterMemberRemoved $event): void
    {
        if (! $this->settings->isCampaignNotificationEnabled('roster_removed')) {
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

        Notification::route('mail', $user->email)->notify(new CampaignRosterRemovedNotification(
            userName: $user->name,
            campaignTitle: $campaign->title,
            campaignSlug: $campaign->slug,
        ));
    }
}
