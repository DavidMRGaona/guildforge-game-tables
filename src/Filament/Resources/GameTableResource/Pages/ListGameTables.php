<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\GameTableResource;

final class ListGameTables extends ListRecords
{
    protected static string $resource = GameTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_table')),
        ];
    }
}
