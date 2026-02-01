<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum FrontendCreationStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
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
