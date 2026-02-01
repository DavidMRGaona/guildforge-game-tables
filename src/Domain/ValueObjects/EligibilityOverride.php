<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;

/**
 * Represents an event-level override for game table creation eligibility.
 */
final readonly class EligibilityOverride
{
    /**
     * @param  array<string>|null  $allowedRoles
     */
    public function __construct(
        public CreationAccessLevel $accessLevel,
        public ?array $allowedRoles = null,
        public ?string $requiredPermission = null,
    ) {
        // Validate role-based access requires roles
        if ($accessLevel === CreationAccessLevel::Role && empty($allowedRoles)) {
            throw new InvalidArgumentException('Allowed roles must be specified for role-based access.');
        }

        // Validate permission-based access requires a permission
        if ($accessLevel === CreationAccessLevel::Permission && ($requiredPermission === null || $requiredPermission === '')) {
            throw new InvalidArgumentException('Required permission must be specified for permission-based access.');
        }
    }

    /**
     * @param  array<string>|null  $allowedRoles
     */
    public static function create(
        CreationAccessLevel $accessLevel,
        ?array $allowedRoles = null,
        ?string $requiredPermission = null,
    ): self {
        return new self($accessLevel, $allowedRoles, $requiredPermission);
    }

    /**
     * @param  array{access_level: string, allowed_roles?: array<string>|null, required_permission?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessLevel: CreationAccessLevel::from($data['access_level']),
            allowedRoles: $data['allowed_roles'] ?? null,
            requiredPermission: $data['required_permission'] ?? null,
        );
    }

    public function requiresAuthentication(): bool
    {
        return $this->accessLevel->requiresAuthentication();
    }

    public function isRoleBased(): bool
    {
        return $this->accessLevel === CreationAccessLevel::Role;
    }

    public function isPermissionBased(): bool
    {
        return $this->accessLevel === CreationAccessLevel::Permission;
    }

    public function equals(self $other): bool
    {
        return $this->accessLevel === $other->accessLevel
            && $this->allowedRoles === $other->allowedRoles
            && $this->requiredPermission === $other->requiredPermission;
    }

    /**
     * @return array{access_level: string, allowed_roles: array<string>|null, required_permission: string|null}
     */
    public function toArray(): array
    {
        return [
            'access_level' => $this->accessLevel->value,
            'allowed_roles' => $this->allowedRoles,
            'required_permission' => $this->requiredPermission,
        ];
    }
}
