<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Media;

use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * The image of each table in the panel media library (core ≥ 3.53).
 */
final class GameTableMediaSource extends EloquentColumnMediaSource
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
        return (string) $record->getAttribute('title');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        if (! Gate::forUser($user)->allows('update', $record)) {
            return null;
        }

        return GameTableResource::getUrl('edit', ['record' => $record]);
    }
}
