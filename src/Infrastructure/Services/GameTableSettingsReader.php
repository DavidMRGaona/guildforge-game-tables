<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Domain\Enums\CreationAccessLevel;

final readonly class GameTableSettingsReader
{
    public function isNotifyOnRegistrationEnabled(): bool
    {
        return (bool) (config('modules.settings.gametables.notifications.notify_on_registration')
            ?? config('game-tables.notifications.notify_on_registration', true));
    }

    public function isNotifyOnCancellationEnabled(): bool
    {
        return (bool) (config('modules.settings.gametables.notifications.notify_on_cancellation')
            ?? config('game-tables.notifications.notify_on_cancellation', true));
    }

    public function isNotifyWaitingListPromotionEnabled(): bool
    {
        return (bool) (config('modules.settings.gametables.notifications.notify_waiting_list_promotion')
            ?? config('game-tables.notifications.notify_waiting_list_promotion', true));
    }

    public function isFrontendCreationEnabled(): bool
    {
        return (bool) (config('modules.settings.gametables.frontend_creation.enabled')
            ?? config('game-tables.frontend_creation.enabled', false));
    }

    public function getAllowedContent(): string
    {
        return (string) (config('modules.settings.gametables.frontend_creation.allowed_content')
            ?? config('game-tables.frontend_creation.allowed_content', 'tables'));
    }

    public function getAccessLevel(): CreationAccessLevel
    {
        $value = (string) (config('modules.settings.gametables.frontend_creation.access_level')
            ?? config('game-tables.frontend_creation.access_level', 'registered'));

        return CreationAccessLevel::from($value);
    }

    /**
     * @return array<string>
     */
    public function getAllowedRoles(): array
    {
        return (array) (config('modules.settings.gametables.frontend_creation.allowed_roles')
            ?? config('game-tables.frontend_creation.allowed_roles', []));
    }

    public function getRequiredPermission(): ?string
    {
        return config('modules.settings.gametables.frontend_creation.required_permission')
            ?? config('game-tables.frontend_creation.required_permission');
    }

    /**
     * @return array<array{tier: int, type: string, value: ?string, days_before: int}>
     */
    public function getPriorityTiers(): array
    {
        return (array) (config('modules.settings.gametables.frontend_creation.priority_tiers')
            ?? config('game-tables.frontend_creation.priority_tiers', []));
    }

    public function getPublicationMode(): string
    {
        return (string) (config('modules.settings.gametables.frontend_creation.publication.mode')
            ?? config('game-tables.frontend_creation.publication.mode', 'approval'));
    }

    /**
     * @return array<string>
     */
    public function getAutoPublishRoles(): array
    {
        return (array) (config('modules.settings.gametables.frontend_creation.publication.auto_publish_roles')
            ?? config('game-tables.frontend_creation.publication.auto_publish_roles', []));
    }

    /**
     * @return array<string>
     */
    public function getAutoPublishPermissions(): array
    {
        return (array) (config('modules.settings.gametables.frontend_creation.publication.auto_publish_permissions')
            ?? config('game-tables.frontend_creation.publication.auto_publish_permissions', []));
    }

    public function canCreateTables(): bool
    {
        $allowed = $this->getAllowedContent();

        return in_array($allowed, ['tables', 'both'], true);
    }

    public function canCreateCampaigns(): bool
    {
        $allowed = $this->getAllowedContent();

        return in_array($allowed, ['campaigns', 'both'], true);
    }
}
