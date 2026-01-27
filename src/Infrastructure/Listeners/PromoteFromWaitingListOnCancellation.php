<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Events\ParticipantCancelled;

final readonly class PromoteFromWaitingListOnCancellation
{
    public function __construct(
        private RegistrationServiceInterface $registrationService,
    ) {}

    public function handle(ParticipantCancelled $event): void
    {
        // Only promote if the cancelled participant was confirmed (had a slot)
        if (! $event->wasConfirmed) {
            return;
        }

        $this->registrationService->promoteFromWaitingList($event->gameTableId);
    }
}
