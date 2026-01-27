<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;

final readonly class SendGuestRegistrationConfirmation
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(GuestRegistered $event): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if ($gameTable === null) {
            return;
        }

        $tableDate = $gameTable->timeSlot->startsAt->format('d/m/Y H:i');
        $tableLocation = $gameTable->location;

        Notification::route('mail', $event->email)
            ->notify(new GuestRegistrationConfirmation(
                firstName: $event->firstName,
                tableTitle: $gameTable->title,
                tableDate: $tableDate,
                tableLocation: $tableLocation,
                cancellationToken: $event->cancellationToken,
                role: $event->role,
            ));
    }
}
