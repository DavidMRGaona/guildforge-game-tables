<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class EditGameTable extends EditRecord
{
    protected static string $resource = GameTableResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.edit_table');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var GameTableModel $record */
        $record = $this->record;

        // Build the gameMasters array for the form
        $gameMastersData = [];

        // Get inherited GMs from campaign (if linked)
        if ($record->campaign_id !== null && $record->campaign !== null) {
            $excludedIds = $record->excludedGameMasters()
                ->pluck('gametables_game_masters.id')
                ->toArray();

            foreach ($record->campaign->gameMasters as $gm) {
                $isExcluded = in_array($gm->id, $excludedIds, true);
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
                    'is_inherited' => true,
                    'excluded' => $isExcluded,
                ];
            }
        }

        // Get local GMs for this table
        foreach ($record->localGameMasters as $gm) {
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
                'is_inherited' => false,
                'excluded' => false,
            ];
        }

        $data['gameMasters'] = $gameMastersData;

        return $data;
    }

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
        $gameMasterService->syncTableGameMasters($record->id, $gameMastersData);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveModeration')
                ->label(__('game-tables::messages.moderation.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.moderation.approve_confirmation'))
                ->modalDescription(__('game-tables::messages.moderation.approve_description'))
                ->visible(fn (): bool => $this->record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                ->action(function (): void {
                    /** @var FrontendCreationServiceInterface $service */
                    $service = app(FrontendCreationServiceInterface::class);
                    $service->approveFrontendCreation($this->record->id);

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.table_approved'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['frontend_creation_status', 'is_published', 'status', 'published_at']);
                }),

            Action::make('rejectModeration')
                ->label(__('game-tables::messages.moderation.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.moderation.reject_confirmation'))
                ->modalDescription(__('game-tables::messages.moderation.reject_description'))
                ->form([
                    Textarea::make('reason')
                        ->label(__('game-tables::messages.moderation.rejection_reason'))
                        ->placeholder(__('game-tables::messages.moderation.rejection_reason_placeholder'))
                        ->required(),
                ])
                ->visible(fn (): bool => $this->record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                ->action(function (array $data): void {
                    /** @var FrontendCreationServiceInterface $service */
                    $service = app(FrontendCreationServiceInterface::class);
                    $service->rejectFrontendCreation($this->record->id, $data['reason']);

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.table_rejected'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['frontend_creation_status']);
                }),

            Action::make('publish')
                ->label(__('game-tables::messages.actions.publish'))
                ->icon('heroicon-o-eye')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->is_published)
                ->action(function (): void {
                    $this->record->update([
                        'is_published' => true,
                        'status' => TableStatus::Scheduled->value,
                        'published_at' => now(),
                    ]);
                    $this->refreshFormData(['is_published', 'status', 'published_at']);
                }),

            Action::make('unpublish')
                ->label(__('game-tables::messages.actions.unpublish'))
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->visible(fn (): bool => $this->record->is_published && ! $this->record->status->isFinal())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'is_published' => false,
                        'status' => TableStatus::Draft->value,
                        'published_at' => null,
                    ]);
                    $this->refreshFormData(['is_published', 'status', 'published_at']);
                }),

            Action::make('start')
                ->label(__('game-tables::messages.actions.start'))
                ->icon('heroicon-o-play')
                ->color('primary')
                ->visible(fn (): bool => in_array($this->record->status, [TableStatus::Scheduled, TableStatus::Full], true))
                ->action(function (): void {
                    $this->record->update(['status' => TableStatus::InProgress->value]);
                    $this->refreshFormData(['status']);
                }),

            Action::make('complete')
                ->label(__('game-tables::messages.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === TableStatus::InProgress)
                ->action(function (): void {
                    $this->record->update(['status' => TableStatus::Completed->value]);
                    $this->refreshFormData(['status']);
                }),

            Action::make('cancelTable')
                ->label(__('game-tables::messages.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.actions.cancel_confirmation_title'))
                ->modalDescription(__('game-tables::messages.actions.cancel_confirmation_description'))
                ->modalSubmitActionLabel(__('game-tables::messages.actions.cancel_confirm'))
                ->visible(fn (): bool => ! $this->record->status->isFinal())
                ->action(function (): void {
                    $this->record->update(['status' => TableStatus::Cancelled->value]);

                    GameTableCancelled::dispatch(
                        $this->record->id,
                        $this->record->title,
                        new \DateTimeImmutable($this->record->starts_at->toDateTimeString()),
                    );

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.table_cancelled'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            DeleteAction::make(),
        ];
    }
}
