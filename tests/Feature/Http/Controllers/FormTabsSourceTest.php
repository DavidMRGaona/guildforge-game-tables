<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the source of the tabs of the table and
 * campaign forms. They follow the ARIA tabs pattern (arrow keys, Home and End select a tab and
 * take the focus with them) and the tab list is named in the site's language.
 */
final class FormTabsSourceTest extends TestCase
{
    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/'.$file);
    }

    public function test_arrow_keys_home_and_end_select_a_tab_and_move_the_focus(): void
    {
        $source = $this->source('components/FormTabs.vue');

        $this->assertStringContainsString('@keydown="onKeydown($event, index)"', $source);
        foreach (['ArrowLeft:', 'ArrowRight:', 'Home:', 'End:'] as $key) {
            $this->assertStringContainsString($key, $source);
        }
        // The selected tab is the only one in the tab order, so the focus has to follow it
        $this->assertStringContainsString('.focus()', $source);
        $this->assertStringNotContainsString('@keydown.left', $source);
    }

    public function test_the_tab_list_is_named_in_the_site_language(): void
    {
        $source = $this->source('components/FormTabs.vue');

        $this->assertStringContainsString(':aria-label="t(\'gameTables.formTabs.label\')"', $source);
        $this->assertStringNotContainsString('aria-label="Form sections"', $source);
        $this->assertStringContainsString("label: 'Secciones del formulario'", $this->source('locales/es.ts'));
        $this->assertStringContainsString("label: 'Form sections'", $this->source('locales/en.ts'));
    }

    public function test_inactive_tabs_change_colour_on_hover(): void
    {
        $source = $this->source('components/FormTabs.vue');

        // Layered on both sides: the core's unlayered .text-base-secondary would hide any hover
        $this->assertStringContainsString("'text-[color:var(--color-text-secondary)] hover:text-[color:var(--color-text-primary)]'", $source);
    }
}
