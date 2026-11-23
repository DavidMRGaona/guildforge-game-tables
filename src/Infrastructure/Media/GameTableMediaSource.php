<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Media;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * The image of each table in the panel media library (core ≥ 3.53). Its preview shows only while the
 * table is published (core#830, core#837): until then, a draft, a proposal waiting for review or a
 * rejected one, the table is not public yet, so the library keeps its image out of sight and names it
 * with a neutral title. Publishing or unpublishing refreshes the usage (GameTablesServiceProvider).
 */
final class GameTableMediaSource extends EloquentColumnMediaSource implements ReleasesMediaThroughCoreInterface, ShowsMediaPreviews
{
    public const string TYPE = 'gametables.tables';

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('game-tables::messages.media.sources.tables');
    }

    public function folders(): array
    {
        return ['game-tables/'];
    }

    protected function modelClass(): string
    {
        return GameTableModel::class;
    }

    protected function column(): string
    {
        return 'image_public_id';
    }

    protected function role(): string
    {
        return __('game-tables::messages.media.roles.image');
    }

    protected function titleOf(Model $record): string
    {
        if ($this->isHidden($record)) {
            return __('game-tables::messages.media.unpublished_table');
        }

        return (string) $record->getAttribute('title');
    }

    /**
     * The page of an unpublished table does not open, so neither does its image in the library.
     */
    protected function isHidden(Model $record): bool
    {
        return ! $record instanceof GameTableModel || ! $record->isShownOnItsPage();
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        if (! Gate::forUser($user)->allows('update', $record)) {
            return null;
        }

        return GameTableResource::getUrl('edit', ['record' => $record]);
    }
}
