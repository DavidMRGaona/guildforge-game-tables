<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the guest sign-up modal. A guest accepts the
 * privacy policy there (the GDPR consent of the sign-up), so its link must open the real policy page,
 * taken from the core's routes, and the modal must behave as a dialog for keyboard and screen reader
 * users.
 */
final class GuestRegistrationModalSourceTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/components/GuestRegistrationModal.vue');
    }

    public function test_the_consent_links_to_the_privacy_policy_page(): void
    {
        $source = $this->source();

        // The URL comes from the core's routes, checked there against the legal.show route; a
        // hand-written path went stale once (/privacidad, a 404)
        $this->assertStringContainsString("import { useRoutes } from '@/composables/useRoutes';", $source);
        $this->assertStringContainsString(':href="routes.legal.privacy"', $source);
        $this->assertStringNotContainsString('href="/', $source);
    }

    public function test_the_modal_is_a_native_modal_dialog_named_by_its_heading(): void
    {
        $source = $this->source();

        // showModal() gives the dialog role, keeps focus inside, makes the page behind inert and closes on Escape
        $this->assertStringContainsString('<dialog', $source);
        $this->assertStringContainsString('dialog.showModal();', $source);
        $this->assertStringContainsString('dialog.close();', $source);
        $this->assertStringContainsString(':aria-labelledby="titleId"', $source);
        $this->assertStringContainsString('<h3 :id="titleId"', $source);
    }

    public function test_escape_closes_the_modal_unless_a_sign_up_is_being_sent(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('@cancel.prevent="handleClose"', $source);
        $this->assertStringContainsString("if (!loading.value) {\n        emit('close');", $source);
    }
}
