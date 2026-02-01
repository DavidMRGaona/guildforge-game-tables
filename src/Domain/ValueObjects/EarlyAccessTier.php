<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;

/**
 * Represents an early access tier for game table creation.
 * Users matching this tier can create tables before the general opening date.
 */
final readonly class EarlyAccessTier
{
    /**
     * @param  array<string>|null  $allowedRoles
     */
    public function __construct(
        public CreationAccessLevel $accessType,
        public ?array $allowedRoles,
        public ?string $requiredPermission,
        public int $daysBeforeOpening,
    ) {
        // Validate role-based access requires roles
        if ($accessType === CreationAccessLevel::Role && empty($allowedRoles)) {
            throw new InvalidArgumentException('Allowed roles must be specified for role-based early access.');
        }

        // Validate permission-based access requires a permission
        if ($accessType === CreationAccessLevel::Permission && ($requiredPermission === null || $requiredPermission === '')) {
            throw new InvalidArgumentException('Required permission must be specified for permission-based early access.');
        }

        // Validate days before opening is positive
        if ($daysBeforeOpening < 1) {
            throw new InvalidArgumentException('Days before opening must be at least 1.');
        }
    }

    /**
     * @param  array<string>|null  $allowedRoles
     */
    public static function create(
        CreationAccessLevel $accessType,
        ?array $allowedRoles = null,
        ?string $requiredPermission = null,
        int $daysBeforeOpening = 3,
    ): self {
        return new self($accessType, $allowedRoles, $requiredPermission, $daysBeforeOpening);
    }

    /**
     * Calculate the early access open date based on the general opening date.
     */
    public function getOpenDate(DateTimeImmutable $generalOpenDate): DateTimeImmutable
    {
        return $generalOpenDate->modify("-{$this->daysBeforeOpening} days");
    }

    public function isRoleBased(): bool
    {
        return $this->accessType === CreationAccessLevel::Role;
    }

    public function isPermissionBased(): bool
    {
        return $this->accessType === CreationAccessLevel::Permission;
    }

    public function equals(self $other): bool
    {
        return $this->accessType === $other->accessType
            && $this->allowedRoles === $other->allowedRoles
            && $this->requiredPermission === $other->requiredPermission
            && $this->daysBeforeOpening === $other->daysBeforeOpening;
    }

    /**
     * @param  array{access_type: string, allowed_roles?: array<string>|null, required_permission?: string|null, days_before_opening: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessType: CreationAccessLevel::from($data['access_type']),
            allowedRoles: $data['allowed_roles'] ?? null,
            requiredPermission: $data['required_permission'] ?? null,
            daysBeforeOpening: $data['days_before_opening'],
        );
    }

    /**
     * @return array{access_type: string, allowed_roles: array<string>|null, required_permission: string|null, days_before_opening: int}
     */
    public function toArray(): array
    {
        return [
            'access_type' => $this->accessType->value,
            'allowed_roles' => $this->allowedRoles,
            'required_permission' => $this->requiredPermission,
            'days_before_opening' => $this->daysBeforeOpening,
        ];
    }
}
