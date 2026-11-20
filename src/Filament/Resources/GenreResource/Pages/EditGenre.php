<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GenreResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\GameTables\Filament\Resources\GenreResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

final class EditGenre extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = GenreResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.edit_genre');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            // A genre in use would be lost by its tables and campaigns: the dialog says who uses it
            // and offers to deactivate it instead; an unused one needs both confirmations
            DeleteAction::make()
                ->modalDescription(static fn (GenreModel $record): ?string => GenreResource::usageDescription($record))
                ->schema(static fn (GenreModel $record): array => $record->isInUse() ? [] : [GenreResource::deletionConfirmation()])
                ->modalSubmitAction(static fn (GenreModel $record): ?bool => $record->isInUse() ? false : null)
                ->extraModalFooterActions(static fn (DeleteAction $action, GenreModel $record): array => $record->isInUse() && $record->is_active ? [
                    $action->makeModalSubmitAction('deactivate', arguments: ['deactivate' => true])
                        ->label(__('game-tables::messages.genre_deletion.deactivate'))
                        ->color('warning'),
                ] : [])
                ->before(function (DeleteAction $action, GenreModel $record, array $arguments): void {
                    if (($arguments['deactivate'] ?? false) === true) {
                        $record->update(['is_active' => false]);
                        $this->refreshFormData(['is_active']);

                        Notification::make()
                            ->title(__('game-tables::messages.genre_deletion.deactivated'))
                            ->success()
                            ->send();

                        $action->cancel();
                    }

                    // Also when a table took it after the dialog opened
                    if ($record->isInUse()) {
                        Notification::make()
                            ->title(__('game-tables::messages.genre_deletion.not_deleted'))
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }

    /**
     * The key never changes: tables and campaigns store it. The field is disabled and not sent,
     * and a forged one is dropped here.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['key'], $data['sort_order']);

        return $data;
    }
}
