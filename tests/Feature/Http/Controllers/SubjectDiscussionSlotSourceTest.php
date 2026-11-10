<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the source of the table and campaign pages.
 * Both put the core's `subject-discussion` slot where a forum module draws the conversation, with
 * the type and id of their publication source; the core's discussion summary is gone.
 */
final class SubjectDiscussionSlotSourceTest extends TestCase
{
    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$file);
    }

    public function test_the_table_page_offers_the_slot_for_tables_on_their_own(): void
    {
        $source = $this->source('resources/js/pages/GameTables/Show.vue');

        $this->assertStringContainsString('name="subject-discussion"', $source);
        $this->assertStringContainsString("subjectType: 'game-table'", $source);
        $this->assertStringContainsString('subjectId: table.id', $source);
        // A campaign session has no conversation of its own
        $this->assertMatchesRegularExpression('/v-if="table\.campaignId === null"[^>]*>\s*<ModuleSlot\s+name="subject-discussion"/', $source);
    }

    public function test_the_campaign_page_offers_the_slot(): void
    {
        $source = $this->source('resources/js/pages/Campaigns/Show.vue');

        $this->assertStringContainsString('name="subject-discussion"', $source);
        $this->assertStringContainsString("subjectType: 'campaign'", $source);
        $this->assertStringContainsString('subjectId: campaign.id', $source);
    }

    public function test_the_pages_no__longer_use_the_core_discussion_summary(): void
    {
        foreach (['resources/js/pages/GameTables/Show.vue', 'resources/js/pages/Campaigns/Show.vue'] as $page) {
            $source = $this->source($page);

            $this->assertStringNotContainsString('SubjectDiscussion', $source);
            $this->assertStringNotContainsString('discussion?:', $source);
        }
    }

    public function test_the_controllers_no_longer_send_a_discussion_prop(): void
    {
        foreach (['GameTableController', 'CampaignController'] as $controller) {
            $source = $this->source("src/Http/Controllers/{$controller}.php");

            $this->assertStringNotContainsString("'discussion'", $source);
            $this->assertStringNotContainsString('SubjectDiscussionActivity', $source);
        }
    }
}
