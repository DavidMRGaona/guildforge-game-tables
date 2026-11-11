<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Support\PublicationLock;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * @property CampaignModel $record
 */
final class EditCampaign extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = CampaignResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    public function getTitle(): string
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
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A web-created campaign is published by approving it, even against a forged payload
        return PublicationLock::guardFormData($this->getRecord(), $data);
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
        $gameMasterService->syncCampaignGameMasters((string) $record->getKey(), $gameMastersData);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            Action::make('approveModeration')
                ->authorize(fn (): bool => CampaignResource::canModerate())
                ->label(__('game-tables::messages.moderation.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('game-tables::messages.moderation.campaign_approve_confirmation'))
                ->modalDescription(__('game-tables::messages.moderation.campaign_approve_description'))
                ->visible(fn (): bool => $this->record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                ->action(function (): void {
                    try {
                        app(FrontendCreationServiceInterface::class)->approveCampaign($this->record->id);
                    } catch (NotEditableException) {
                        // Another moderator got there first
                        $this->warnStatusAlreadyChanged();

                        return;
                    }

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.campaign_approved'))
                        ->success()
                        ->send();

                    $this->showModerationOutcome();
                }),

            Action::make('rejectModeration')
                ->authorize(fn (): bool => CampaignResource::canModerate())
                ->label(__('game-tables::messages.moderation.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading(__('game-tables::messages.moderation.campaign_reject_confirmation'))
                ->modalDescription(__('game-tables::messages.moderation.campaign_reject_description'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('game-tables::messages.moderation.rejection_reason'))
                        ->placeholder(__('game-tables::messages.moderation.campaign_rejection_reason_placeholder'))
                        ->required(),
                ])
                ->visible(fn (): bool => $this->record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                ->action(function (array $data): void {
                    try {
                        app(FrontendCreationServiceInterface::class)->rejectCampaign($this->record->id, (string) $data['reason']);
                    } catch (NotEditableException) {
                        $this->warnStatusAlreadyChanged();

                        return;
                    }

                    Notification::make()
                        ->title(__('game-tables::messages.notifications.campaign_rejected'))
                        ->success()
                        ->send();

                    $this->showModerationOutcome();
                }),

            DeleteAction::make(),
            $this->knowledgeGuideAction('game-tables.panel.campaign-roster'),
        ]);
    }

    private function warnStatusAlreadyChanged(): void
    {
        Notification::make()
            ->title(__('game-tables::messages.notifications.campaign_status_already_changed'))
            ->warning()
            ->send();

        // Show what the campaign is now, so the moderation buttons follow it
        $this->showModerationOutcome();
    }

    /**
     * The service saved through its own model instance: reload the record and the fields it changed.
     */
    private function showModerationOutcome(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['frontend_creation_status', 'is_published']);
    }
}
