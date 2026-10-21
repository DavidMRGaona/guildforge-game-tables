<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources to pin that every page of the
 * module can be reached by clicking: «Mis mesas» / «Mis campañas» from the profile, the roster
 * from the profile and the campaign page for its directors, the calendar on phones, and the
 * tables and campaigns lists from each other.
 */
final class NavigationLinksSourceTest extends TestCase
{
    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/'.$file);
    }

    public function test_the_profile_sections_link_to_the_my_pages(): void
    {
        $sections = [
            'components/profile/ProfileCreatedTablesSection.vue' => ["const manageUrl = '/mesas/mis-mesas';", "t('gameTables.profile.created.manage')", "gate.reason('form')"],
            'components/profile/ProfileCreatedCampaignsSection.vue' => ["const manageUrl = '/campanas/mis-campanas';", "t('campaigns.profile.manage')", "gate.reason('form')"],
        ];

        foreach ($sections as $file => $needles) {
            $source = $this->source($file);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $file);
            }
            // In the list and in the empty state (when the user may create)
            $this->assertSame(2, substr_count($source, ':href="manageUrl"'), $file);
        }
    }

    public function test_a_published_campaign_card_links_its_director_to_the_roster(): void
    {
        $source = $this->source('components/profile/ProfileCreatedCampaignCard.vue');

        $this->assertMatchesRegularExpression('/v-if="!showActions && campaign\.rosterUrl"/', $source);
        $this->assertStringContainsString(':href="campaign.rosterUrl"', $source);
        $this->assertStringContainsString("t('campaigns.myCampaigns.roster')", $source);
        $this->assertStringContainsString('v-if="campaign.pendingRequests > 0"', $source);
        $this->assertStringContainsString("t('campaigns.myCampaigns.pendingRequests', { count: campaign.pendingRequests }, campaign.pendingRequests)", $source);
    }

    public function test_the_campaign_page_offers_its_director_a_roster_button_with_the_pending_requests(): void
    {
        $source = $this->source('components/campaigns/CampaignJoinBox.vue');

        $this->assertStringContainsString(':href="joinBox.roster_url"', $source);
        $this->assertStringContainsString(':class="primaryButtonClass"', $source);
        $this->assertStringContainsString('v-if="joinBox.pending_requests > 0"', $source);
        $this->assertStringContainsString("gate.reason('form')", $source);
    }

    public function test_the_list_and_calendar_toggle_shows_on_phones(): void
    {
        foreach (['pages/GameTables/Index.vue' => 'list', 'pages/GameTables/Calendar.vue' => 'calendar'] as $page => $current) {
            $source = $this->source($page);

            $this->assertStringContainsString("<TablesViewToggle current=\"{$current}\"", $source, $page);
            $this->assertStringNotContainsString('role="tablist"', $source, $page);
        }

        $toggle = $this->source('components/TablesViewToggle.vue');
        // No `hidden` utility (aria-hidden on the icons is fine)
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])hidden(?![\w-])/', $toggle);
        $this->assertStringContainsString('min-h-[44px] min-w-[44px]', $toggle);
        $this->assertStringContainsString(":aria-current=\"current === 'list' ? 'page' : undefined\"", $toggle);
    }

    public function test_the_tables_and_campaigns_lists_link_to_each_other(): void
    {
        foreach (['pages/GameTables/Index.vue', 'pages/GameTables/Calendar.vue'] as $page) {
            $source = $this->source($page);
            $this->assertStringContainsString('href="/campanas"', $source, $page);
            $this->assertStringContainsString("t('campaigns.viewAll')", $source, $page);
        }

        $campaigns = $this->source('pages/Campaigns/Index.vue');
        $this->assertStringContainsString('href="/mesas"', $campaigns);
        $this->assertStringContainsString("t('gameTables.viewAll')", $campaigns);
    }
}
