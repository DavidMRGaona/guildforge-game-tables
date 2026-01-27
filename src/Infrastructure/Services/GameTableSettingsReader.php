<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

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
}
