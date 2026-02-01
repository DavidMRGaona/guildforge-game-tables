<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\CreationEligibilityDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\Memberships\Application\Services\MemberServiceInterface;

final readonly class CreationEligibilityService implements CreationEligibilityServiceInterface
{
    public function __construct(
        private GameTableSettingsReader $settingsReader,
        private AuthorizationServiceInterface $authorizationService,
        private ?MemberServiceInterface $memberService = null,
    ) {}

    public function canCreateTable(?string $userId): CreationEligibilityDTO
    {
        // 1. Check if frontend creation is enabled
        if (! $this->settingsReader->isFrontendCreationEnabled()) {
            return CreationEligibilityDTO::notEligible('frontend_creation_disabled');
        }

        // 2. Check if tables are allowed
        if (! $this->settingsReader->canCreateTables()) {
            return CreationEligibilityDTO::notEligible('tables_not_allowed');
        }

        // 3. Check access level
        $accessLevel = $this->settingsReader->getAccessLevel();

        if ($accessLevel === CreationAccessLevel::Everyone) {
            return CreationEligibilityDTO::eligible(
                userTier: null,
                canCreateTables: true,
                canCreateCampaigns: $this->settingsReader->canCreateCampaigns(),
            );
        }

        // For all other levels, user must be authenticated
        if ($userId === null) {
            return CreationEligibilityDTO::notEligible('authentication_required');
        }

        // Check based on access level
        return match ($accessLevel) {
            CreationAccessLevel::Registered => $this->checkRegisteredAccess($userId),
            CreationAccessLevel::Role => $this->checkRoleAccess($userId),
            CreationAccessLevel::Permission => $this->checkPermissionAccess($userId),
            default => CreationEligibilityDTO::notEligible('invalid_access_level'),
        };
    }

    public function canCreateCampaign(?string $userId): CreationEligibilityDTO
    {
        // 1. Check if frontend creation is enabled
        if (! $this->settingsReader->isFrontendCreationEnabled()) {
            return CreationEligibilityDTO::notEligible('frontend_creation_disabled');
        }

        // 2. Check if campaigns are allowed
        if (! $this->settingsReader->canCreateCampaigns()) {
            return CreationEligibilityDTO::notEligible('campaigns_not_allowed');
        }

        // 3. Check access level
        $accessLevel = $this->settingsReader->getAccessLevel();

        if ($accessLevel === CreationAccessLevel::Everyone) {
            return CreationEligibilityDTO::eligible(
                userTier: null,
                canCreateTables: $this->settingsReader->canCreateTables(),
                canCreateCampaigns: true,
            );
        }

        // For all other levels, user must be authenticated
        if ($userId === null) {
            return CreationEligibilityDTO::notEligible('authentication_required');
        }

        // Check based on access level
        return match ($accessLevel) {
            CreationAccessLevel::Registered => $this->checkRegisteredAccessForCampaign($userId),
            CreationAccessLevel::Role => $this->checkRoleAccessForCampaign($userId),
            CreationAccessLevel::Permission => $this->checkPermissionAccessForCampaign($userId),
            default => CreationEligibilityDTO::notEligible('invalid_access_level'),
        };
    }

    public function getCreationOpenDate(?string $userId, DateTimeImmutable $eventStartDate): ?DateTimeImmutable
    {
        $tier = $this->getUserPriorityTier($userId);
        if ($tier === null) {
            return $eventStartDate; // No priority, opens at event start
        }

        $tiers = $this->settingsReader->getPriorityTiers();
        foreach ($tiers as $tierConfig) {
            if ($tierConfig['tier'] === $tier) {
                return $eventStartDate->modify("-{$tierConfig['days_before']} days");
            }
        }

        return $eventStartDate;
    }

    public function getUserPriorityTier(?string $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $tiers = $this->settingsReader->getPriorityTiers();
        $user = $this->getUser($userId);

        if ($user === null) {
            return null;
        }

        foreach ($tiers as $tierConfig) {
            $matches = match ($tierConfig['type']) {
                'role' => $this->authorizationService->hasRole($user, $tierConfig['value'] ?? ''),
                'permission' => $this->authorizationService->can($user, $tierConfig['value'] ?? ''),
                default => false,
            };

            if ($matches) {
                return $tierConfig['tier'];
            }
        }

        return null;
    }

    public function isTableCreationEnabled(): bool
    {
        return $this->settingsReader->isFrontendCreationEnabled()
            && $this->settingsReader->canCreateTables();
    }

    public function isCampaignCreationEnabled(): bool
    {
        return $this->settingsReader->isFrontendCreationEnabled()
            && $this->settingsReader->canCreateCampaigns();
    }

    private function checkRegisteredAccess(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $userTier = $this->getUserPriorityTier($userId);

        return CreationEligibilityDTO::eligible(
            userTier: $userTier,
            canCreateTables: true,
            canCreateCampaigns: $this->settingsReader->canCreateCampaigns(),
        );
    }

    private function checkRoleAccess(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $allowedRoles = $this->settingsReader->getAllowedRoles();
        if (empty($allowedRoles)) {
            return CreationEligibilityDTO::notEligible('no_roles_configured');
        }

        if (! $this->authorizationService->hasAnyRole($user, $allowedRoles)) {
            return CreationEligibilityDTO::notEligible('role_not_allowed');
        }

        $userTier = $this->getUserPriorityTier($userId);

        return CreationEligibilityDTO::eligible(
            userTier: $userTier,
            canCreateTables: true,
            canCreateCampaigns: $this->settingsReader->canCreateCampaigns(),
        );
    }

    private function checkPermissionAccess(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $requiredPermission = $this->settingsReader->getRequiredPermission();
        if ($requiredPermission === null) {
            return CreationEligibilityDTO::notEligible('no_permission_configured');
        }

        if (! $this->authorizationService->can($user, $requiredPermission)) {
            return CreationEligibilityDTO::notEligible('permission_denied');
        }

        $userTier = $this->getUserPriorityTier($userId);

        return CreationEligibilityDTO::eligible(
            userTier: $userTier,
            canCreateTables: true,
            canCreateCampaigns: $this->settingsReader->canCreateCampaigns(),
        );
    }

    private function checkRegisteredAccessForCampaign(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $userTier = $this->getUserPriorityTier($userId);

        return CreationEligibilityDTO::eligible(
            userTier: $userTier,
            canCreateTables: $this->settingsReader->canCreateTables(),
            canCreateCampaigns: true,
        );
    }

    private function checkRoleAccessForCampaign(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $allowedRoles = $this->settingsReader->getAllowedRoles();
        if (empty($allowedRoles)) {
            return CreationEligibilityDTO::notEligible('no_roles_configured');
        }

        if (! $this->authorizationService->hasAnyRole($user, $allowedRoles)) {
            return CreationEligibilityDTO::notEligible('role_not_allowed');
        }

        $userTier = $this->getUserPriorityTier($userId);

        return CreationEligibilityDTO::eligible(
            userTier: $userTier,
            canCreateTables: $this->settingsReader->canCreateTables(),
            canCreateCampaigns: true,
        );
    }

    private function checkPermissionAccessForCampaign(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $requiredPermission = $this->settingsReader->getRequiredPermission();
        if ($requiredPermission === null) {
            return CreationEligibilityDTO::notEligible('no_permission_configured');
        }

        if (! $this->authorizationService->can($user, $requiredPermission)) {
            return CreationEligibilityDTO::notEligible('permission_denied');
        }

        $userTier = $this->getUserPriorityTier($userId);

        return CreationEligibilityDTO::eligible(
            userTier: $userTier,
            canCreateTables: $this->settingsReader->canCreateTables(),
            canCreateCampaigns: true,
        );
    }

    private function getUser(string $userId): ?UserModel
    {
        return UserModel::find($userId);
    }
}
