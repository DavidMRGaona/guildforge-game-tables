<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.edit_campaign');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var CampaignModel $record */
        $record = $this->record;

        // Build the gameMasters array for the form
        $gameMastersData = [];

        foreach ($record->gameMasters as $gm) {
            $gameMastersData[] = [
                'id' => $gm->id,
                'gm_type' => $gm->user_id !== null ? 'user' : 'external',
                'user_id' => $gm->user_id,
                'first_name' => $gm->first_name,
                'last_name' => $gm->last_name,
                'email' => $gm->email,
                'phone' => $gm->phone,
                'role' => $gm->role->value,
                'custom_title' => $gm->custom_title,
                'notify_by_email' => $gm->notify_by_email,
                'is_name_public' => $gm->is_name_public,
                'notes' => $gm->notes,
            ];
        }

        $data['gameMasters'] = $gameMastersData;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract game masters data before saving
        $gameMastersData = $data['gameMasters'] ?? [];
        unset($data['gameMasters']);

        // Update the record
        $record->update($data);

        // Sync game masters using the service
        /** @var GameMasterServiceInterface $gameMasterService */
        $gameMasterService = app(GameMasterServiceInterface::class);
        $gameMasterService->syncCampaignGameMasters($record->id, $gameMastersData);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
