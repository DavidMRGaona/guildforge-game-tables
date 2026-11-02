<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameSystemResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\GameSystemResource;

final class ListGameSystems extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = GameSystemResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_game_system')),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
