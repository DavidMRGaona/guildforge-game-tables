<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use Tests\TestCase;

final class WaitingListPromotionNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['translator']->addNamespace(
            'game-tables',
            base_path('modules/game-tables/lang'),
        );
    }

    public function test_via_returns_mail_channel(): void
    {
        $notification = new WaitingListPromotionNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable();
        $result = $notification->via($notifiable);

        $this->assertIsArray($result);
        $this->assertContains('mail', $result);
    }

    public function test_to_mail_contains_subject_with_table_title(): void
    {
        $notification = new WaitingListPromotionNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'La Tumba de la Aniquilacion',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable();
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('La Tumba de la Aniquilacion', $mail->subject);
    }

    public function test_to_mail_contains_participant_name(): void
    {
        $notification = new WaitingListPromotionNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable();
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('John Doe', $mail->greeting);
    }

    public function test_to_mail_includes_date_when_provided(): void
    {
        $notification = new WaitingListPromotionNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable();
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('15/02/2026 18:00', implode(' ', $mail->introLines));
    }

    public function test_to_mail_includes_location_when_provided(): void
    {
        $notification = new WaitingListPromotionNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable();
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('Sala de juegos', implode(' ', $mail->introLines));
    }

    public function test_to_mail_includes_outro_line(): void
    {
        $notification = new WaitingListPromotionNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable();
        $mail = $notification->toMail($notifiable);

        $allLines = implode(' ', $mail->introLines);
        $this->assertStringContainsString('', $allLines);
        $this->assertGreaterThan(3, count($mail->introLines));
    }
}
