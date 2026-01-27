<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Infrastructure\Services;

use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Tests\TestCase;

final class GameTableSettingsReaderTest extends TestCase
{
    private GameTableSettingsReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new GameTableSettingsReader();
    }

    public function test_notify_on_registration_returns_true_by_default(): void
    {
        $result = $this->reader->isNotifyOnRegistrationEnabled();

        $this->assertTrue($result);
    }

    public function test_notify_on_registration_reads_from_game_tables_config(): void
    {
        config()->set('game-tables.notifications.notify_on_registration', false);

        $result = $this->reader->isNotifyOnRegistrationEnabled();

        $this->assertFalse($result);
    }

    public function test_notify_on_registration_module_settings_override(): void
    {
        config()->set('game-tables.notifications.notify_on_registration', true);
        config()->set('modules.settings.gametables.notifications.notify_on_registration', false);

        $result = $this->reader->isNotifyOnRegistrationEnabled();

        $this->assertFalse($result);
    }

    public function test_notify_on_cancellation_returns_true_by_default(): void
    {
        $result = $this->reader->isNotifyOnCancellationEnabled();

        $this->assertTrue($result);
    }

    public function test_notify_on_cancellation_reads_from_game_tables_config(): void
    {
        config()->set('game-tables.notifications.notify_on_cancellation', false);

        $result = $this->reader->isNotifyOnCancellationEnabled();

        $this->assertFalse($result);
    }

    public function test_notify_waiting_list_promotion_returns_true_by_default(): void
    {
        $result = $this->reader->isNotifyWaitingListPromotionEnabled();

        $this->assertTrue($result);
    }

    public function test_notify_waiting_list_promotion_reads_from_game_tables_config(): void
    {
        config()->set('game-tables.notifications.notify_waiting_list_promotion', false);

        $result = $this->reader->isNotifyWaitingListPromotionEnabled();

        $this->assertFalse($result);
    }
}
