<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * The campaign side of a session page: whether the campaign is closed to outsiders and, for a
 * roster member, where they stand in the session and whether they can take their seat back.
 */
final readonly class CampaignSessionBoxDTO
{
    public function __construct(
        public string $campaignTitle,
        // Null while the campaign is not published: it has no public page
        public ?string $campaignSlug,
        public bool $closed,
        public bool $isRosterMember,
        // enrolled, waiting, absent or none for a roster member; null otherwise
        public ?string $rosterState,
        // The place among those waiting, not the stored position
        public ?int $waitingListPosition,
        public bool $canRejoin,
        // Why a member without a seat cannot take it back now
        public ?string $rejoinRefusal,
    ) {}

    /**
     * @return array{campaign_title: string, campaign_slug: string|null, closed: bool, is_roster_member: bool, roster_state: string|null, waiting_list_position: int|null, can_rejoin: bool, rejoin_refusal: string|null}
     */
    public function toArray(): array
    {
        return [
            'campaign_title' => $this->campaignTitle,
            'campaign_slug' => $this->campaignSlug,
            'closed' => $this->closed,
            'is_roster_member' => $this->isRosterMember,
            'roster_state' => $this->rosterState,
            'waiting_list_position' => $this->waitingListPosition,
            'can_rejoin' => $this->canRejoin,
            'rejoin_refusal' => $this->rejoinRefusal,
        ];
    }
}
