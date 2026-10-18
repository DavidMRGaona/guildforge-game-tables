<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\GameTables\Notifications\GuestEmailConfirmationRequest;
use Tests\TestCase;

final class GuestEmailConfirmationRequestTest extends TestCase
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

    public function test_a_held_place_gives_the_deadline(): void
    {
        $mail = $this->mail(holdsPlace: true, deadline: '02/11/2026 12:00');

        $this->assertSame('Confirma tu inscripción: Mesa', $mail->subject);
        $this->assertSame('Hola Invitada,', $mail->greeting);
        $this->assertContains('Hemos recibido tu inscripción en la mesa «Mesa». Confírmala con el botón de abajo.', $mail->introLines);
        $this->assertContains('Si no la confirmas antes del 02/11/2026 12:00, la plaza se liberará.', $mail->introLines);
        $this->assertNotContains('Tu plaza no está garantizada hasta que la confirmes.', $mail->introLines);
        $this->assertSame('Confirmar inscripción', $mail->actionText);
        $this->assertSame('https://example.test/mesas/confirmar/abc', $mail->actionUrl);
        $this->assertContains('Si no te has inscrito tú, ignora este email: la inscripción no seguirá adelante.', $mail->outroLines);
    }

    public function test_a_place_not_held_is_not_guaranteed(): void
    {
        $mail = $this->mail(holdsPlace: false, deadline: null);

        $this->assertContains('Tu plaza no está garantizada hasta que la confirmes.', $mail->introLines);
        foreach ($mail->introLines as $line) {
            $this->assertStringNotContainsString('la plaza se liberará', (string) $line);
        }
        $this->assertSame('https://example.test/mesas/confirmar/abc', $mail->actionUrl);
    }

    public function test_it_is_queued_and_sent_by_mail(): void
    {
        $notification = $this->notification(holdsPlace: true, deadline: '02/11/2026 12:00');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable()));
    }

    private function mail(bool $holdsPlace, ?string $deadline): MailMessage
    {
        return $this->notification($holdsPlace, $deadline)->toMail(new AnonymousNotifiable());
    }

    private function notification(bool $holdsPlace, ?string $deadline): GuestEmailConfirmationRequest
    {
        return new GuestEmailConfirmationRequest(
            firstName: 'Invitada',
            tableTitle: 'Mesa',
            tableDate: '09/11/2026 10:00',
            confirmationUrl: 'https://example.test/mesas/confirmar/abc',
            holdsPlace: $holdsPlace,
            deadline: $deadline,
        );
    }
}
