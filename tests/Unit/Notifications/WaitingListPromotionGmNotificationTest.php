<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Tests\TestCase;

final class WaitingListPromotionGmNotificationTest extends TestCase
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
        $notification = new WaitingListPromotionGmNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable;
        $result = $notification->via($notifiable);

        $this->assertContains('mail', $result);
    }

    public function test_to_mail_contains_subject_with_table_title(): void
    {
        $notification = new WaitingListPromotionGmNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'La Tumba de la Aniquilacion',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable;
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('La Tumba de la Aniquilacion', $mail->subject);
    }

    public function test_to_mail_contains_participant_name(): void
    {
        $notification = new WaitingListPromotionGmNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable;
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('John Doe', implode(' ', $mail->introLines));
    }

    public function test_to_mail_includes_date_when_provided(): void
    {
        $notification = new WaitingListPromotionGmNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable;
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('15/02/2026 18:00', implode(' ', $mail->introLines));
    }

    public function test_to_mail_includes_location_when_provided(): void
    {
        $notification = new WaitingListPromotionGmNotification(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
        );

        $notifiable = new AnonymousNotifiable;
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('Sala de juegos', implode(' ', $mail->introLines));
    }

    public function test_a_pending_promotion_without_a_manage_link_points_to_the_panel(): void
    {
        app()->setLocale('es');

        $mail = (new WaitingListPromotionGmNotification('John Doe', 'table-uuid-123', 'Test Table', null, null, confirmed: false))
            ->toMail(new AnonymousNotifiable);

        $this->assertContains('La mesa tiene confirmación manual: la inscripción queda pendiente hasta que la confirmes o la rechaces en el panel.', $mail->introLines);
        $this->assertSame('Ver mesa', $mail->actionText);
        $this->assertSame(url('/mesas/table-uuid-123'), $mail->actionUrl);
    }

    public function test_a_pending_promotion_with_a_manage_link_points_to_the_registrations_page(): void
    {
        app()->setLocale('es');

        $mail = (new WaitingListPromotionGmNotification('John Doe', 'table-uuid-123', 'Test Table', null, null, confirmed: false, manageUrl: 'https://example.test/mesas/mis-mesas/x/inscritos'))
            ->toMail(new AnonymousNotifiable);

        $this->assertContains('La mesa tiene confirmación manual: la inscripción queda pendiente hasta que la apruebes o la rechaces desde la página de inscritos.', $mail->introLines);
        $this->assertNotContains(__('game-tables::emails.waiting_list_promotion_gm.pending'), $mail->introLines);
        $this->assertSame('Gestionar inscritos', $mail->actionText);
        $this->assertSame('https://example.test/mesas/mis-mesas/x/inscritos', $mail->actionUrl);
    }

    public function test_a_confirmed_promotion_says_nothing_pending(): void
    {
        app()->setLocale('es');

        $mail = (new WaitingListPromotionGmNotification('John Doe', 'table-uuid-123', 'Test Table', null, null))
            ->toMail(new AnonymousNotifiable);

        foreach ($mail->introLines as $line) {
            $this->assertStringNotContainsString('pendiente', (string) $line);
        }
        $this->assertSame('Ver mesa', $mail->actionText);
    }
}
