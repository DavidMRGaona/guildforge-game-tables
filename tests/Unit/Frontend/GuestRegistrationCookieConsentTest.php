<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source guards, not behaviour tests: the module has no Vue test runner. The guest registration
 * cookie is a preferences cookie, so it is only touched after asking the core's consent contract,
 * which the module build must share with the page instead of bundling its own copy.
 */
final class GuestRegistrationCookieConsentTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../../';

    public function test_the_cookie_asks_the_core_contract_before_touching_the_cookie(): void
    {
        $source = $this->source('resources/js/utils/guestRegistrationCookie.ts');

        $this->assertStringContainsString("from '@/contracts/cookieConsent'", $source);
        $this->assertStringContainsString("hasCookieConsent('preferences')", $source);
        $this->assertStringNotContainsString('GuildForgeCookieConsent', $source);

        $consent = strpos($source, "hasCookieConsent('preferences')");
        $cookie = strpos($source, 'document.cookie');
        $this->assertNotFalse($consent);
        $this->assertNotFalse($cookie);
        $this->assertLessThan($cookie, $consent);
    }

    public function test_the_modal_follows_consent_changes_through_the_contract(): void
    {
        $source = $this->source('resources/js/components/GuestRegistrationModal.vue');

        $this->assertStringContainsString('watch(canUseGuestCookie, checkBrowserHint)', $source);
        $this->assertStringNotContainsString('guildforge:cookie-consent-changed', $source);
    }

    public function test_the_build_shares_the_core_public_api_instead_of_bundling_it(): void
    {
        // A bundled copy of the contract would never hear from the consent provider
        // The core's build keeps its public API, the consent contract included, out of the bundle
        $this->assertStringContainsString("import { defineModuleConfig } from '../../vite.module.config.ts';", $this->source('vite.config.ts'));
        $this->assertStringContainsString("defineModuleConfig({ name: 'game-tables', root: import.meta.dirname })", $this->source('vite.config.ts'));
    }

    private function source(string $file): string
    {
        $contents = file_get_contents(self::ROOT.$file);
        $this->assertNotFalse($contents, "{$file} is readable");

        return $contents;
    }
}
