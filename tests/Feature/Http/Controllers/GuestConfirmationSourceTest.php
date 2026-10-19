<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources to pin that the visitor sees
 * the server's answer after the guest form and after the confirmation button.
 */
final class GuestConfirmationSourceTest extends TestCase
{
    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/'.$file);
    }

    private function template(string $source): string
    {
        $start = strpos($source, '<template>');
        $this->assertNotFalse($start, 'template missing');

        return substr($source, $start);
    }

    public function test_the_registration_button_shows_the_flash_message_of_the_server(): void
    {
        $source = $this->source('components/RegistrationButton.vue');

        $this->assertStringContainsString("import { useFlashMessages } from '@/composables/useFlashMessages';", $source);
        $this->assertStringContainsString('const { success: flashSuccess, error: flashError } = useFlashMessages();', $source);
        $this->assertStringNotContainsString('The page will reload with flash message via Inertia', $source);

        $template = $this->template($source);
        $this->assertMatchesRegularExpression('/v-if="flashSuccess"\s+role="status"/', $template);
        $this->assertStringContainsString('{{ flashSuccess }}', $template);
        $this->assertMatchesRegularExpression('/v-if="flashError && !error"\s+role="alert"/', $template);
        $this->assertStringContainsString('{{ flashError }}', $template);
    }

    public function test_the_confirmation_page_shows_the_result_of_the_button(): void
    {
        $source = $this->source('pages/GameTables/ConfirmRegistration.vue');

        $this->assertStringContainsString('result: ConfirmationResult | null;', $source);
        $this->assertStringContainsString("tone: 'success' | 'info' | 'error';", $source);

        $template = $this->template($source);
        $this->assertStringContainsString('<template v-if="result">', $template);
        $this->assertStringContainsString('{{ result.message }}', $template);
        $this->assertStringContainsString('{{ participant.status_label }}', $template);
    }

    public function test_the_confirmation_page_nests_no_button_in_a_link(): void
    {
        $template = $this->template($this->source('pages/GameTables/ConfirmRegistration.vue'));

        $this->assertDoesNotMatchRegularExpression('/<Link\b[^>]*>\s*<BaseButton\b/', $template);
    }
}
