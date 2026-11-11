<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Media;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * The image of each campaign in the panel media library (core ≥ 3.53).
 */
final class CampaignMediaSource extends EloquentColumnMediaSource implements ReleasesMediaThroughCoreInterface
{
    public const string TYPE = 'gametables.campaigns';

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('game-tables::messages.media.sources.campaigns');
    }

    public function folders(): array
    {
        return ['campaigns/'];
    }

    protected function modelClass(): string
    {
        return CampaignModel::class;
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

        return CampaignResource::getUrl('edit', ['record' => $record]);
    }
}
