<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources to pin that the request to join
 * a campaign is written in a dialog behind one button, that withdrawing asks first, and that the
 * roster page's actions look and behave like buttons.
 */
final class JoinAndRosterButtonsSourceTest extends TestCase
{
    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$file);
    }

    public function test_the_join_box_opens_the_request_dialog_from_one_button(): void
    {
        $box = $this->source('resources/js/components/campaigns/CampaignJoinBox.vue');

        // No inline form any more: one primary button, full width on phones
        $this->assertStringNotContainsString('<textarea', $box);
        $this->assertStringContainsString("import JoinRequestDialog from './JoinRequestDialog.vue';", $box);
        $this->assertMatchesRegularExpression('/<BaseButton\s+v-else\s+variant="primary"\s+size="sm"\s+class="w-full sm:w-auto"\s+aria-haspopup="dialog"\s+@click="openRequest"/', $box);
        $this->assertStringContainsString('<JoinRequestDialog', $box);
        $this->assertStringContainsString(':error="requestError"', $box);
        $this->assertStringContainsString(':processing="processing"', $box);
        // Once sent, the opening button is gone: the focus moves to the new pending status
        $this->assertMatchesRegularExpression('/:id="pendingStatusId"\s+ref="pendingStatus"\s+role="status"\s+tabindex="-1"/', $box);
        $this->assertStringContainsString("showRequestDialog.value = false;\n                void nextTick(() => pendingStatus.value?.focus());", $box);
        // Withdrawing asks first
        $this->assertStringContainsString('@click="askWithdraw"', $box);
        $this->assertStringContainsString('v-model="showWithdrawDialog"', $box);
        $this->assertStringContainsString('@confirm="confirmWithdraw"', $box);
    }

    public function test_the_request_dialog_is_an_accessible_modal_with_the_validated_limit(): void
    {
        $dialog = $this->source('resources/js/components/campaigns/JoinRequestDialog.vue');

        foreach ([
            '<dialog',
            'role="dialog"',
            'aria-modal="true"',
            ':aria-labelledby="titleId"',
            'showModal()',
            'textarea.value?.focus()',
            'opener?.focus()',
            '@cancel.prevent="close"',
            '@click="onBackdropClick"',
            ':disabled="processing"',
            ':loading="processing"',
            "t('campaigns.join.dialogTitle', { title: campaignTitle })",
            "t('campaigns.join.requestHint')",
            "t('campaigns.join.counter', { count: message.length, max: MAX_LENGTH })",
            'role="alert"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dialog);
        }

        // The counter's limit is the one the request validates
        preg_match('/const MAX_LENGTH = (\d+);/', $dialog, $limit);
        $this->assertSame(1, preg_match("/'message' => \['nullable', 'string', 'max:(\d+)'\]/", $this->source('src/Http/Requests/RequestToJoinCampaignRequest.php'), $rule));
        $this->assertSame($rule[1], $limit[1] ?? null);
    }

    public function test_the_roster_actions_are_buttons_that_ask_before_removing(): void
    {
        $roster = $this->source('resources/js/pages/Campaigns/Roster.vue');

        // «Quitar»: an outlined danger button named after the player, confirmed in a dialog
        $this->assertStringContainsString("const dangerOutlineClass = 'inline-flex min-h-[44px]", $roster);
        $this->assertStringContainsString(':class="dangerOutlineClass"', $roster);
        $this->assertStringContainsString("t('campaigns.roster.players.removeNamed', { name: player.name })", $roster);
        $this->assertStringContainsString(':message="removeMessage"', $roster);
        // The dialog only promises the email the server sends
        $this->assertStringContainsString("props.removal_notice ? 'campaigns.roster.players.removeMessage' : 'campaigns.roster.players.removeMessageNoEmail'", $roster);
        $this->assertStringNotContainsString('variant="ghost"', $roster);
        // Requests and the add form: full-width buttons on phones
        $this->assertStringContainsString('class="grid flex-shrink-0 grid-cols-2 gap-2 sm:flex"', $roster);
        $this->assertStringContainsString('<BaseButton type="submit" variant="primary" class="w-full sm:w-auto" :loading="addForm.processing">', $roster);
        // The helper line sits under the heading
        $this->assertLessThan(
            strpos($roster, '<table'),
            strpos($roster, "t('campaigns.roster.footnote')"),
        );
    }
}
