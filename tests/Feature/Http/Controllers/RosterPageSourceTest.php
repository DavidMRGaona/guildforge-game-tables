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
     * Module stylesheets load in a cascade layer below the core utilities, so the attendance cells
     * use theme tokens (their value flips with the theme) instead of a light/dark colour pair.
     */
    public function test_the_going_cell_uses_theme_tokens(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "going: 'border-success/40 bg-success-light text-base-primary',",
            $source
        );
        $this->assertStringNotContainsString('dark:', $source);
    }

    /**
     * A refusal (member, limits) comes back as a successful visit with an error
     * flash: the typed email stays so it can be corrected; only a real invitation clears it.
     */
    public function test_the_invite_form_keeps_the_email_after_a_refusal(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("onSuccess: () => {\n            if (!flashError.value) inviteForm.reset();\n        },", $source);
        $this->assertStringNotContainsString("onSuccess: () => inviteForm.reset(),", $source);
    }

    /**
     * Pending invitations stay listed (to resend or cancel them) when the roster is full: only the
     * invite form depends on can_add.
     */
    public function test_pending_invitations_do_not_depend_on_room_in_the_roster(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('<section v-if="invitations.length > 0"', $source);
        $this->assertStringContainsString('<section v-if="can_add" :class="sectionClass" aria-labelledby="roster-invite-title">', $source);
        $this->assertSame(1, substr_count($source, 'v-if="can_add"'));
    }

    /**
     * WCAG 2.5.3: a cell's accessible name starts with its visible text («✓ Va»).
     */
    public function test_the_cell_name_starts_with_its_visible_text(): void
    {
        $source = $this->source();
        $root = dirname(__DIR__, 4).'/resources/js/locales/';

        $this->assertStringContainsString("t('campaigns.roster.attendance.accessibleName', {\n".'        label: `${stateIcons[state]} ${stateLabel(state)}`,', $source);
        $this->assertStringContainsString("accessibleName: '{label} — {name}, {date}. {action}',", (string) file_get_contents($root.'es.ts'));
        $this->assertStringContainsString("accessibleName: '{label} — {name}, {date}. {action}',", (string) file_get_contents($root.'en.ts'));
    }
}
