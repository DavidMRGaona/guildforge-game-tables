<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameSystemResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\GameSystemResource;

final class ListGameSystems extends ListRecords
{
    protected static string $resource = GameSystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_game_system')),
        ];
    }
}
