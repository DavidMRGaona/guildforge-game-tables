<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;
use Modules\GameTables\Notifications\CampaignJoinRequestAcceptedNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestReceivedNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestRejectedNotification;
use Modules\GameTables\Notifications\CampaignPlayerLeftNotification;
use Modules\GameTables\Notifications\CampaignRosterAddedNotification;
use Modules\GameTables\Notifications\CampaignRosterRemovedNotification;
use Modules\GameTables\Notifications\CampaignSessionScheduledNotification;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignRosterNotificationsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const string WAITING = ' (en lista de espera, con prioridad por ser de la plantilla)';

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
    }

    private function mail(object $notification): MailMessage
    {
        return $notification->toMail(new AnonymousNotifiable);
    }

    /**
     * @return list<string>
     */
    private function lines(MailMessage $mail): array
    {
        return array_map(static fn (mixed $line): string => $line instanceof HtmlString ? html_entity_decode(strip_tags((string) $line)) : (string) $line, [...$mail->introLines, ...$mail->outroLines]);
    }

    private function scheduled(bool $waiting = false): CampaignSessionScheduledNotification
    {
        return new CampaignSessionScheduledNotification(
            userName: 'John Doe',
            campaignTitle: 'Campaña de prueba',
            tableId: 'table-1',
            tableTitle: 'Sesión 2',
            tableDate: '08/11/2026 18:00',
            tableLocation: 'Sala de juegos',
            waiting: $waiting,
        );
    }

    public function test_the_scheduled_session_email_has_seat_copy(): void
    {
        $mail = $this->mail($this->scheduled());

        $this->assertSame('Nueva sesión de Campaña de prueba: 08/11/2026 18:00', $mail->subject);
        $this->assertSame('¡Hola, John Doe!', $mail->greeting);
        $lines = $this->lines($mail);
        $this->assertContains('Se ha programado una nueva sesión de la campaña «Campaña de prueba» y ya tienes tu plaza.', $lines);
        $this->assertContains('**Mesa:** Sesión 2', $lines);
        $this->assertContains('**Fecha:** 08/11/2026 18:00', $lines);
        $this->assertContains('**Ubicación:** Sala de juegos', $lines);
        $this->assertContains('Si no puedes ir a una sesión, date de baja desde su ficha: seguirás en la campaña.', $lines);
        $this->assertSame('Ver sesión', $mail->actionText);
        $this->assertSame(url('/mesas/table-1'), $mail->actionUrl);
        $this->assertSame(['mail'], $this->scheduled()->via(new AnonymousNotifiable));
    }

    public function test_the_scheduled_session_email_has_waiting_copy(): void
    {
        $lines = $this->lines($this->mail($this->scheduled(waiting: true)));

        $this->assertContains('Se ha programado una nueva sesión de la campaña «Campaña de prueba». Está completa: estás en la lista de espera, con prioridad por ser de la plantilla.', $lines);
        $this->assertNotContains('Se ha programado una nueva sesión de la campaña «Campaña de prueba» y ya tienes tu plaza.', $lines);
    }

    public function test_the_scheduled_session_email_omits_a_missing_location(): void
    {
        $mail = $this->mail(new CampaignSessionScheduledNotification('John Doe', 'Campaña de prueba', 'table-1', 'Sesión 2', '08/11/2026 18:00', null, false));

        $this->assertNotContains('**Ubicación:** Sala de juegos', $this->lines($mail));
        foreach ($this->lines($mail) as $line) {
            $this->assertStringNotContainsString('Ubicación', $line);
        }
    }

    public function test_the_received_email_names_the_player_and_the_message(): void
    {
        $mail = $this->mail(new CampaignJoinRequestReceivedNotification('Jane Doe', 'camp-1', 'Campaña de prueba', 'Mary Major', 'Me encantaría jugar'));

        $this->assertSame('Solicitud para unirse a Campaña de prueba', $mail->subject);
        $this->assertSame('¡Hola, Jane Doe!', $mail->greeting);
        $lines = $this->lines($mail);
        $this->assertContains('Mary Major quiere unirse a la campaña «Campaña de prueba».', $lines);
        $this->assertContains('Mensaje: «Me encantaría jugar»', $lines);
        $this->assertSame('Revisar solicitudes', $mail->actionText);
        $this->assertSame(url('/campanas/mis-campanas/camp-1/plantilla'), $mail->actionUrl);
    }

    public function test_the_received_email_omits_the_message_without_one(): void
    {
        $mail = $this->mail(new CampaignJoinRequestReceivedNotification('Jane Doe', 'camp-1', 'Campaña de prueba', 'Mary Major', null));

        foreach ($this->lines($mail) as $line) {
            $this->assertStringNotContainsString('Mensaje', $line);
        }
    }

    public function test_the_accepted_email_lists_the_sessions(): void
    {
        $mail = $this->mail(new CampaignJoinRequestAcceptedNotification('John Doe', 'Campaña de prueba', 'campana-de-prueba', [
            ['title' => 'Sesión 1', 'date' => '08/11/2026 18:00', 'waiting' => false],
            ['title' => 'Sesión 2', 'date' => '15/11/2026 18:00', 'waiting' => true],
        ]));

        $this->assertSame('Ya formas parte de Campaña de prueba', $mail->subject);
        $this->assertSame('¡Hola, John Doe!', $mail->greeting);
        $lines = $this->lines($mail);
        $this->assertContains('La dirección ha aceptado tu solicitud para unirte a la campaña «Campaña de prueba».', $lines);
        $this->assertContains('Quedas inscrito en estas sesiones:', $lines);
        $this->assertContains('«Sesión 1» — 08/11/2026 18:00', $lines);
        $this->assertContains('«Sesión 2» — 15/11/2026 18:00'.self::WAITING, $lines);
        $this->assertContains('Si no puedes ir a una sesión, date de baja desde su ficha: seguirás en la campaña.', $lines);
        $this->assertSame('Ver campaña', $mail->actionText);
        $this->assertSame(route('campaigns.show', 'campana-de-prueba'), $mail->actionUrl);
    }

    public function test_the_accepted_email_without_sessions_says_so(): void
    {
        $mail = $this->mail(new CampaignJoinRequestAcceptedNotification('John Doe', 'Campaña de prueba', 'campana-de-prueba', []));

        $lines = $this->lines($mail);
        $this->assertContains('Todavía no hay sesiones programadas: te avisaremos cuando se publique la próxima.', $lines);
        $this->assertNotContains('Quedas inscrito en estas sesiones:', $lines);
    }

    public function test_the_rejected_email_shows_the_reason_only_when_given(): void
    {
        $with = $this->mail(new CampaignJoinRequestRejectedNotification('John Doe', 'Campaña de prueba', 'campana-de-prueba', 'La plantilla está completa'));
        $without = $this->mail(new CampaignJoinRequestRejectedNotification('John Doe', 'Campaña de prueba', 'campana-de-prueba', null));

        $this->assertSame('Solicitud para Campaña de prueba', $with->subject);
        $this->assertContains('La dirección no ha aceptado tu solicitud para unirte a la campaña «Campaña de prueba».', $this->lines($with));
        $this->assertContains('Motivo: La plantilla está completa', $this->lines($with));
        foreach ($this->lines($without) as $line) {
            $this->assertStringNotContainsString('Motivo', $line);
        }
        $this->assertSame('Ver campaña', $with->actionText);
        $this->assertSame(route('campaigns.show', 'campana-de-prueba'), $with->actionUrl);
    }

    public function test_the_roster_added_email(): void
    {
        $mail = $this->mail(new CampaignRosterAddedNotification('John Doe', 'Campaña de prueba', 'campana-de-prueba', [
            ['title' => 'Sesión 1', 'date' => '08/11/2026 18:00', 'waiting' => false],
        ]));

        $this->assertSame('Te han añadido a Campaña de prueba', $mail->subject);
        $lines = $this->lines($mail);
        $this->assertContains('La dirección te ha añadido a la plantilla de la campaña «Campaña de prueba».', $lines);
        $this->assertContains('«Sesión 1» — 08/11/2026 18:00', $lines);
        $this->assertContains('Si no puedes ir a una sesión, date de baja desde su ficha: seguirás en la campaña.', $lines);
        $this->assertSame(route('campaigns.show', 'campana-de-prueba'), $mail->actionUrl);
    }

    public function test_the_roster_removed_email(): void
    {
        $mail = $this->mail(new CampaignRosterRemovedNotification('John Doe', 'Campaña de prueba', 'campana-de-prueba'));

        $this->assertSame('Ya no formas parte de Campaña de prueba', $mail->subject);
        $this->assertSame('¡Hola, John Doe!', $mail->greeting);
        $this->assertContains('La dirección te ha quitado de la plantilla de la campaña «Campaña de prueba». Tus plazas en las próximas sesiones se han liberado.', $this->lines($mail));
        $this->assertSame('Ver campaña', $mail->actionText);
        $this->assertSame(route('campaigns.show', 'campana-de-prueba'), $mail->actionUrl);
    }

    public function test_the_player_left_email(): void
    {
        $mail = $this->mail(new CampaignPlayerLeftNotification('Jane Doe', 'camp-1', 'Campaña de prueba', 'Mary Major'));

        $this->assertSame('Mary Major ha salido de Campaña de prueba', $mail->subject);
        $this->assertSame('¡Hola, Jane Doe!', $mail->greeting);
        $this->assertContains('Mary Major ha salido de la plantilla de la campaña «Campaña de prueba». Sus plazas en las próximas sesiones se han liberado.', $this->lines($mail));
        $this->assertSame('Ver plantilla', $mail->actionText);
        $this->assertSame(url('/campanas/mis-campanas/camp-1/plantilla'), $mail->actionUrl);
    }

    public function test_the_english_copy_exists_for_every_email(): void
    {
        foreach (['campaign_sessions', 'campaign_session_scheduled', 'campaign_join_request_received', 'campaign_join_request_accepted', 'campaign_join_request_rejected', 'campaign_roster_added', 'campaign_roster_removed', 'campaign_player_left'] as $block) {
            $es = require dirname(__DIR__, 3).'/lang/es/emails.php';
            $en = require dirname(__DIR__, 3).'/lang/en/emails.php';
            $this->assertSame(array_keys($es[$block]), array_keys($en[$block]), $block);
        }
    }

    public function test_user_text_is_shown_literally_and_never_as_markdown(): void
    {
        $evil = '[Pulsa aquí](https://evil.example) **URGENTE**';
        $name = '<b>Mary</b> Major';

        $mails = [
            $this->mail(new CampaignJoinRequestReceivedNotification('Jane Doe', 'camp-1', 'Campaña de prueba', $name, $evil)),
            $this->mail(new CampaignJoinRequestRejectedNotification('John Doe', 'Campaña de prueba', 'campana-de-prueba', $evil)),
            $this->mail(new CampaignPlayerLeftNotification('Jane Doe', 'camp-1', 'Campaña de prueba', $name)),
        ];

        foreach ($mails as $mail) {
            $html = (string) $mail->render();

            $this->assertStringNotContainsString('<a href="https://evil.example"', $html);
            $this->assertStringNotContainsString('<strong>URGENTE', $html);
            $this->assertStringNotContainsString('<b>Mary</b>', $html);
        }

        $this->assertStringContainsString('[Pulsa aquí](https://evil.example) **URGENTE**', (string) $mails[0]->render());
        $this->assertStringContainsString('&lt;b&gt;Mary&lt;/b&gt; Major quiere unirse', (string) $mails[0]->render());
        $this->assertStringContainsString('Motivo: [Pulsa aquí](https://evil.example) **URGENTE**', (string) $mails[1]->render());
        $this->assertStringContainsString('&lt;b&gt;Mary&lt;/b&gt; Major ha salido', (string) $mails[2]->render());
    }
}
