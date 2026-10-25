<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * The panel never publishes a web-created table or campaign its author has not sent to review,
 * nor one awaiting review: approving it is the only way. Items created in the panel and approved
 * ones publish freely (and may be unpublished and republished).
 */
final class PublicationLock
{
    /**
     * Whether «Publicada» (and the table's «Publicar» action) stay locked for the record. An item
     * already published is never locked, so it can still be unpublished.
     */
    public static function isLocked(?Model $record): bool
    {
        if (! $record instanceof GameTableModel && ! $record instanceof CampaignModel) {
            return false;
        }

        if ($record->is_published) {
            return false;
        }

        return ! FrontendCreationStatus::allowsPanelPublishing($record->frontend_creation_status);
    }

    /**
     * The hint shown under the locked toggle, or null when it is not locked.
     */
    public static function hint(?Model $record): ?string
    {
        if (!$record instanceof GameTableModel && !$record instanceof CampaignModel || ! self::isLocked($record)) {
            return null;
        }

        if ($record->frontend_creation_status === FrontendCreationStatus::PendingReview) {
            return $record instanceof CampaignModel
                ? (string) __('game-tables::messages.fields.is_published_campaign_pending_review')
                : (string) __('game-tables::messages.fields.is_published_pending_review');
        }

        return (string) __('game-tables::messages.fields.is_published_not_submitted');
    }

    /**
     * Server-side guard for the panel save: a disabled toggle is not saved, but a forged Livewire
     * payload must not publish a locked item either.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function guardFormData(Model $record, array $data): array
    {
        if (self::isLocked($record)) {
            $data['is_published'] = false;
        }

        return $data;
    }
}
