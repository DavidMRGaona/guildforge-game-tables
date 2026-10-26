<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Tests\TestCase;

final class FrontendCreationStatusTest extends TestCase
{
    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('draft', FrontendCreationStatus::Draft->value);
        $this->assertEquals('pending_review', FrontendCreationStatus::PendingReview->value);
        $this->assertEquals('approved', FrontendCreationStatus::Approved->value);
        $this->assertEquals('rejected', FrontendCreationStatus::Rejected->value);
    }

    public function test_it_returns_label(): void
    {
        foreach (FrontendCreationStatus::cases() as $status) {
            $this->assertSame(__('game-tables::messages.enums.frontend_creation_status.'.$status->value), $status->label());
        }
    }

    public function test_it_returns_color(): void
    {
        $this->assertEquals('gray', FrontendCreationStatus::Draft->color());
        $this->assertEquals('warning', FrontendCreationStatus::PendingReview->color());
        $this->assertEquals('success', FrontendCreationStatus::Approved->color());
        $this->assertEquals('danger', FrontendCreationStatus::Rejected->color());
    }

    public function test_it_returns_icon(): void
    {
        $this->assertSame('heroicon-o-pencil', FrontendCreationStatus::Draft->icon());
        $this->assertSame('heroicon-o-clock', FrontendCreationStatus::PendingReview->icon());
        $this->assertSame('heroicon-o-check-circle', FrontendCreationStatus::Approved->icon());
        $this->assertSame('heroicon-o-x-circle', FrontendCreationStatus::Rejected->icon());
    }

    public function test_it_returns_options(): void
    {
        $options = FrontendCreationStatus::options();

        $this->assertCount(4, $options);
        $this->assertArrayHasKey('draft', $options);
        $this->assertArrayHasKey('pending_review', $options);
        $this->assertArrayHasKey('approved', $options);
        $this->assertArrayHasKey('rejected', $options);
    }

    public function test_it_returns_values(): void
    {
        $values = FrontendCreationStatus::values();

        $this->assertCount(4, $values);
        $this->assertContains('draft', $values);
        $this->assertContains('pending_review', $values);
        $this->assertContains('approved', $values);
        $this->assertContains('rejected', $values);
    }

    public function test_is_pending_returns_true_for_draft_and_pending_review(): void
    {
        $this->assertTrue(FrontendCreationStatus::Draft->isPending());
        $this->assertTrue(FrontendCreationStatus::PendingReview->isPending());
        $this->assertFalse(FrontendCreationStatus::Approved->isPending());
        $this->assertFalse(FrontendCreationStatus::Rejected->isPending());
    }

    public function test_is_resolved_returns_true_for_approved_and_rejected(): void
    {
        $this->assertFalse(FrontendCreationStatus::Draft->isResolved());
        $this->assertFalse(FrontendCreationStatus::PendingReview->isResolved());
        $this->assertTrue(FrontendCreationStatus::Approved->isResolved());
        $this->assertTrue(FrontendCreationStatus::Rejected->isResolved());
    }

    public function test_can_edit_returns_true_for_draft_and_rejected(): void
    {
        $this->assertTrue(FrontendCreationStatus::Draft->canEdit());
        $this->assertFalse(FrontendCreationStatus::PendingReview->canEdit());
        $this->assertFalse(FrontendCreationStatus::Approved->canEdit());
        $this->assertTrue(FrontendCreationStatus::Rejected->canEdit());
    }

    public function test_can_submit_for_review_returns_true_for_draft_and_rejected(): void
    {
        $this->assertTrue(FrontendCreationStatus::Draft->canSubmitForReview());
        $this->assertFalse(FrontendCreationStatus::PendingReview->canSubmitForReview());
        $this->assertFalse(FrontendCreationStatus::Approved->canSubmitForReview());
        $this->assertTrue(FrontendCreationStatus::Rejected->canSubmitForReview());
    }

    // What "Mis mesas" and "Mis campañas" offer the author: edit, submit and delete only an unpublished draft or rejected item
    public function test_the_author_may_change_only_an_unpublished_draft_or_rejected_item(): void
    {
        $this->assertTrue(FrontendCreationStatus::allowsAuthorChanges(FrontendCreationStatus::Draft, isPublished: false));
        $this->assertTrue(FrontendCreationStatus::allowsAuthorChanges(FrontendCreationStatus::Rejected, isPublished: false));
        $this->assertFalse(FrontendCreationStatus::allowsAuthorChanges(FrontendCreationStatus::PendingReview, isPublished: false));
        $this->assertFalse(FrontendCreationStatus::allowsAuthorChanges(FrontendCreationStatus::Approved, isPublished: false));
        $this->assertFalse(FrontendCreationStatus::allowsAuthorChanges(null, isPublished: false));

        foreach ([...FrontendCreationStatus::cases(), null] as $status) {
            $this->assertFalse(FrontendCreationStatus::allowsAuthorChanges($status, isPublished: true));
        }
    }

    // Approving is the only way to publish a web-created item: the panel publishes freely only
    // items created there (no status) and approved ones (which it may also unpublish and republish)
    public function test_the_panel_publishes_freely_only_panel_created_and_approved_items(): void
    {
        $this->assertTrue(FrontendCreationStatus::allowsPanelPublishing(null));
        $this->assertTrue(FrontendCreationStatus::allowsPanelPublishing(FrontendCreationStatus::Approved));
        $this->assertFalse(FrontendCreationStatus::allowsPanelPublishing(FrontendCreationStatus::Draft));
        $this->assertFalse(FrontendCreationStatus::allowsPanelPublishing(FrontendCreationStatus::PendingReview));
        $this->assertFalse(FrontendCreationStatus::allowsPanelPublishing(FrontendCreationStatus::Rejected));
    }
}
