<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Notifications\GuestRegistrationExpiredNotification;

final readonly class SendGuestRegistrationExpiredNotification
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(GuestRegistrationExpired $event): void
    {
        if (! $event->notifyGuest || $event->email === '') {
            return;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if ($gameTable === null) {
            return;
        }

        Notification::route('mail', $event->email)
            ->notify(new GuestRegistrationExpiredNotification(
                firstName: $event->firstName,
                tableTitle: $gameTable->title,
                tableUrl: route('gametables.show', $gameTable->slug),
            ));
    }
}
