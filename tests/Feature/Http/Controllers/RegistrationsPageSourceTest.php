<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the «Gestionar inscritos» page source to
 * pin what the server expects from it.
 */
final class RegistrationsPageSourceTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/pages/GameTables/Registrations.vue');
    }

    /**
     * The server refuses a reason over 500 characters (RejectTableRegistrationRequest).
     */
    public function test_the_reason_is_capped_as_the_server_caps_it(): void
    {
        $this->assertStringContainsString('maxlength="500"', $this->source());
    }

    /**
     * An empty reason is sent as null, so the email carries no «Motivo» line.
     */
    public function test_an_empty_reason_is_sent_as_null(): void
    {
        $this->assertStringContainsString("{ reason: reason === '' ? null : reason }", $this->source());
    }

    /**
     * Module stylesheets load in a cascade layer below the core utilities: theme tokens only.
     */
    public function test_the_page_uses_theme_tokens(): void
    {
        $this->assertStringNotContainsString('dark:', $this->source());
    }

    /**
     * A button that cannot act stays focusable and says so (aria-disabled), as on the roster page.
     */
    public function test_unavailable_buttons_use_aria_disabled(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(':aria-disabled=', $source);
        $this->assertStringNotContainsString(':disabled="processing"', $source);
    }
}
