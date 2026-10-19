<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources to pin that the visitor sees
 * the server's answer after the registration forms.
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
}
