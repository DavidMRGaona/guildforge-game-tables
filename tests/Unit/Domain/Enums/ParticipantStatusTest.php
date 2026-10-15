<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\ParticipantStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParticipantStatusTest extends TestCase
{
    /**
     * @return array<string, array{ParticipantStatus, bool}>
     */
    public static function deletableStatuses(): array
    {
        return [
            'pending' => [ParticipantStatus::Pending, false],
            'confirmed' => [ParticipantStatus::Confirmed, false],
            'waiting list' => [ParticipantStatus::WaitingList, false],
            'cancelled' => [ParticipantStatus::Cancelled, true],
            'rejected' => [ParticipantStatus::Rejected, true],
            'no-show' => [ParticipantStatus::NoShow, false],
        ];
    }

    // Only a registration that no longer holds or waits for a seat, and is not a record of attendance
    #[DataProvider('deletableStatuses')]
    public function test_only_cancelled_and_rejected_registrations_can_be_deleted(ParticipantStatus $status, bool $deletable): void
    {
        $this->assertSame($deletable, $status->canBeDeleted());
    }
}
