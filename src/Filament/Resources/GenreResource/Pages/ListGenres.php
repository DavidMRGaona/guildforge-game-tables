<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GenreResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\GenreResource;

final class ListGenres extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = GenreResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.model.genre.plural');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_genre')),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
