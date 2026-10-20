<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Modules\GameTables\Application\DTOs\EnrolledSessionDTO;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;

final readonly class CampaignRosterSessionSync implements CampaignRosterSessionSyncInterface
{
    public function __construct(
        private RegistrationServiceInterface $registrations,
        private GameTableRepositoryInterface $gameTables,
    ) {}

    public function onSessionPublished(string $gameTableId): int
    {
        // Under the table lock; the events go out once it is released
        $placed = $this->registrations->enrollRoster($gameTableId);

        foreach ($placed as $participant) {
            if ($participant->userId === null) {
                continue;
            }

            RosterMemberEnrolledInSession::dispatch(
                $participant->id,
                $participant->gameTableId,
                $participant->userId,
                $participant->status === ParticipantStatus::WaitingList,
            );
        }

        return count($placed);
    }

    public function onMemberJoined(string $campaignId, string $userId): array
    {
        $now = CarbonImmutable::now();
        $enrolled = [];

        // By start; enrollRoster checks the window again under each table lock
        foreach ($this->gameTables->getByCampaign(new CampaignId($campaignId)) as $session) {
            if (! $session->admitsRosterEnrollment($now)) {
                continue;
            }

            foreach ($this->registrations->enrollRoster($session->id->value, $userId) as $participant) {
                $enrolled[] = new EnrolledSessionDTO(
                    $participant->gameTableId,
                    $participant->status === ParticipantStatus::WaitingList,
                );
            }
        }

        return $enrolled;
    }
}
