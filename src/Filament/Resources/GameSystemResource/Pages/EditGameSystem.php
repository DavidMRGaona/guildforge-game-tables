<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameSystemResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\GameTables\Filament\Resources\GameSystemResource;

final class EditGameSystem extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = GameSystemResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.edit_game_system');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            DeleteAction::make(),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
