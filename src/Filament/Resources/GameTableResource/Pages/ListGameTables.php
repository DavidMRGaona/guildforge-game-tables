<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class ListGameTables extends ListRecords
{
    protected static string $resource = GameTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_table')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('game-tables::messages.table_tabs.all')),
            'pending_moderation' => Tab::make(__('game-tables::messages.table_tabs.pending_moderation'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('frontend_creation_status', FrontendCreationStatus::PendingReview->value))
                ->badge($this->getPendingModerationCount())
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),
        ];
    }

    private function getPendingModerationCount(): int
    {
        return GameTableModel::query()
            ->where('frontend_creation_status', FrontendCreationStatus::PendingReview->value)
            ->count();
    }
}
