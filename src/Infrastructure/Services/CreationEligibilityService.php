<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\DTOs\CreationEligibilityDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;

final readonly class CreationEligibilityService implements CreationEligibilityServiceInterface
{
    public function __construct(
        private GameTableSettingsReader $settingsReader,
        private AuthorizationServiceInterface $authorizationService,
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

        // Every level needs a session
        if ($userId === null) {
            return CreationEligibilityDTO::notEligible(
                reason: 'authentication_required',
                requiresAuthentication: true,
            );
        }

        // Check based on access level
        return match ($accessLevel) {
            CreationAccessLevel::Registered => $this->checkRegisteredAccess($userId),
            CreationAccessLevel::Role => $this->checkRoleAccess($userId),
            CreationAccessLevel::Permission => $this->checkPermissionAccess($userId),
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

        // Every level needs a session
        if ($userId === null) {
            return CreationEligibilityDTO::notEligible(
                reason: 'authentication_required',
                requiresAuthentication: true,
            );
        }

        // Check based on access level
        return match ($accessLevel) {
            CreationAccessLevel::Registered => $this->checkRegisteredAccessForCampaign($userId),
            CreationAccessLevel::Role => $this->checkRoleAccessForCampaign($userId),
            CreationAccessLevel::Permission => $this->checkPermissionAccessForCampaign($userId),
        };
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
        if (! $user instanceof UserModel) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        return CreationEligibilityDTO::eligible(
            canCreateTables: true,
            canCreateCampaigns: $this->settingsReader->canCreateCampaigns(),
        );
    }

    private function checkRoleAccess(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if (! $user instanceof UserModel) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $allowedRoles = $this->settingsReader->getAllowedRoles();
        if ($allowedRoles === []) {
            return CreationEligibilityDTO::notEligible('no_roles_configured');
        }

        if (! $this->authorizationService->hasAnyRole($user, $allowedRoles)) {
            return CreationEligibilityDTO::notEligible('role_not_allowed');
        }

        return CreationEligibilityDTO::eligible(
            canCreateTables: true,
            canCreateCampaigns: $this->settingsReader->canCreateCampaigns(),
        );
    }

    private function checkPermissionAccess(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if (! $user instanceof UserModel) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $requiredPermission = $this->settingsReader->getRequiredPermission();
        if ($requiredPermission === null) {
            return CreationEligibilityDTO::notEligible('no_permission_configured');
        }

        if (! $this->authorizationService->can($user, $requiredPermission)) {
            return CreationEligibilityDTO::notEligible('permission_denied');
        }

        return CreationEligibilityDTO::eligible(
            canCreateTables: true,
            canCreateCampaigns: $this->settingsReader->canCreateCampaigns(),
        );
    }

    private function checkRegisteredAccessForCampaign(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if (! $user instanceof UserModel) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        return CreationEligibilityDTO::eligible(
            canCreateTables: $this->settingsReader->canCreateTables(),
            canCreateCampaigns: true,
        );
    }

    private function checkRoleAccessForCampaign(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if (! $user instanceof UserModel) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $allowedRoles = $this->settingsReader->getAllowedRoles();
        if ($allowedRoles === []) {
            return CreationEligibilityDTO::notEligible('no_roles_configured');
        }

        if (! $this->authorizationService->hasAnyRole($user, $allowedRoles)) {
            return CreationEligibilityDTO::notEligible('role_not_allowed');
        }

        return CreationEligibilityDTO::eligible(
            canCreateTables: $this->settingsReader->canCreateTables(),
            canCreateCampaigns: true,
        );
    }

    private function checkPermissionAccessForCampaign(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if (! $user instanceof UserModel) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        $requiredPermission = $this->settingsReader->getRequiredPermission();
        if ($requiredPermission === null) {
            return CreationEligibilityDTO::notEligible('no_permission_configured');
        }

        if (! $this->authorizationService->can($user, $requiredPermission)) {
            return CreationEligibilityDTO::notEligible('permission_denied');
        }

        return CreationEligibilityDTO::eligible(
            canCreateTables: $this->settingsReader->canCreateTables(),
            canCreateCampaigns: true,
        );
    }

    private function getUser(string $userId): ?UserModel
    {
        return UserModel::find($userId);
    }
}
