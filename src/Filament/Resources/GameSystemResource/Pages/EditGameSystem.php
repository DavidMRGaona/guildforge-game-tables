<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameSystemResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

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
            // A system in use would take its tables and campaigns with it: the dialog explains it
            // and offers to deactivate the system instead
            DeleteAction::make()
                ->modalDescription(fn (GameSystemModel $record): ?string => GameSystemResource::usageDescription($record))
                ->modalSubmitAction(fn (GameSystemModel $record): ?bool => $record->isInUse() ? false : null)
                ->extraModalFooterActions(fn (DeleteAction $action, GameSystemModel $record): array => $record->isInUse() && $record->is_active ? [
                    $action->makeModalSubmitAction('deactivate', arguments: ['deactivate' => true])
                        ->label(__('game-tables::messages.game_system_deletion.deactivate'))
                        ->color('warning'),
                ] : [])
                ->before(function (DeleteAction $action, GameSystemModel $record, array $arguments): void {
                    if (($arguments['deactivate'] ?? false) === true) {
                        $record->update(['is_active' => false]);
                        $this->refreshFormData(['is_active']);

                        Notification::make()
                            ->title(__('game-tables::messages.game_system_deletion.deactivated'))
                            ->success()
                            ->send();

                        $action->cancel();
                    }

                    // Also when a table started using it after the dialog opened
                    if ($record->isInUse()) {
                        Notification::make()
                            ->title(__('game-tables::messages.game_system_deletion.not_deleted'))
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
