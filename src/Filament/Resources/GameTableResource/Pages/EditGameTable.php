<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Support\DelegatedDirectors;
use Modules\GameTables\Filament\Support\PublicationLock;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class EditGameTable extends EditRecord
{
    protected static string $resource = GameTableResource::class;

    /**
     * The status the form shows, as loaded: the automatic update (or another user) may move the
     * table while the form is open, and saving must not write the old status back.
     */
    #[Locked]
    public ?string $loadedStatus = null;

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

    protected function afterFill(): void
    {
        $this->loadedStatus = $this->getRecord()->status->value;
    }

    /**
     * @param  array<string>  $statePaths
     */
    public function refreshFormData(array $statePaths): void
    {
        parent::refreshFormData($statePaths);

        // The actions show the status they set: the form is current again
        if (in_array('status', $statePaths, true)) {
            $this->loadedStatus = $this->getRecord()->status->value;
        }
    }

    /**
     * A status that moved since the form was filled wins over the one on the form, before the
     * validation (an old «Borrador» is no longer an option). Saving the current status leaves the
     * column clean, so a move that lands right after this check is not overwritten either.
     */
    protected function beforeValidate(): void
    {
        $current = $this->getRecord()->status->value;

        if ($this->loadedStatus === null || $current === $this->loadedStatus) {
            return;
        }

        if (($this->data['status'] ?? null) !== $current) {
            $this->warnStatusChangeRefused();
        }

        $this->data['status'] = $current;
        $this->loadedStatus = $current;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Refused before the table or its directors are written
        DelegatedDirectors::ensureLinked($data);
        $data = DelegatedDirectors::dropForCampaign($data);

        // A web-created table is published by approving it, even against a forged payload
        return PublicationLock::guardFormData($this->getRecord(), $data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract game masters data before saving
        $gameMastersData = $data['gameMasters'] ?? [];
        unset($data['gameMasters']);

        // The status goes through the service: the transitions apply and it is saved only while the
        // table still has the status read, so a move landing meanwhile is not overwritten
        $status = isset($data['status']) ? TableStatus::tryFrom((string) $data['status']) : null;
        unset($data['status']);

        // Update the record
        $record->update($data);

        if ($status !== null && $status !== $record->getAttribute('status')) {
            try {
                app(GameTableServiceInterface::class)->changeStatus((string) $record->getKey(), $status);
            } catch (InvalidStatusTransitionException) {
                $this->warnStatusChangeRefused();
            }

            // The service saved through its own model instance
            $record->refresh();
        }

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
                ->authorize(fn (): bool => GameTableResource::canModerate())
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

                    try {
                        $service->approveFrontendCreation($this->record->id);
                    } catch (NotEditableException) {
                        // Another admin got there first
                        $this->warnStatusAlreadyChanged();

                        return;
                    } catch (SessionBelowRosterException $e) {
                        $this->refuseBelowRoster($e);

                        return;
                    }

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.table_approved'))
                        ->success()
                        ->send();

                    // The service saved through its own model instance
                    $this->getRecord()->refresh();
                    $this->refreshFormData(['frontend_creation_status', 'is_published', 'status', 'published_at']);
                }),

            Action::make('rejectModeration')
                ->authorize(fn (): bool => GameTableResource::canModerate())
                ->label(__('game-tables::messages.moderation.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.moderation.reject_confirmation'))
                ->modalDescription(__('game-tables::messages.moderation.reject_description'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('game-tables::messages.moderation.rejection_reason'))
                        ->placeholder(__('game-tables::messages.moderation.rejection_reason_placeholder'))
                        ->required(),
                ])
                ->visible(fn (): bool => $this->record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                ->action(function (array $data): void {
                    /** @var FrontendCreationServiceInterface $service */
                    $service = app(FrontendCreationServiceInterface::class);

                    try {
                        $service->rejectFrontendCreation($this->record->id, $data['reason']);
                    } catch (NotEditableException) {
                        $this->warnStatusAlreadyChanged();

                        return;
                    }

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.table_rejected'))
                        ->success()
                        ->send();

                    // The service saved through its own model instance
                    $this->getRecord()->refresh();
                    $this->refreshFormData(['frontend_creation_status', 'is_published', 'status', 'published_at']);
                }),

            Action::make('publish')
                ->label(__('game-tables::messages.actions.publish'))
                ->icon('heroicon-o-eye')
                ->color('success')
                // A web-created table the author has not sent, or one awaiting review, is published by approving it
                ->visible(fn (): bool => ! $this->record->is_published && ! PublicationLock::isLocked($this->getRecord()))
                ->action(function (): void {
                    /** @var GameTableServiceInterface $service */
                    $service = app(GameTableServiceInterface::class);

                    try {
                        // Through the service: it guards the campaign roster and announces GameTablePublished
                        $service->publish((string) $this->getRecord()->getKey());
                    } catch (SessionBelowRosterException $e) {
                        $this->refuseBelowRoster($e);

                        return;
                    }

                    // The service saved through its own model instance
                    $this->getRecord()->refresh();
                    $this->refreshFormData(['is_published', 'status', 'published_at']);
                }),

            Action::make('unpublish')
                ->label(__('game-tables::messages.actions.unpublish'))
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                // A table that started is cancelled, not unpublished: as a draft it could never be completed
                ->visible(fn (): bool => $this->record->is_published && $this->record->status->allowsUnpublishing())
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var GameTableServiceInterface $service */
                    $service = app(GameTableServiceInterface::class);

                    try {
                        $service->unpublish((string) $this->getRecord()->getKey());
                    } catch (InvalidStatusTransitionException) {
                        $this->warnStatusAlreadyChanged();

                        return;
                    }

                    // The service saved through its own model instance
                    $this->getRecord()->refresh();
                    $this->refreshFormData(['is_published', 'status', 'published_at']);
                }),

            // Starting, completing, cancelling and deleting cannot be undone: each asks twice
            Action::make('start')
                ->label(__('game-tables::messages.actions.start'))
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.actions.start_confirmation_title'))
                ->modalDescription(__('game-tables::messages.actions.start_confirmation_description'))
                ->modalSubmitActionLabel(__('game-tables::messages.actions.start_confirm'))
                ->schema([self::secondConfirmation('start')])
                ->visible(fn (): bool => in_array($this->record->status, [TableStatus::Scheduled, TableStatus::Full], true))
                ->action(function (): void {
                    $this->moveTable(TableStatus::InProgress);
                }),

            Action::make('complete')
                ->label(__('game-tables::messages.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.actions.complete_confirmation_title'))
                ->modalDescription(__('game-tables::messages.actions.complete_confirmation_description'))
                ->modalSubmitActionLabel(__('game-tables::messages.actions.complete_confirm'))
                ->schema([self::secondConfirmation('complete')])
                ->visible(fn (): bool => $this->record->status === TableStatus::InProgress)
                ->action(function (): void {
                    $this->moveTable(TableStatus::Completed);
                }),

            Action::make('cancelTable')
                ->label(__('game-tables::messages.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.actions.cancel_confirmation_title'))
                ->modalDescription(__('game-tables::messages.actions.cancel_confirmation_description'))
                ->modalSubmitActionLabel(__('game-tables::messages.actions.cancel_confirm'))
                ->schema([self::secondConfirmation('cancel')])
                ->visible(fn (): bool => ! $this->record->status->isFinal())
                ->action(function (): void {
                    try {
                        // Through the service: it guards the transition and announces
                        // GameTableCancelled, which cancels and notifies the participants
                        app(GameTableServiceInterface::class)->cancel((string) $this->getRecord()->getKey());
                    } catch (InvalidStatusTransitionException) {
                        $this->warnStatusChangeRefused();
                        $this->getRecord()->refresh();
                        $this->refreshFormData(['status']);

                        return;
                    }

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.table_cancelled'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            DeleteAction::make()
                ->schema([self::secondConfirmation('delete')]),
        ];
    }

    /**
     * The box to tick, on top of the confirmation modal, before an action that cannot be undone.
     */
    private static function secondConfirmation(string $action): Checkbox
    {
        return Checkbox::make('confirmation')
            ->label(__("game-tables::messages.actions.{$action}_confirmation_check"))
            ->accepted()
            ->required();
    }

    private function refuseBelowRoster(SessionBelowRosterException $e): void
    {
        Notification::make()
            ->title($e->getMessage())
            ->danger()
            ->send();
    }

    private function warnStatusChangeRefused(): void
    {
        Notification::make()
            ->title(__('game-tables::messages.notifications.status_already_changed'))
            ->warning()
            ->send();
    }

    private function warnStatusAlreadyChanged(): void
    {
        $this->warnStatusChangeRefused();

        // Show what the table is now, so the moderation buttons follow it
        $this->getRecord()->refresh();
        $this->refreshFormData(['frontend_creation_status', 'is_published', 'status', 'published_at']);
    }

    /**
     * Starts or completes the table through the service, as the automatic update does, so both
     * give the same result (completing announces GameTableCompleted).
     */
    private function moveTable(TableStatus $to): void
    {
        /** @var GameTableServiceInterface $service */
        $service = app(GameTableServiceInterface::class);
        $record = $this->getRecord();
        $id = (string) $record->getKey();

        try {
            if ($to === TableStatus::InProgress) {
                $service->start($id);
            } else {
                $service->complete($id);
            }
        } catch (InvalidStatusTransitionException) {
            // The automatic update or another user changed it first
            $this->warnStatusChangeRefused();
        }

        // The service saved through its own model instance
        $record->refresh();
        $this->refreshFormData(['status']);
    }
}
