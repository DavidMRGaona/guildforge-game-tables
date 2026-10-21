<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Providers;

use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
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
        $this->assertStringContainsString('Origen: te lo facilitó :inviter.', $notice->defaultContent);
        $this->assertStringContainsString('como máximo :days días', $notice->defaultContent);
        $this->assertStringNotContainsString('Responsable', $notice->defaultContent);
    }

    public function test_the_preview_renders_the_invitation_email(): void
    {
        $notice = app(LegalMailNoticeRegistryInterface::class)->find('game-tables.campaign_invitation');

        $html = ($notice->preview)('Aviso sin guardar para :inviter en :campaign.');

        $this->assertStringContainsString('Aviso sin guardar para John Doe en Campaña de ejemplo.', $html);
        $this->assertStringContainsString('Ver invitación', $html);
        $this->assertStringContainsString('/politica-de-privacidad', $html);
    }
}
