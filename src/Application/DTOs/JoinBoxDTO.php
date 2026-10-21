<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * The join box of a campaign page. State: director, member, pending, finished, not_accepting,
 * full, guest, members_only or can_request.
 */
final readonly class JoinBoxDTO
{
    public function __construct(
        public string $state,
        // d/m/Y, with a pending request
        public ?string $requestedAt = null,
        // For the direction, the roster page
        public ?string $rosterUrl = null,
        // For the direction, the requests awaiting an answer
        public int $pendingRequests = 0,
    ) {}

    /**
     * @return array{state: string, requested_at: string|null, roster_url: string|null, pending_requests: int}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'requested_at' => $this->requestedAt,
            'roster_url' => $this->rosterUrl,
            'pending_requests' => $this->pendingRequests,
        ];
    }
}
