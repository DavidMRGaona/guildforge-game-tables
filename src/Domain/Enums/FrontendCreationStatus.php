<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

/**
 * Review of a table or campaign created from the web: the author prepares a draft and sends it
 * to review; approving it approves and publishes it, rejecting it returns it to a draft that keeps
 * the reason. Items created in the panel have no status.
 */
enum FrontendCreationStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    /**
     * Legacy: no longer written (rejecting returns the item to Draft with the reason). Kept, and
     * treated as a draft, for rows rejected before 2.10.0 until they are converted.
     */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('game-tables::messages.enums.frontend_creation_status.draft'),
            self::PendingReview => __('game-tables::messages.enums.frontend_creation_status.pending_review'),
            self::Approved => __('game-tables::messages.enums.frontend_creation_status.approved'),
            self::Rejected => __('game-tables::messages.enums.frontend_creation_status.rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil',
            self::PendingReview => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Draft, self::PendingReview], true);
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function canSubmitForReview(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /**
     * Whether the author, without the admin permission, may edit, submit for review or delete
     * a table or campaign: only an unpublished draft (or legacy rejected item), as "Mis mesas" and
     * "Mis campañas" offer. A published item (even one published from the panel while still a
     * draft) and an item not created from the web (no status) are out of the author's hands.
     * The single rule behind the entities, the policies and the response DTOs.
     */
    public static function allowsAuthorChanges(?self $status, bool $isPublished): bool
    {
        return ! $isPublished && $status !== null && $status->canEdit();
    }

    /**
     * Whether the panel may publish the item with its «Publicada» toggle or «Publicar» action:
     * only items created in the panel (no status) and approved ones, which it may also unpublish
     * and republish. A web-created item the author is preparing or that awaits review is
     * published only by approving it.
     */
    public static function allowsPanelPublishing(?self $status): bool
    {
        return $status === null || $status === self::Approved;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case): string => $case->label(), self::cases()),
        );
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
