<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the roster page source to pin
 * what a browser check found.
 */
final class RosterPageSourceTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/pages/Campaigns/Roster.vue');
    }

    /**
     * Module stylesheets load in a cascade layer below the core utilities, so the chips use theme
     * tokens (their value flips with the theme) instead of a light/dark colour pair.
     */
    public function test_the_going_chip_uses_theme_tokens(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "going: 'border-success/40 bg-success-light text-base-primary',",
            $source
        );
        $this->assertStringNotContainsString('dark:', $source);
    }

    /**
     * A refusal (unknown email, full roster) comes back as a successful visit with an error
     * flash: the typed email stays so it can be corrected; only a real addition clears it.
     */
    public function test_the_add_form_keeps_the_email_after_a_refusal(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("onSuccess: () => {\n            if (!flashError.value) addForm.reset('email');\n        },", $source);
        $this->assertStringNotContainsString("onSuccess: () => addForm.reset('email'),", $source);
    }

    /**
     * WCAG 2.5.3: the chip's accessible name starts with its visible text («08/11 ✓ va»).
     */
    public function test_the_chip_name_starts_with_its_visible_text(): void
    {
        $source = $this->source();
        $root = dirname(__DIR__, 4).'/resources/js/locales/';

        $this->assertStringContainsString("t('campaigns.roster.chips.accessibleName', {\n        label: chipLabel(cell),", $source);
        $this->assertStringContainsString("accessibleName: '{label} — {name}, {date}',", (string) file_get_contents($root.'es.ts'));
        $this->assertStringContainsString("accessibleName: '{label} — {name}, {date}',", (string) file_get_contents($root.'en.ts'));
    }
}
