<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\PublisherResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\PublisherResource;

final class ListPublishers extends ListRecords
{
    protected static string $resource = PublisherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('game-tables::messages.actions.create_publisher')),
        ];
    }
}
