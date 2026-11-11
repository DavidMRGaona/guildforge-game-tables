<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Support\CampaignSessionType;
use Modules\GameTables\Filament\Support\DelegatedDirectors;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * @property GameTableModel $record
 */
final class CreateGameTable extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = GameTableResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $gameMastersData = [];

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.create_table');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Refused before anything is written
        DelegatedDirectors::ensureLinked($data);
        $data = DelegatedDirectors::dropForCampaign($data);
        $data = CampaignSessionType::guardFormData($data);

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
        $gameMasterService->syncTableGameMasters($this->record->id, $this->gameMastersData);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction('game-tables.panel.create-table'),
        ]);
    }
}
