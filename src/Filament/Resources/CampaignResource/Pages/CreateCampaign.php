<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Modules\GameTables\Filament\Resources\CampaignResource;

final class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.create_campaign');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
