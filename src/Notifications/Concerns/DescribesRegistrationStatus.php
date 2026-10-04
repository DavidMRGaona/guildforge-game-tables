<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications\Concerns;

use Modules\GameTables\Domain\Enums\ParticipantStatus;

/**
 * Registration emails are sent once, right after signing up, so their copy must
 * reflect the resulting status instead of always announcing a confirmation.
 */
trait DescribesRegistrationStatus
{
    private function registrationStatusSubject(ParticipantStatus $status, string $tableTitle): string
    {
        $key = match ($status) {
            ParticipantStatus::Confirmed => 'subject_confirmed',
            ParticipantStatus::WaitingList => 'subject_waiting_list',
            default => 'subject_pending',
        };

        return __("game-tables::emails.registration_status.{$key}", ['tableTitle' => $tableTitle]);
    }

    private function registrationStatusLine(ParticipantStatus $status, ?int $waitingListPosition): string
    {
        return match ($status) {
            ParticipantStatus::Confirmed => __('game-tables::emails.registration_status.confirmed'),
            ParticipantStatus::WaitingList => __('game-tables::emails.registration_status.waiting_list', [
                'position' => (string) $waitingListPosition,
            ]),
            default => __('game-tables::emails.registration_status.pending'),
        };
    }
}
