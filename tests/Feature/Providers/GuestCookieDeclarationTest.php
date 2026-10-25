<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Providers;

use App\Application\Cookies\DTOs\DeclaredCookieDTO;
use App\Application\Cookies\Services\CookieDeclarationRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Cookies\Enums\CookieType;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The guest cookie is declared in the core's registry, whether or not a cookie consent module
 * is installed to read it.
 */
final class GuestCookieDeclarationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_the_guest_registrations_cookie_is_declared_in_preferences(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set('guild_name', 'Example guild');

        $declared = $this->declaredGuestCookie();

        $this->assertSame('preferences', $declared->categorySlug);
        $this->assertSame('Example guild', $declared->provider);
        $this->assertSame(
            'Recuerda en qué mesas te has inscrito como invitado desde este navegador, para avisarte si intentas inscribirte otra vez.',
            $declared->purpose,
        );
        $this->assertSame('90 días', $declared->duration);
        $this->assertSame(CookieType::FirstParty, $declared->type);
    }

    public function test_without_a_site_name_the_provider_is_the_installation_name(): void
    {
        config(['app.name' => 'Example installation']);
        $this->app->make(SettingsServiceInterface::class)->set('guild_name', '');

        $this->assertSame('Example installation', $this->declaredGuestCookie()->provider);
    }

    /**
     * The declaration runs when the registry is resolved: resolve it afresh so it reads the
     * site name the test has just set.
     */
    private function declaredGuestCookie(): DeclaredCookieDTO
    {
        $this->app->forgetInstance(CookieDeclarationRegistryInterface::class);

        $declared = collect($this->app->make(CookieDeclarationRegistryInterface::class)->all())
            ->first(fn (DeclaredCookieDTO $cookie): bool => $cookie->name === 'gt_guest_registrations');

        $this->assertInstanceOf(DeclaredCookieDTO::class, $declared);

        return $declared;
    }
}
