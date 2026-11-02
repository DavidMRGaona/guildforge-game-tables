<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\PublisherResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\PublisherResource;

final class ListPublishers extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = PublisherResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('game-tables::messages.actions.create_publisher')),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
