<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Listeners;

use App\Application\Legal\Services\LegalMailNoticeServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Notifications\CampaignInvitationAcceptedNotification;
use Modules\GameTables\Notifications\CampaignInvitationNotification;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignInvitationMailsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const string TOKEN = 'plain-token-for-the-email';

    private UserModel $john;

    private UserModel $mary;

    private CampaignModel $campaign;

    private string $invitationId;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        config()->set('app.name', 'Gremio de prueba');
        app()->setLocale('es');

        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de prueba', 'slug' => 'sistema-de-prueba', 'is_active' => true]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->john->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba',
            'status' => 'recruiting',
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ]);

        $this->invitationId = $this->invitation(hash('sha256', self::TOKEN));
    }

    private function invitation(string $tokenHash): string
    {
        $id = (string) Str::uuid();
        app(CampaignInvitationRepositoryInterface::class)->save(new CampaignInvitation(
            id: new CampaignInvitationId($id),
            campaignId: new CampaignId($this->campaign->id),
            email: 'guest@example.com',
            invitedBy: $this->john->id,
            inviterName: 'John Doe',
            tokenHash: $tokenHash,
            expiresAt: new DateTimeImmutable('2026-11-04 12:00:00'),
            sendCount: 1,
            lastSentAt: new DateTimeImmutable('2026-10-21 12:00:00'),
        ));

        return $id;
    }

    private function sendInvitation(): void
    {
        event(new CampaignInvitationSent($this->invitationId, $this->campaign->id, 'guest@example.com', self::TOKEN));
    }

    public function test_the_invitation_is_mailed_to_the_address(): void
    {
        $this->sendInvitation();

        Notification::assertSentOnDemand(
            CampaignInvitationNotification::class,
            function (CampaignInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                $mail = $notification->toMail($notifiable);

                return $notifiable->routes['mail'] === 'guest@example.com'
                    && $mail->subject === 'John Doe te invita a la campaña «Campaña de prueba»'
                    && str_contains($mail->actionUrl, '/campanas/invitaciones/'.self::TOKEN);
            },
        );
        Notification::assertSentOnDemandTimes(CampaignInvitationNotification::class, 1);
    }

    public function test_the_invitation_ignores_the_notice_settings(): void
    {
        config()->set('modules.settings.game-tables', ['campaign_notifications' => array_fill_keys(
            ['session_scheduled', 'join_request_received', 'join_request_accepted', 'join_request_rejected', 'roster_added', 'roster_removed', 'player_left', 'invitation_accepted'],
            false,
        )]);

        $this->sendInvitation();

        Notification::assertSentOnDemandTimes(CampaignInvitationNotification::class, 1);
    }

    public function test_the_invitation_carries_the_privacy_information(): void
    {
        $this->sendInvitation();

        Notification::assertSentOnDemand(CampaignInvitationNotification::class, function (CampaignInvitationNotification $notification): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);
            $text = implode("\n", array_map('strval', $mail->introLines))."\n".implode("\n", array_map('strval', $mail->outroLines));

            return str_contains($text, 'Gremio de prueba')
                && str_contains($text, 'te lo facilitó John Doe')
                && str_contains($text, 'Caduca el 04/11/2026.')
                && str_contains($text, url('/politica-de-privacidad'))
                && str_contains($text, 'Rechazar')
                && str_contains($mail->actionUrl, self::TOKEN);
        });
    }

    private function invitationText(): string
    {
        $mail = (new CampaignInvitationNotification('John <b>Doe</b>', 'Campaña de prueba', 'campana-de-prueba', 'guest@example.com', self::TOKEN, '04/11/2026'))
            ->toMail(new AnonymousNotifiable);

        return implode("\n", array_map('strval', $mail->introLines))."\n".implode("\n", array_map('strval', $mail->outroLines));
    }

    private function requireLegalNotices(): void
    {
        if (! interface_exists(LegalMailNoticeServiceInterface::class)) {
            $this->markTestSkipped('The core has no legal mail notices.');
        }
    }

    public function test_the_email_uses_the_saved_text_with_placeholders(): void
    {
        $this->requireLegalNotices();
        app(SettingsServiceInterface::class)->set(
            'legal_mail_notice:game-tables.campaign_invitation',
            "Invita :inviter a :campaign.\n\nSitio :site, :days días.",
        );

        $text = $this->invitationText();

        $this->assertStringContainsString('Invita John &lt;b&gt;Doe&lt;/b&gt; a Campaña de prueba.', $text);
        $this->assertStringContainsString('Sitio Gremio de prueba, 14 días.', $text);
        $this->assertStringNotContainsString('Finalidad', $text);
        $this->assertStringContainsString(url('/politica-de-privacidad'), $text);
    }

    public function test_the_email_falls_back_to_the_default_text(): void
    {
        $this->requireLegalNotices();

        $text = $this->invitationText();

        $this->assertStringContainsString('Finalidad: invitarte a esta campaña.', $text);
        $this->assertStringContainsString('Origen: te lo facilitó John &lt;b&gt;Doe&lt;/b&gt;.', $text);
        $this->assertStringContainsString('como máximo 14 días', $text);
        $this->assertStringContainsString('Derechos:', $text);
    }

    public function test_the_controller_comes_from_the_legal_entity(): void
    {
        $this->requireLegalNotices();
        $settings = app(SettingsServiceInterface::class);
        $settings->set('legal_entity_name', 'Asociación Ejemplo');
        $settings->set('legal_entity_email', 'info@example.com');
        $settings->set('legal_entity_dpo_email', 'dpo@example.com');

        $text = $this->invitationText();

        $this->assertStringContainsString('Responsable: Asociación Ejemplo', $text);
        $this->assertStringContainsString('Contacto: info@example.com', $text);
        $this->assertStringContainsString('Delegado de protección de datos: dpo@example.com', $text);
        $this->assertStringNotContainsString('Responsable: Gremio de prueba', $text);
    }

    public function test_user_values_in_placeholders_are_escaped(): void
    {
        $this->requireLegalNotices();
        app(SettingsServiceInterface::class)->set('legal_mail_notice:game-tables.campaign_invitation', 'De :inviter');

        $text = $this->invitationText();

        $this->assertStringContainsString('De John &lt;b&gt;Doe&lt;/b&gt;', $text);
        $this->assertStringNotContainsString('<b>Doe', $text);
        $this->assertStringNotContainsString('&amp;lt;', $text);
    }

    public function test_the_email_keeps_the_default_lines_without_the_core_service(): void
    {
        $this->requireLegalNotices();
        unset($this->app[LegalMailNoticeServiceInterface::class]);

        $text = $this->invitationText();

        $this->assertStringContainsString('Responsable: Gremio de prueba', $text);
        $this->assertStringContainsString('Origen: te lo facilitó John &lt;b&gt;Doe&lt;/b&gt;.', $text);
    }

    public function test_a_resend_in_the_meantime_skips_the_stale_email(): void
    {
        $repository = app(CampaignInvitationRepositoryInterface::class);
        $invitation = $repository->find(new CampaignInvitationId($this->invitationId));
        $invitation->resend(hash('sha256', 'a-newer-token'), new DateTimeImmutable);
        $repository->save($invitation);

        $this->sendInvitation();

        Notification::assertNothingSent();
    }

    public function test_a_deleted_invitation_sends_nothing(): void
    {
        app(CampaignInvitationRepositoryInterface::class)->delete(new CampaignInvitationId($this->invitationId));

        $this->sendInvitation();

        Notification::assertNothingSent();
    }

    public function test_the_direction_is_told_when_someone_accepts(): void
    {
        event(new CampaignInvitationAccepted($this->campaign->id, $this->mary->id));

        Notification::assertSentOnDemand(
            CampaignInvitationAcceptedNotification::class,
            function (CampaignInvitationAcceptedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                $mail = $notification->toMail($notifiable);

                return $notifiable->routes['mail'] === 'john@example.com'
                    && $mail->subject === 'Mary Major ha aceptado tu invitación a «Campaña de prueba»'
                    && str_contains($mail->actionUrl, '/plantilla');
            },
        );
    }

    public function test_the_accept_notice_follows_its_setting(): void
    {
        config()->set('modules.settings.game-tables', ['campaign_notifications' => ['invitation_accepted' => false]]);

        event(new CampaignInvitationAccepted($this->campaign->id, $this->mary->id));

        Notification::assertNothingSent();
    }
}
