<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Modules\GameTables\Notifications\GuestRegistrationExpiredNotification;
use Tests\TestCase;

final class GuestRegistrationExpiredNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['translator']->addNamespace(
            'game-tables',
            base_path('modules/game-tables/lang'),
        );
        app()->setLocale('es');
    }

    public function test_it_tells_the_guest_the_place_was_released(): void
    {
        $mail = $this->notification()->toMail(new AnonymousNotifiable());

        $this->assertSame('Inscripción caducada: Mesa', $mail->subject);
        $this->assertSame('Hola Invitada,', $mail->greeting);
        $this->assertContains('No confirmaste a tiempo tu inscripción en la mesa «Mesa» y la plaza se ha liberado.', $mail->introLines);
        $this->assertContains('Si siguen quedando plazas, puedes volver a apuntarte.', $mail->introLines);
        $this->assertSame('Ver mesa', $mail->actionText);
        $this->assertSame('https://example.test/mesas/mesa', $mail->actionUrl);
    }

    public function test_the_english_copy(): void
    {
        app()->setLocale('en');

        $mail = $this->notification()->toMail(new AnonymousNotifiable());

        $this->assertSame('Registration expired: Mesa', $mail->subject);
        $this->assertSame('View table', $mail->actionText);
    }

    public function test_it_is_queued_and_sent_by_mail(): void
    {
        $notification = $this->notification();

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable()));
    }

    private function notification(): GuestRegistrationExpiredNotification
    {
        return new GuestRegistrationExpiredNotification(
            firstName: 'Invitada',
            tableTitle: 'Mesa',
            tableUrl: 'https://example.test/mesas/mesa',
        );
    }
}
