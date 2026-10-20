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
     * Module stylesheets load in a cascade layer below the core utilities, so a light
     * utility the core ships beats a dark variant only the module ships. The core ships
     * `text-green-800` but not `dark:text-green-300`, which left the «va» chip as dark
     * green text on a dark background (contrast under 2:1); `dark:text-green-400` is in
     * the core stylesheet, so it wins in dark mode.
     */
    public function test_the_going_chip_uses_a_dark_text_colour_the_core_stylesheet_ships(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "going: 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-400',",
            $source
        );
        $this->assertStringNotContainsString('dark:text-green-300', $source);
    }
}
