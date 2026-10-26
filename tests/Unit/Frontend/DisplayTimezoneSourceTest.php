<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source guards, not behaviour tests: the module has no Vue test runner. The pages show dates in
 * the site's zone and fill datetime-local fields with the wall time the server computes, never by
 * cutting an ISO string (in UTC it would be two hours off in summer).
 */
final class DisplayTimezoneSourceTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../../';

    public function test_the_edit_page_fills_the_start_with_the_wall_time_from_the_server(): void
    {
        $source = $this->source('resources/js/pages/GameTables/Edit.vue');

        $this->assertStringContainsString('starts_at: props.startsAtWallTime ??', $source);
        $this->assertStringNotContainsString('substring(0, 16)', $source);
        $this->assertStringNotContainsString('slice(0, 16)', $source);
    }

    public function test_the_edit_page_finds_the_slot_of_the_table_by_instant(): void
    {
        $source = $this->source('resources/js/pages/GameTables/Edit.vue');

        $this->assertStringContainsString('Date.parse(slot.start_time) === tableStart', $source);
        $this->assertStringNotContainsString('slot.start_time === tableStartTime', $source);
    }

    public function test_the_not_eligible_pages_show_the_opening_in_the_site_zone(): void
    {
        foreach (['resources/js/pages/GameTables/CreateNotEligible.vue', 'resources/js/pages/Campaigns/CreateNotEligible.vue'] as $file) {
            $source = $this->source($file);

            $this->assertStringContainsString("import { VENUE_TIMEZONE } from '@/utils/datetime';", $source, $file);
            $this->assertStringContainsString('timeZone: VENUE_TIMEZONE', $source, $file);
            $this->assertStringNotContainsString("d(date, 'long')", $source, $file);
        }
    }

    private function source(string $file): string
    {
        $contents = file_get_contents(self::ROOT.$file);
        $this->assertNotFalse($contents, "{$file} is readable");

        return $contents;
    }
}
