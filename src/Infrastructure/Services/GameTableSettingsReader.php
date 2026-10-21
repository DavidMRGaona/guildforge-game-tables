<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\GuestAccountsMode;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\RegistrationType;

/**
 * Reads the module settings: the values saved in the panel
 * (config 'modules.settings.game-tables') merged over the shipped defaults
 * (config/settings.php of the module).
 */
final class GameTableSettingsReader
{
    /** Keys of the campaign roster emails under `campaign_notifications` */
    public const array CAMPAIGN_NOTIFICATIONS = [
        'session_scheduled',
        'join_request_received',
        'join_request_accepted',
        'join_request_rejected',
        'roster_added',
        'invitation_accepted',
        'roster_removed',
        'player_left',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $shippedDefaults = null;

    /**
     * Shipped defaults: the module's config/settings.php.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        if (self::$shippedDefaults === null) {
            $file = dirname(__DIR__, 3).'/config/settings.php';
            $defaults = is_file($file) ? (static fn (string $path): mixed => require $path)($file) : [];
            self::$shippedDefaults = is_array($defaults) ? $defaults : [];
        }

        return self::$shippedDefaults;
    }

    /**
     * Effective settings: panel values over the shipped defaults (a list replaces the default list whole).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = config('modules.settings.game-tables');

        return is_array($stored) ? self::merge($this->defaults(), $stored) : $this->defaults();
    }

    public function isNotifyOnRegistrationEnabled(): bool
    {
        return $this->bool('notifications.notify_on_registration', true);
    }

    public function isNotifyOnCancellationEnabled(): bool
    {
        return $this->bool('notifications.notify_on_cancellation', true);
    }

    public function isNotifyWaitingListPromotionEnabled(): bool
    {
        return $this->bool('notifications.notify_waiting_list_promotion', true);
    }

    public function isCampaignNotificationEnabled(string $key): bool
    {
        if (! in_array($key, self::CAMPAIGN_NOTIFICATIONS, true)) {
            throw new InvalidArgumentException("Unknown campaign notification [{$key}].");
        }

        return $this->bool('campaign_notifications.'.$key, true);
    }

    /**
     * The reminder before a table: off until an admin enables it.
     */
    public function isReminderEnabled(): bool
    {
        return $this->bool('notifications.send_reminder', false);
    }

    public function reminderHoursBefore(): int
    {
        $hours = $this->int('notifications.reminder_hours_before', 24);

        return $hours >= 1 ? min($hours, 168) : 24;
    }

    public function isMembershipIntegrationEnabled(): bool
    {
        return $this->bool('membership_integration.enabled', true);
    }

    public function isWaitingListEnabled(): bool
    {
        return $this->bool('registration.waiting_list_enabled', false);
    }

    public function isAutoStatusEnabled(): bool
    {
        return $this->bool('automation.auto_status_enabled', true);
    }

    public function guestConfirmationMode(): GuestConfirmationMode
    {
        $value = $this->get('guest_confirmation.mode');

        return (is_string($value) ? GuestConfirmationMode::tryFrom($value) : null) ?? GuestConfirmationMode::Hold;
    }

    public function guestConfirmationHoldHours(): int
    {
        return max(1, $this->int('guest_confirmation.hold_hours', 2));
    }

    public function maxUnconfirmedHeldSeats(): int
    {
        return max(0, $this->int('guest_confirmation.max_unconfirmed_held', 2));
    }

    public function guestAccountsMode(): GuestAccountsMode
    {
        $value = $this->get('guest_accounts.mode');

        return (is_string($value) ? GuestAccountsMode::tryFrom($value) : null) ?? GuestAccountsMode::RequireLogin;
    }

    public function guestRateLimitPerMinute(): int
    {
        return max(1, $this->int('guest_rate_limit.per_minute', 5));
    }

    public function guestRateLimitPerHour(): int
    {
        return max(1, $this->int('guest_rate_limit.per_hour', 20));
    }

    public function duplicateSignalWindowHours(): int
    {
        return max(1, $this->int('duplicate_signal.window_hours', 24));
    }

    public function defaultDurationMinutes(): int
    {
        return $this->int('defaults.duration_minutes', 240);
    }

    public function defaultMinPlayers(): int
    {
        return $this->int('defaults.min_players', 3);
    }

    public function defaultMaxPlayers(): int
    {
        return $this->int('defaults.max_players', 5);
    }

    public function defaultMaxSpectators(): int
    {
        return $this->int('defaults.max_spectators', 0);
    }

    public function defaultAutoConfirm(): bool
    {
        return $this->bool('defaults.auto_confirm', true);
    }

    public function defaultRegistrationType(): RegistrationType
    {
        $value = $this->get('defaults.registration_type');

        return (is_string($value) ? RegistrationType::tryFrom($value) : null) ?? RegistrationType::Everyone;
    }

    public function defaultMembersEarlyAccessDays(): int
    {
        return $this->int('defaults.members_early_access_days', 0);
    }

    public function maxPlayersLimit(): int
    {
        return $this->int('limits.max_players_limit', 20);
    }

    public function maxSpectatorsLimit(): int
    {
        return $this->int('limits.max_spectators_limit', 50);
    }

    public function maxDurationMinutes(): int
    {
        return $this->int('limits.max_duration_minutes', 720);
    }

    public function isFrontendCreationEnabled(): bool
    {
        return $this->bool('frontend_creation.enabled', false);
    }

    public function getAllowedContent(): string
    {
        return $this->string('frontend_creation.allowed_content', 'tables');
    }

    public function getAccessLevel(): CreationAccessLevel
    {
        return CreationAccessLevel::from($this->string('frontend_creation.access_level', 'registered'));
    }

    /**
     * The panel saves the roles as comma-separated text.
     *
     * @return array<string>
     */
    public function getAllowedRoles(): array
    {
        $value = $this->get('frontend_creation.allowed_roles');

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $role): string => trim((string) $role), $value),
            static fn (string $role): bool => $role !== '',
        ));
    }

    public function getRequiredPermission(): ?string
    {
        $value = $this->get('frontend_creation.required_permission');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<array{tier: int, type: string, value: ?string, days_before: int}>
     */
    public function getPriorityTiers(): array
    {
        /** @var array<array{tier: int, type: string, value: ?string, days_before: int}> */
        return $this->array('frontend_creation.priority_tiers');
    }

    public function getPublicationMode(): string
    {
        return $this->string('frontend_creation.publication.mode', 'approval');
    }

    /**
     * @return array<string>
     */
    public function getAutoPublishRoles(): array
    {
        /** @var array<string> */
        return $this->array('frontend_creation.publication.auto_publish_roles');
    }

    /**
     * @return array<string>
     */
    public function getAutoPublishPermissions(): array
    {
        /** @var array<string> */
        return $this->array('frontend_creation.publication.auto_publish_permissions');
    }

    public function canCreateTables(): bool
    {
        return in_array($this->getAllowedContent(), ['tables', 'both'], true);
    }

    public function canCreateCampaigns(): bool
    {
        return in_array($this->getAllowedContent(), ['campaigns', 'both'], true);
    }

    private function get(string $key): mixed
    {
        return data_get($this->all(), $key);
    }

    private function bool(string $key, bool $fallback): bool
    {
        $value = $this->get($key);

        return $value === null ? $fallback : (bool) $value;
    }

    private function int(string $key, int $fallback): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function string(string $key, string $fallback): string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @return array<mixed>
     */
    private function array(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<mixed>  $defaults
     * @param  array<mixed>  $stored
     * @return array<mixed>
     */
    private static function merge(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            // A value the panel left empty keeps the default, as `??` did
            if ($value === null) {
                continue;
            }

            $default = $defaults[$key] ?? null;

            $defaults[$key] = is_array($value) && is_array($default) && ! array_is_list($value) && ! array_is_list($default)
                ? self::merge($default, $value)
                : $value;
        }

        return $defaults;
    }
}
