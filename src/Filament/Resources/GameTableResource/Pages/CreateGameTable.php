<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Filament\Resources\GameTableResource;

final class CreateGameTable extends CreateRecord
{
    protected static string $resource = GameTableResource::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $gameMastersData = [];

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.create_table');
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
        if (empty($this->gameMastersData)) {
            return;
        }

        /** @var GameMasterServiceInterface $gameMasterService */
        $gameMasterService = app(GameMasterServiceInterface::class);
        $gameMasterService->syncTableGameMasters($this->record->id, $this->gameMastersData);
    }
}
