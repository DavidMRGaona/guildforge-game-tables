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

    /**
     * Compares without whitespace or trailing commas: Prettier decides where the code breaks
     * its lines, and adds a trailing comma when it breaks a list.
     */
    private function assertCodeContains(string $needle, string $source, string $message = ''): void
    {
        $this->assertStringContainsString($this->compact($needle), $this->compact($source), $message);
    }

    private function compact(string $code): string
    {
        return (string) preg_replace('/,(?=[)\]}]|$)/', '', (string) preg_replace('/\s+/', '', $code));
    }

    public function test_attendance_is_a_table_of_players_by_sessions(): void
    {
        $source = $this->source();

        $this->assertCodeContains("<caption class=\"sr-only\">{{ t('campaigns.roster.attendance.caption') }}</caption>", $source);
        $this->assertCodeContains('v-for="session in visibleSessions"', $source);
        $this->assertCodeContains('{{ sessionLabel(session) }}', $source);
        $this->assertCodeContains("t('campaigns.roster.attendance.seats', { taken: session.seats_taken, max: session.max_players })", $source);
        $this->assertSame(1, preg_match_all('/<th\s+scope="row"\s+class="sticky left-0 z-10 w-full border-b border-default bg-surface/', $source));
        $this->assertCodeContains('class="-mx-4 mt-4 overflow-x-auto sm:mx-0"', $source);
        // The chips are gone
        $this->assertStringNotContainsString('chipLabel', $source);
        $this->assertStringNotContainsString('campaigns.roster.chips', $source);
    }

    public function test_the_grid_shows_a_window_of_sessions_from_the_nearest(): void
    {
        $source = $this->source();

        $this->assertCodeContains('const pageSize = computed(() => (isSmUp.value ? 4 : 2));', $source);
        $this->assertCodeContains('const windowStart = ref(0);', $source);
        $this->assertCodeContains('<p aria-live="polite" class="text-center text-sm text-base-secondary">', $source);
        $this->assertCodeContains(':disabled="!hasPrevious"', $source);
        $this->assertCodeContains(':disabled="!hasNext"', $source);
        $this->assertCodeContains(':aria-label="hasPrevious ? previousLabel : undefined"', $source);
        $this->assertCodeContains(':aria-label="hasNext ? nextLabel : undefined"', $source);
    }

    public function test_every_state_the_query_returns_has_a_cell(): void
    {
        $source = $this->source();

        foreach (['going', 'absent', 'waiting', 'none'] as $state) {
            $this->assertCodeContains("    {$state}: 'campaigns.roster.attendance.states.{$state}',", $source);
        }
        $query = (string) file_get_contents(dirname(__DIR__, 4).'/src/Infrastructure/Services/CampaignRosterQuery.php');
        preg_match_all("/\['state' => '([a-z]+)'/", $query, $states);
        $this->assertEqualsCanonicalizing(['going', 'absent', 'waiting', 'none'], array_values(array_unique($states[1])));
    }

    public function test_a_cell_asks_before_changing_the_attendance(): void
    {
        $source = $this->source();

        $this->assertCodeContains('v-if="isActionable(cellOf(player, session), session)"', $source);
        $this->assertCodeContains('aria-haspopup="dialog"', $source);
        $this->assertCodeContains("'inline-flex min-h-[44px] w-full min-w-[4.5rem] cursor-pointer", $source);
        $this->assertCodeContains(':aria-busy="busyCell === cellKey(player, session) ? \'true\' : undefined"', $source);
        $this->assertCodeContains('<LoadingSpinner v-if="busyCell === cellKey(player, session)" size="sm" />', $source);
        $this->assertCodeContains('v-model="showChangeDialog"', $source);
        $this->assertCodeContains('@confirm="confirmChange"', $source);
        // Clearing an absence needs a free seat, as the server checks it
        $this->assertCodeContains("return session.has_room ? { kind: 'clearAbsence' } : { kind: 'blocked', reason: 'full' };", $source);
        $this->assertStringNotContainsString('dark:', $source);
    }

    public function test_two_sessions_fit_a_phone_with_the_remove_button_under_the_name(): void
    {
        $source = $this->source();

        // Phones: «Quitar» under the name, the actions column hidden
        // Hidden through a wrapper with no display utility: the core's own (inline-flex, hidden) sit in a later layer and win
        $this->assertCodeContains('<div class="mt-2 sm:hidden">', $source);
        $this->assertStringNotContainsString('sm:table-cell', $source);
        $this->assertCodeContains('<th scope="col" class="border-b border-default py-2 pl-2 pr-4 max-sm:hidden sm:pr-0">', $source);
        $this->assertCodeContains('<td class="border-b border-default py-2 pl-2 pr-4 text-right align-middle max-sm:hidden sm:pr-0">', $source);
        // Wide screens: the name column takes the spare width, sessions keep theirs beside the actions
        $this->assertSame(2, substr_count($source, 'class="sticky left-0 z-10 w-full border-b border-default bg-surface'));
        $this->assertCodeContains('text-center align-bottom font-medium text-base-primary sm:min-w-[8rem]"', $source);
        // The way back is a touch target too, without moving the heading
        $this->assertCodeContains('class="-my-3 inline-flex min-h-[44px] items-center gap-1 text-sm text-base-secondary', $source);
    }
}
