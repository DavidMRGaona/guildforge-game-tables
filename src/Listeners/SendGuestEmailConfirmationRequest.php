<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Notifications\GuestEmailConfirmationRequest;

final readonly class SendGuestEmailConfirmationRequest
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(GuestConfirmationRequested $event): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if ($gameTable === null || $event->email === '' || $event->confirmationToken === '') {
            return;
        }

        Notification::route('mail', $event->email)
            ->notify(new GuestEmailConfirmationRequest(
                firstName: $event->firstName,
                tableTitle: $gameTable->title,
                tableDate: $gameTable->timeSlot->startsAt->format('d/m/Y H:i'),
                confirmationUrl: route('gametables.confirm-guest', ['token' => $event->confirmationToken]),
                holdsPlace: $event->holdsPlace,
                deadline: $event->deadline,
            ));
    }
}
