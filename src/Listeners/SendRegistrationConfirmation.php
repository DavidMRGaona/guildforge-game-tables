<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Notifications\RegistrationConfirmation;

final readonly class SendRegistrationConfirmation
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(ParticipantRegistered $event): void
    {
        $user = UserModel::find($event->userId);

        if ($user === null || $user->email === null) {
            return;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if ($gameTable === null) {
            return;
        }

        $tableDate = $gameTable->timeSlot->startsAt->format('d/m/Y H:i');
        $tableLocation = $gameTable->location;

        Notification::route('mail', $user->email)
            ->notify(new RegistrationConfirmation(
                participantName: $user->name,
                tableId: $event->gameTableId,
                tableTitle: $gameTable->title,
                tableDate: $tableDate,
                tableLocation: $tableLocation,
                role: $event->role,
            ));
    }
}
