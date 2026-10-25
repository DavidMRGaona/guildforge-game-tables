<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Providers;

use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\GameTables\Notifications\CampaignInvitationNotification;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignInvitationLegalNoticeTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (! interface_exists(LegalMailNoticeRegistryInterface::class)) {
            $this->markTestSkipped('The core has no legal mail notices.');
        }

        config()->set('app.name', 'Gremio de prueba');
        app()->setLocale('es');
    }

    public function test_the_notice_is_registered(): void
    {
        $notice = app(LegalMailNoticeRegistryInterface::class)->find('game-tables.campaign_invitation');

        $this->assertNotNull($notice);
        $this->assertSame('game-tables', $notice->module);
        $this->assertSame('Correo de invitación a una campaña', $notice->label);
        $this->assertSame('Bloque de protección de datos del correo que recibe una persona invitada a una campaña.', $notice->help);
        $this->assertSame([':inviter', ':campaign', ':site', ':days'], $notice->placeholders);
        $this->assertStringContainsString('Origen: tu email nos lo ha facilitado :inviter.', $notice->defaultContent);
        $this->assertStringContainsString('Legitimación: interés legítimo (art. 6.1.f RGPD) para enviarte esta invitación, que :inviter nos ha pedido enviarte con tu permiso.', $notice->defaultContent);
        $this->assertStringContainsString('como máximo :days días', $notice->defaultContent);
        $this->assertStringNotContainsString('Responsable', $notice->defaultContent);
    }

    public function test_the_preview_renders_the_invitation_email(): void
    {
        $notice = app(LegalMailNoticeRegistryInterface::class)->find('game-tables.campaign_invitation');
        $this->assertNotNull($notice);

        $html = ($notice->preview)("Aviso sin guardar para :inviter en :campaign.\n\nSegundo párrafo.");

        $this->assertMatchesRegularExpression('/<h1[^>]*>¡Hola!<\/h1>/u', $html);
        $this->assertMatchesRegularExpression('/<p[^>]*>Aviso sin guardar para John Doe en Campaña de ejemplo\.<\/p>\s*<p[^>]*>Segundo párrafo\.<\/p>/u', $html);
        $this->assertStringContainsString('Ver invitación', $html);
        $this->assertStringContainsString('/politica-de-privacidad', $html);
    }

    public function test_the_legal_pages_preview_shows_the_email_as_it_is_sent(): void
    {
        if (! interface_exists(LegalMailNoticeRegistryInterface::class)) {
            $this->markTestSkipped('The core has no legal pages preview.');
        }

        $this->actingAs(UserModel::factory()->admin()->create());
        $field = LegalPagesSettings::noticeFieldName(CampaignInvitationNotification::NOTICE_KEY);

        // The modal is drawn inside a Livewire render, unlike the email sent from the queue
        $html = Livewire::test(LegalPagesSettings::class)
            ->fillForm([$field => "Primer aviso para :inviter.\n\nSegundo aviso en :campaign."])
            ->mountAction(TestAction::make(LegalPagesSettings::noticePreviewActionName(CampaignInvitationNotification::NOTICE_KEY))
                ->schemaComponent(LegalPagesSettings::noticeSectionKey(CampaignInvitationNotification::NOTICE_KEY)))
            ->getMountedActionModalHtml();

        $this->assertSame(1, preg_match('/srcdoc="([^"]*)"/', $html, $match));
        $email = html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5);

        $this->assertMatchesRegularExpression('/<h1[^>]*>¡Hola!<\/h1>/u', $email);
        $this->assertStringNotContainsString('# ¡Hola!', $email);
        $this->assertStringNotContainsString('[if BLOCK]', $email);
        $this->assertMatchesRegularExpression(
            '/<p[^>]*>Primer aviso para John Doe\.<\/p>\s*<p[^>]*>Segundo aviso en Campaña de ejemplo\.<\/p>/u',
            $email,
        );
        $this->assertMatchesRegularExpression('/<p[^>]*>Si no quieres unirte[^<]*<\/p>\s*<p[^>]*>Caduca el /u', $email);
        $this->assertMatchesRegularExpression('/<iframe[^>]*style="[^"]*min-height:[^"]*"/', $html);
    }
}
