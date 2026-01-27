<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\GameTables\Filament\Resources\CampaignResource;

final class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.edit_campaign');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
