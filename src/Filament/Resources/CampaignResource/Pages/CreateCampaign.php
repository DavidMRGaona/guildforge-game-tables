<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * @property CampaignModel $record
 */
final class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $gameMastersData = [];

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.create_campaign');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        // Extract game masters data to process after creation
        $this->gameMastersData = $data['gameMasters'] ?? [];
        unset($data['gameMasters']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->gameMastersData === []) {
            return;
        }

        /** @var GameMasterServiceInterface $gameMasterService */
        $gameMasterService = app(GameMasterServiceInterface::class);
        $gameMasterService->syncCampaignGameMasters($this->record->id, $this->gameMastersData);
    }
}
