<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources to pin that «Mis mesas»,
 * «Mis campañas» and the profile's drafts offer a draft's actions (edit, send to review, delete)
 * through one shared component, so the places cannot drift apart, and that the profile shows them
 * and the moderator's reason only while the author may still change the draft.
 */
final class DraftActionsSourceTest extends TestCase
{
    private const SHARED = 'resources/js/components/DraftActions.vue';

    private const REASON = 'resources/js/components/DraftRejectionReason.vue';

    private const PAGES = [
        'resources/js/pages/GameTables/MyTables.vue' => 'table',
        'resources/js/pages/Campaigns/MyCampaigns.vue' => 'campaign',
    ];

    private const CARDS = [
        'resources/js/components/profile/ProfileCreatedTableCard.vue' => 'table',
        'resources/js/components/profile/ProfileCreatedCampaignCard.vue' => 'campaign',
    ];

    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$file);
    }

    public function test_the_my_pages_and_the_profile_cards_use_the_shared_actions(): void
    {
        foreach (self::PAGES + self::CARDS as $file => $kind) {
            $source = $this->source($file);

            $this->assertMatchesRegularExpression("#import DraftActions from '(\.\./)+(components/)?DraftActions\.vue';#", $source, $file);
            $this->assertStringContainsString('<DraftActions', $source, $file);
            $this->assertStringContainsString("kind=\"{$kind}\"", $source, $file);
            // The requests and their confirmations live only in the shared component
            $this->assertStringNotContainsString('enviar-revision', $source, $file);
            $this->assertStringNotContainsString('<ConfirmDialog', $source, $file);
            $this->assertStringNotContainsString('router.delete', $source, $file);
        }
    }

    public function test_the_my_pages_follow_the_servers_single_rule(): void
    {
        foreach (array_keys(self::PAGES) as $file) {
            $source = $this->source($file);

            // can_edit is FrontendCreationStatus::allowsAuthorChanges(), the rule the server enforces
            $this->assertMatchesRegularExpression('/<DraftActions\s+v-(?:else-)?if="(table|campaign)\.can_edit"/', $source, $file);
            $this->assertStringNotContainsString('function canSubmitForReview', $source, $file);
            $this->assertStringNotContainsString('function canDelete', $source, $file);
            $this->assertStringNotContainsString('function canEdit', $source, $file);
            // Their redirect stays the default one
            $this->assertStringNotContainsString('return-to=', $source, $file);
        }
    }

    public function test_the_profile_cards_offer_the_actions_only_while_the_author_may_change_the_draft(): void
    {
        foreach (self::CARDS as $file => $kind) {
            $source = $this->source($file);

            $this->assertMatchesRegularExpression("/<DraftActions\s+v-if=\"showActions && {$kind}\.canEdit[ &.a-zA-Z]*\"/", $source, $file);
            $this->assertStringContainsString('return-to="profile"', $source, $file);
            // No lone «Editar» link any more
            $this->assertStringNotContainsString('/editar`', $source, $file);
            $this->assertStringNotContainsString('showEditLink', $source, $file);
        }

        foreach (['ProfileCreatedTablesSection.vue', 'ProfileCreatedCampaignsSection.vue'] as $section) {
            $source = $this->source('resources/js/components/profile/'.$section);
            $this->assertStringContainsString(':show-actions="true"', $source, $section);
            $this->assertStringNotContainsString('show-edit-link', $source, $section);
        }
    }

    public function test_the_profile_cards_show_the_rejection_reason_only_while_the_draft_is_editable(): void
    {
        foreach (self::CARDS as $file => $kind) {
            $source = $this->source($file);

            $this->assertStringContainsString("import DraftRejectionReason from '../DraftRejectionReason.vue';", $source, $file);
            $this->assertMatchesRegularExpression(
                "/<DraftRejectionReason\s+v-if=\"showActions && {$kind}\.canEdit && {$kind}\.rejectionReason\"/",
                $source,
                $file,
            );
        }

        $reason = $this->source(self::REASON);
        $this->assertStringContainsString('whitespace-pre-line', $reason);
        $this->assertStringContainsString('line-clamp-3', $reason);
        $this->assertStringContainsString(':aria-expanded="expanded"', $reason);
        $this->assertStringContainsString('v-if="overflows"', $reason);
        // A note (present on load, not a live change) that stands out: error tokens, thick left border, icon
        $this->assertStringContainsString('role="note"', $reason);
        $this->assertStringContainsString('rounded-lg border border-l-4 border-error/40 border-l-error bg-error-light p-4', $reason);
        $this->assertStringContainsString('class="h-5 w-5 shrink-0 text-error"', $reason);
        $this->assertStringContainsString('class="font-semibold text-error"', $reason);
    }

    public function test_the_my_pages_show_the_rejection_reason_with_the_same_note(): void
    {
        foreach (self::PAGES as $file => $kind) {
            $source = $this->source($file);
            $var = $kind;

            $this->assertStringContainsString("import DraftRejectionReason from '../../components/DraftRejectionReason.vue';", $source, $file);
            $this->assertMatchesRegularExpression("/<DraftRejectionReason\s+v-if=\"{$var}\.rejection_reason\"\s+kind=\"{$kind}\"/", $source, $file);
        }
    }

    public function test_the_my_pages_lead_back_to_their_profile_tab(): void
    {
        $tabs = [
            'resources/js/pages/GameTables/MyTables.vue' => 'gametables-created',
            'resources/js/pages/Campaigns/MyCampaigns.vue' => 'gametables-campaigns',
        ];

        foreach ($tabs as $file => $tab) {
            $source = $this->source($file);
            $this->assertStringContainsString("href=\"/perfil?tab={$tab}\"", $source, $file);
            $this->assertStringContainsString("t('gameTables.backToProfile')", $source, $file);
        }
    }

    public function test_the_shared_actions_confirm_and_keep_the_profile_tab(): void
    {
        $source = $this->source(self::SHARED);

        foreach (['variant="secondary"', 'variant="primary"', 'variant="danger"'] as $variant) {
            $this->assertStringContainsString($variant, $source);
        }
        $this->assertSame(2, substr_count($source, '<ConfirmDialog'));
        // Only the whitelisted target goes to the server, never a URL
        $this->assertStringContainsString("returnTo?: 'profile' | null", $source);
        $this->assertStringContainsString("'?return_to=profile'", $source);
        // The profile keeps its tab in component state: the visit must not remount the page
        $this->assertStringContainsString('preserveState: true', $source);
        // Below sm a full-width row of equal buttons; BaseButton is at least 44 px tall
        $this->assertStringContainsString('grid w-full grid-cols-3', $source);
        $this->assertStringContainsString('sm:flex', $source);
    }
}
