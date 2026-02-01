<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Tests\TestCase;

final class FrontendCreationStatusTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = FrontendCreationStatus::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(FrontendCreationStatus::Draft, $cases);
        $this->assertContains(FrontendCreationStatus::PendingReview, $cases);
        $this->assertContains(FrontendCreationStatus::Approved, $cases);
        $this->assertContains(FrontendCreationStatus::Rejected, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('draft', FrontendCreationStatus::Draft->value);
        $this->assertEquals('pending_review', FrontendCreationStatus::PendingReview->value);
        $this->assertEquals('approved', FrontendCreationStatus::Approved->value);
        $this->assertEquals('rejected', FrontendCreationStatus::Rejected->value);
    }

    public function test_it_returns_label(): void
    {
        $this->assertIsString(FrontendCreationStatus::Draft->label());
        $this->assertIsString(FrontendCreationStatus::PendingReview->label());
        $this->assertIsString(FrontendCreationStatus::Approved->label());
        $this->assertIsString(FrontendCreationStatus::Rejected->label());
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
        $this->assertIsString(FrontendCreationStatus::Draft->icon());
        $this->assertIsString(FrontendCreationStatus::PendingReview->icon());
        $this->assertIsString(FrontendCreationStatus::Approved->icon());
        $this->assertIsString(FrontendCreationStatus::Rejected->icon());
    }

    public function test_it_returns_options(): void
    {
        $options = FrontendCreationStatus::options();

        $this->assertIsArray($options);
        $this->assertCount(4, $options);
        $this->assertArrayHasKey('draft', $options);
        $this->assertArrayHasKey('pending_review', $options);
        $this->assertArrayHasKey('approved', $options);
        $this->assertArrayHasKey('rejected', $options);
    }

    public function test_it_returns_values(): void
    {
        $values = FrontendCreationStatus::values();

        $this->assertIsArray($values);
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
}
