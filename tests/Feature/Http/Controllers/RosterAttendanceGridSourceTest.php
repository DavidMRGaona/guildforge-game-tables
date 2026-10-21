<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the roster page source to pin the
 * attendance grid (players × upcoming sessions): a real table, a window of sessions with paging,
 * cells that show every state the roster query returns and ask before changing anything.
 */
final class RosterAttendanceGridSourceTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/pages/Campaigns/Roster.vue');
    }

    public function test_attendance_is_a_table_of_players_by_sessions(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("<caption class=\"sr-only\">{{ t('campaigns.roster.attendance.caption') }}</caption>", $source);
        $this->assertStringContainsString('v-for="session in visibleSessions"', $source);
        $this->assertStringContainsString('{{ sessionLabel(session) }}', $source);
        $this->assertStringContainsString("t('campaigns.roster.attendance.seats', { taken: session.seats_taken, max: session.max_players })", $source);
        $this->assertSame(1, preg_match_all('/<th\s+scope="row"\s+class="sticky left-0 z-10 border-b border-default bg-surface/', $source));
        $this->assertStringContainsString('class="-mx-4 mt-4 overflow-x-auto sm:mx-0"', $source);
        // The chips are gone
        $this->assertStringNotContainsString('chipLabel', $source);
        $this->assertStringNotContainsString('campaigns.roster.chips', $source);
    }

    public function test_the_grid_shows_a_window_of_sessions_from_the_nearest(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('const pageSize = computed(() => (isSmUp.value ? 4 : 2));', $source);
        $this->assertStringContainsString('const windowStart = ref(0);', $source);
        $this->assertStringContainsString('<p aria-live="polite" class="text-center text-sm text-base-secondary">', $source);
        $this->assertStringContainsString(':disabled="!hasPrevious"', $source);
        $this->assertStringContainsString(':disabled="!hasNext"', $source);
        $this->assertStringContainsString(':aria-label="hasPrevious ? previousLabel : undefined"', $source);
        $this->assertStringContainsString(':aria-label="hasNext ? nextLabel : undefined"', $source);
    }

    public function test_every_state_the_query_returns_has_a_cell(): void
    {
        $source = $this->source();

        foreach (['going', 'absent', 'waiting', 'none'] as $state) {
            $this->assertStringContainsString("    {$state}: 'campaigns.roster.attendance.states.{$state}',", $source);
        }
        $query = (string) file_get_contents(dirname(__DIR__, 4).'/src/Infrastructure/Services/CampaignRosterQuery.php');
        preg_match_all("/\['state' => '([a-z]+)'/", $query, $states);
        $this->assertEqualsCanonicalizing(['going', 'absent', 'waiting', 'none'], array_values(array_unique($states[1])));
    }

    public function test_a_cell_asks_before_changing_the_attendance(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('v-if="isActionable(cellOf(player, session), session)"', $source);
        $this->assertStringContainsString('aria-haspopup="dialog"', $source);
        $this->assertStringContainsString("'inline-flex min-h-[44px] w-full min-w-[5.5rem] cursor-pointer", $source);
        $this->assertStringContainsString(':aria-busy="busyCell === cellKey(player, session) ? \'true\' : undefined"', $source);
        $this->assertStringContainsString('<LoadingSpinner v-if="busyCell === cellKey(player, session)" size="sm" />', $source);
        $this->assertStringContainsString('v-model="showChangeDialog"', $source);
        $this->assertStringContainsString('@confirm="confirmChange"', $source);
        // Clearing an absence needs a free seat, as the server checks it
        $this->assertStringContainsString("return session.has_room ? { kind: 'clearAbsence' } : { kind: 'blocked', reason: 'full' };", $source);
        $this->assertStringNotContainsString('dark:', $source);
    }
}
