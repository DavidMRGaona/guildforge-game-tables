<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\CampaignResource;

final class ListCampaigns extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_campaign')),
            $this->knowledgeGuideAction('game-tables.panel.overview'),
        ]);
    }
}
