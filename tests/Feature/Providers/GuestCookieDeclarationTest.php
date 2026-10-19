<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Providers;

use Modules\CookieConsent\Application\Services\CookieDeclarationRegistryInterface;
use Tests\Support\Modules\ModuleTestCase;

final class GuestCookieDeclarationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    public function test_the_guest_registrations_cookie_is_declared_in_preferences(): void
    {
        $this->discoverModules();

        if ($this->findModule('cookie-consent') === null) {
            $this->markTestSkipped('The cookie-consent module is not installed.');
        }

        // cookie-consent first, so its classes exist when this module's provider boots
        $this->enableModule('cookie-consent');
        $this->enableModule('game-tables');

        $declared = collect($this->app->make(CookieDeclarationRegistryInterface::class)->all())
            ->firstWhere('name', 'gt_guest_registrations');

        $this->assertNotNull($declared);
        $this->assertSame('preferences', $declared->categorySlug);
        $this->assertSame(config('app.name'), $declared->provider);
        $this->assertSame(
            'Recuerda en qué mesas te has inscrito como invitado desde este navegador, para avisarte si intentas inscribirte otra vez.',
            $declared->purpose,
        );
        $this->assertSame('90 días', $declared->duration);
    }
}
