<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\GameTables\Filament\Resources\CampaignResource;

final class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_campaign')),
        ];
    }
}
