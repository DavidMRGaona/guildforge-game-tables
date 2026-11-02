<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\PublisherResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Modules\GameTables\Filament\Resources\PublisherResource;

final class CreatePublisher extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = PublisherResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.create_publisher');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
