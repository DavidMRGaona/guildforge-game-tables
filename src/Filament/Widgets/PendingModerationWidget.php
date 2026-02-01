<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Widgets;

use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class PendingModerationWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('game-tables::messages.widgets.pending_moderation.title'))
            ->query(
                GameTableModel::query()
                    ->where('frontend_creation_status', FrontendCreationStatus::PendingReview->value)
                    ->with(['gameSystem', 'creator'])
                    ->orderBy('created_at', 'asc')
            )
            ->columns([
                TextColumn::make('title')
                    ->label(__('game-tables::messages.fields.title'))
                    ->searchable()
                    ->limit(40)
                    ->url(fn (GameTableModel $record): string => GameTableResource::getUrl('edit', ['record' => $record->id])),

                TextColumn::make('gameSystem.name')
                    ->label(__('game-tables::messages.fields.game_system'))
                    ->limit(20),

                TextColumn::make('creator.name')
                    ->label(__('game-tables::messages.fields.user'))
                    ->limit(20),

                TextColumn::make('created_at')
                    ->label(__('game-tables::messages.fields.registered_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label(__('game-tables::messages.actions.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (GameTableModel $record): string => GameTableResource::getUrl('edit', ['record' => $record->id])),
            ])
            ->emptyStateHeading(__('game-tables::messages.widgets.pending_moderation.no_pending'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
