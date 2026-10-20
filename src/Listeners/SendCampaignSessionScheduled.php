<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\CampaignSessionScheduledNotification;

final readonly class SendCampaignSessionScheduled
{
    public function __construct(
        private GameTableRepositoryInterface $tables,
        private CampaignRepositoryInterface $campaigns,
        private GameTableSettingsReader $settings,
    ) {}

    public function handle(RosterMemberEnrolledInSession $event): void
    {
        if (! $this->settings->isCampaignNotificationEnabled('session_scheduled')) {
            return;
        }

        $user = UserModel::query()->whereKey($event->userId)->whereNull('anonymized_at')->first();

        if ($user === null) {
            return;
        }

        $table = $this->tables->find(new GameTableId($event->gameTableId));

        if ($table === null || $table->campaignId === null) {
            return;
        }

        $campaign = $this->campaigns->find($table->campaignId);

        if ($campaign === null) {
            return;
        }

        Notification::route('mail', $user->email)->notify(new CampaignSessionScheduledNotification(
            userName: $user->name,
            campaignTitle: $campaign->title,
            tableId: $event->gameTableId,
            tableTitle: $table->title,
            tableDate: $table->timeSlot->startsAt->format('d/m/Y H:i'),
            tableLocation: $table->location,
            waiting: $event->waiting,
        ));
    }
}
