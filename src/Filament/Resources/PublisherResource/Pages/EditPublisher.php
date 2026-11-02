<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\PublisherResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\GameTables\Filament\Resources\PublisherResource;

final class EditPublisher extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = PublisherResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.edit_publisher');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            DeleteAction::make(),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
