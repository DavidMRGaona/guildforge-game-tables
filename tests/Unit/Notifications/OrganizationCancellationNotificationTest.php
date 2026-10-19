<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Modules\GameTables\Notifications\OrganizationCancellationNotification;
use Tests\TestCase;

/**
 * The notice a participant gets when the organisation cancels their registration from the panel.
 */
final class OrganizationCancellationNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['translator']->addNamespace('game-tables', base_path('modules/game-tables/lang'));
    }

    public function test_it_is_sent_by_mail(): void
    {
        $this->assertSame(['mail'], $this->notification(guest: false)->via(new AnonymousNotifiable));
    }

    public function test_it_says_the_organisation_cancelled_the_registration(): void
    {
        app()->setLocale('es');
        $mail = $this->notification(guest: false)->toMail(new AnonymousNotifiable);

        $this->assertSame('La organización ha cancelado tu inscripción en La tumba', $mail->subject);
        $this->assertSame('¡Hola, Ana!', $mail->greeting);
        $this->assertContains('La organización ha cancelado tu inscripción en la mesa «La tumba».', $mail->introLines);
        $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);
        $this->assertStringContainsString('15/02/2026 18:00', $text);
        $this->assertStringContainsString('Sala de juegos', $text);
        $this->assertStringNotContainsString('correctamente', $text);
        $this->assertStringNotContainsString(__('game-tables::emails.organization_cancellation.guest_gdpr_notice'), $text);
    }

    public function test_a_guest_also_gets_the_privacy_notice(): void
    {
        app()->setLocale('es');
        $mail = $this->notification(guest: true)->toMail(new AnonymousNotifiable);

        $this->assertContains(__('game-tables::emails.organization_cancellation.guest_gdpr_notice'), $mail->outroLines);
    }

    public function test_it_has_an_english_text(): void
    {
        app()->setLocale('en');
        $mail = $this->notification(guest: false)->toMail(new AnonymousNotifiable);

        $this->assertSame('The organizers have cancelled your registration for La tumba', $mail->subject);
        $this->assertContains('The organizers have cancelled your registration for the table La tumba.', $mail->introLines);
    }

    private function notification(bool $guest): OrganizationCancellationNotification
    {
        return new OrganizationCancellationNotification(
            participantName: 'Ana',
            tableId: 'table-uuid-123',
            tableTitle: 'La tumba',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
            guest: $guest,
        );
    }
}
