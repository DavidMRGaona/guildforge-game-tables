<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\ContentWarningResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\ContentWarningResource;

final class ListContentWarnings extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = ContentWarningResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.model.content_warning.plural');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_content_warning')),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
