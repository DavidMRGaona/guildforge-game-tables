<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeImmutable;

final readonly class CreationEligibilityDTO
{
    public function __construct(
        public bool $eligible,
        public ?string $reason,
        public ?int $userTier,
        public ?DateTimeImmutable $canCreateAt,
        public bool $canCreateTables,
        public bool $canCreateCampaigns,
    ) {}

    public static function eligible(
        ?int $userTier = null,
        bool $canCreateTables = true,
        bool $canCreateCampaigns = false,
    ): self {
        return new self(
            eligible: true,
            reason: null,
            userTier: $userTier,
            canCreateAt: null,
            canCreateTables: $canCreateTables,
            canCreateCampaigns: $canCreateCampaigns,
        );
    }

    public static function notEligible(string $reason): self
    {
        return new self(
            eligible: false,
            reason: $reason,
            userTier: null,
            canCreateAt: null,
            canCreateTables: false,
            canCreateCampaigns: false,
        );
    }

    public static function eligibleAt(
        DateTimeImmutable $canCreateAt,
        ?int $userTier = null,
    ): self {
        return new self(
            eligible: false,
            reason: null,
            userTier: $userTier,
            canCreateAt: $canCreateAt,
            canCreateTables: false,
            canCreateCampaigns: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'reason' => $this->reason,
            'user_tier' => $this->userTier,
            'can_create_at' => $this->canCreateAt?->format('c'),
            'can_create_tables' => $this->canCreateTables,
            'can_create_campaigns' => $this->canCreateCampaigns,
        ];
    }
}
