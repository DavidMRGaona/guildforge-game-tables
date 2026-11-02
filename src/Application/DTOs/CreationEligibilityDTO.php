<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;

final readonly class CreationEligibilityDTO
{
    public function __construct(
        public bool $eligible,
        public ?string $reason,
        public ?DateTimeImmutable $canCreateAt,
        public bool $canCreateTables,
        public bool $canCreateCampaigns,
        public bool $hasEarlyAccess = false,
        public ?DateTimeImmutable $publicOpenDate = null,
        public bool $requiresAuthentication = false,
    ) {}

    public static function eligible(
        bool $canCreateTables = true,
        bool $canCreateCampaigns = false,
        bool $hasEarlyAccess = false,
    ): self {
        return new self(
            eligible: true,
            reason: null,
            canCreateAt: null,
            canCreateTables: $canCreateTables,
            canCreateCampaigns: $canCreateCampaigns,
            hasEarlyAccess: $hasEarlyAccess,
            requiresAuthentication: false,
        );
    }

    public static function notEligible(
        string $reason,
        bool $requiresAuthentication = false,
    ): self {
        return new self(
            eligible: false,
            reason: $reason,
            canCreateAt: null,
            canCreateTables: false,
            canCreateCampaigns: false,
            hasEarlyAccess: false,
            requiresAuthentication: $requiresAuthentication,
        );
    }

    public static function eligibleAt(
        DateTimeImmutable $canCreateAt,
        bool $hasEarlyAccess = false,
        ?DateTimeImmutable $publicOpenDate = null,
    ): self {
        return new self(
            eligible: false,
            reason: null,
            canCreateAt: $canCreateAt,
            canCreateTables: false,
            canCreateCampaigns: false,
            hasEarlyAccess: $hasEarlyAccess,
            publicOpenDate: $publicOpenDate,
            requiresAuthentication: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $timezone = app(TimezoneServiceInterface::class);

        return [
            'eligible' => $this->eligible,
            'reason' => $this->reason,
            'can_create_at' => $timezone->iso($this->canCreateAt),
            'can_create_tables' => $this->canCreateTables,
            'can_create_campaigns' => $this->canCreateCampaigns,
            'has_early_access' => $this->hasEarlyAccess,
            'public_open_date' => $timezone->iso($this->publicOpenDate),
            'requires_authentication' => $this->requiresAuthentication,
        ];
    }
}
