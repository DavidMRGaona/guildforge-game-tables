<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\CreationEligibilityDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventCreationEligibilityServiceInterface;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\EarlyAccessTier;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;

/**
 * Service that checks eligibility for creating game tables within a specific event.
 * This service considers event-level configuration overrides in addition to global settings.
 */
final readonly class EventCreationEligibilityService implements EventCreationEligibilityServiceInterface
{
    public function __construct(
        private EventGameTableConfigRepositoryInterface $configRepository,
        private AuthorizationServiceInterface $authorizationService,
        private CreationEligibilityServiceInterface $globalEligibilityService,
    ) {}

    public function canCreateTableForEvent(string $eventId, ?string $userId): CreationEligibilityDTO
    {
        // Check if event has specific config
        $config = $this->configRepository->findByEvent($eventId);

        // If no config exists for this event, fall back to global settings
        if ($config === null) {
            return $this->globalEligibilityService->canCreateTable($userId);
        }

        // Event has explicit config - check if tables are enabled
        if (! $config->isEnabled()) {
            return CreationEligibilityDTO::notEligible('tables_not_enabled_for_event');
        }

        // Check early access restrictions
        $earlyAccessCheck = $this->checkEarlyAccess($config, $userId);
        if ($earlyAccessCheck !== null) {
            return $earlyAccessCheck;
        }

        // If event has an eligibility override, use it
        if ($config->hasEligibilityOverride()) {
            return $this->checkWithOverride($config->eligibilityOverride(), $userId);
        }

        // Event has tables enabled (checked above) and no specific override.
        // This means the event allows table creation - user just needs to be authenticated.
        if ($userId === null) {
            return CreationEligibilityDTO::notEligible('authentication_required');
        }

        return CreationEligibilityDTO::eligible(
            userTier: null,
            canCreateTables: true,
            canCreateCampaigns: false,
        );
    }

    /**
     * Check if creation is restricted by early access dates.
     * Returns null if no restriction applies, otherwise returns the eligibility result.
     */
    private function checkEarlyAccess(EventGameTableConfig $config, ?string $userId): ?CreationEligibilityDTO
    {
        // If early access is not configured, no restriction applies
        if (! $config->hasEarlyAccess()) {
            return null;
        }

        $generalOpenDate = $config->creationOpensAt();
        if ($generalOpenDate === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $earlyAccessTier = $config->earlyAccessTier();

        // Calculate the effective open date for this user
        $effectiveOpenDate = $this->getEffectiveOpenDateForUser(
            $generalOpenDate,
            $earlyAccessTier,
            $userId
        );

        // If creation hasn't opened yet for this user, return eligibleAt
        if ($effectiveOpenDate > $now) {
            return CreationEligibilityDTO::eligibleAt($effectiveOpenDate);
        }

        // Creation is open, no restriction
        return null;
    }

    /**
     * Calculate the effective open date for a user based on early access tier.
     */
    private function getEffectiveOpenDateForUser(
        DateTimeImmutable $generalOpenDate,
        ?EarlyAccessTier $earlyAccessTier,
        ?string $userId
    ): DateTimeImmutable {
        // If no early access tier or no user, use general open date
        if ($earlyAccessTier === null || $userId === null) {
            return $generalOpenDate;
        }

        $user = $this->getUser($userId);
        if ($user === null) {
            return $generalOpenDate;
        }

        // Check if user matches the early access tier
        if ($this->userMatchesEarlyAccessTier($user, $earlyAccessTier)) {
            return $earlyAccessTier->getOpenDate($generalOpenDate);
        }

        return $generalOpenDate;
    }

    /**
     * Check if a user matches the early access tier criteria.
     */
    private function userMatchesEarlyAccessTier(UserModel $user, EarlyAccessTier $tier): bool
    {
        return match ($tier->accessType) {
            CreationAccessLevel::Role => $this->authorizationService->hasAnyRole($user, $tier->allowedRoles ?? []),
            CreationAccessLevel::Permission => $this->authorizationService->can($user, $tier->requiredPermission ?? ''),
            default => false,
        };
    }

    private function checkWithOverride(EligibilityOverride $override, ?string $userId): CreationEligibilityDTO
    {
        // Check based on the override's access level
        if ($override->accessLevel === CreationAccessLevel::Everyone) {
            return CreationEligibilityDTO::eligible(
                userTier: null,
                canCreateTables: true,
                canCreateCampaigns: false,
            );
        }

        // For all other levels, user must be authenticated
        if ($userId === null) {
            return CreationEligibilityDTO::notEligible('authentication_required');
        }

        return match ($override->accessLevel) {
            CreationAccessLevel::Registered => $this->checkRegisteredAccess($userId),
            CreationAccessLevel::Role => $this->checkRoleAccess($userId, $override->allowedRoles ?? []),
            CreationAccessLevel::Permission => $this->checkPermissionAccess($userId, $override->requiredPermission ?? ''),
            default => CreationEligibilityDTO::notEligible('invalid_access_level'),
        };
    }

    private function checkRegisteredAccess(string $userId): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        return CreationEligibilityDTO::eligible(
            userTier: null,
            canCreateTables: true,
            canCreateCampaigns: false,
        );
    }

    /**
     * @param  array<string>  $allowedRoles
     */
    private function checkRoleAccess(string $userId, array $allowedRoles): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        if (empty($allowedRoles)) {
            return CreationEligibilityDTO::notEligible('no_roles_configured');
        }

        if (! $this->authorizationService->hasAnyRole($user, $allowedRoles)) {
            return CreationEligibilityDTO::notEligible('role_not_allowed');
        }

        return CreationEligibilityDTO::eligible(
            userTier: null,
            canCreateTables: true,
            canCreateCampaigns: false,
        );
    }

    private function checkPermissionAccess(string $userId, string $requiredPermission): CreationEligibilityDTO
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return CreationEligibilityDTO::notEligible('user_not_found');
        }

        if ($requiredPermission === '') {
            return CreationEligibilityDTO::notEligible('no_permission_configured');
        }

        if (! $this->authorizationService->can($user, $requiredPermission)) {
            return CreationEligibilityDTO::notEligible('permission_denied');
        }

        return CreationEligibilityDTO::eligible(
            userTier: null,
            canCreateTables: true,
            canCreateCampaigns: false,
        );
    }

    private function getUser(string $userId): ?UserModel
    {
        return UserModel::find($userId);
    }
}
