<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers;

use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Grid;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.relation_managers.participants.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('participant_type')
                    ->label(__('game-tables::messages.fields.participant_type'))
                    ->options([
                        'user' => __('game-tables::messages.fields.participant_type_user'),
                        'external' => __('game-tables::messages.fields.participant_type_external'),
                    ])
                    ->default('user')
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Select $component, ?string $state, ?ParticipantModel $record): void {
                        if ($record !== null) {
                            $component->state($record->user_id !== null ? 'user' : 'external');
                        }
                    }),

                Select::make('user_id')
                    ->label(__('game-tables::messages.fields.user'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('participant_type') === 'user')
                    ->required(fn (Get $get): bool => $get('participant_type') === 'user'),

                Grid::make(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('game-tables::messages.fields.first_name'))
                            ->required(fn (Get $get): bool => $get('participant_type') === 'external'),
                        TextInput::make('last_name')
                            ->label(__('game-tables::messages.fields.last_name')),
                    ])
                    ->visible(fn (Get $get): bool => $get('participant_type') === 'external'),

                Grid::make(2)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('game-tables::messages.fields.email'))
                            ->email(),
                        TextInput::make('phone')
                            ->label(__('game-tables::messages.fields.phone'))
                            ->tel(),
                    ])
                    ->visible(fn (Get $get): bool => $get('participant_type') === 'external'),

                Select::make('role')
                    ->label(__('game-tables::messages.fields.participant_role'))
                    ->options(ParticipantRole::participantOptions())
                    ->default(ParticipantRole::Player->value)
                    ->required(),

                // No status field: it changes only through the row actions, which go through the
                // registration service and its lock; with the waiting list on they never go over
                // the seats (with it off, Confirm and Promote do not check them, as before)
                Textarea::make('notes')
                    ->label(__('game-tables::messages.fields.notes'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * The status row actions are for whoever may edit the table, the same as the page they are on.
     */
    private function canManageParticipants(): bool
    {
        return Gate::allows('update', $this->getOwnerRecord());
    }

    private static function notifySeatsFull(TableFullException $exception): void
    {
        Notification::make()
            ->title(__('game-tables::messages.errors.'.$exception->reason))
            ->danger()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('game-tables::messages.fields.name'))
                    ->getStateUsing(fn (ParticipantModel $record): string => $record->display_name)
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                TextColumn::make('role')
                    ->label(__('game-tables::messages.fields.participant_role'))
                    ->badge()
                    ->color(fn (ParticipantRole $state): string => $state->color())
                    ->formatStateUsing(fn (ParticipantRole $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('game-tables::messages.fields.participant_status'))
                    ->badge()
                    ->color(fn (ParticipantStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ParticipantStatus $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('waiting_list_position')
                    ->label(__('game-tables::messages.fields.waiting_list_position'))
                    // The place among those waiting for the same role: stored positions keep gaps
                    ->state(fn (ParticipantModel $record): ?int => $record->status === ParticipantStatus::WaitingList
                        ? app(ParticipantRepositoryInterface::class)->getWaitingListRank(new ParticipantId($record->id))
                        : null)
                    ->numeric()
                    ->visible(function (): bool {
                        /** @var GameTableModel $ownerRecord */
                        $ownerRecord = $this->getOwnerRecord();

                        return $ownerRecord->participants()->where('status', ParticipantStatus::WaitingList->value)->exists();
                    }),

                TextColumn::make('confirmed_at')
                    ->label(__('game-tables::messages.fields.confirmed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('game-tables::messages.fields.registered_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('game-tables::messages.fields.participant_role'))
                    ->options(ParticipantRole::participantOptions()),
                SelectFilter::make('status')
                    ->label(__('game-tables::messages.fields.participant_status'))
                    ->options(ParticipantStatus::options()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('game-tables::messages.relation_managers.participants.add'))
                    ->using(function (array $data, RelationManager $livewire): Model {
                        $registrationService = app(RegistrationServiceInterface::class);
                        /** @var GameTableModel $ownerRecord */
                        $ownerRecord = $livewire->getOwnerRecord();
                        $gameTableId = $ownerRecord->id;
                        $participantType = $data['participant_type'] ?? 'user';

                        // Determine if this is a user or guest registration
                        if ($participantType === 'user' && isset($data['user_id'])) {
                            $dto = new RegisterParticipantDTO(
                                gameTableId: $gameTableId,
                                userId: $data['user_id'],
                                role: ParticipantRole::from($data['role']),
                                notes: $data['notes'] ?? null,
                            );
                            $result = $registrationService->registerByAdmin($dto);
                        } else {
                            $dto = new RegisterParticipantDTO(
                                gameTableId: $gameTableId,
                                userId: null,
                                role: ParticipantRole::from($data['role']),
                                notes: $data['notes'] ?? null,
                                firstName: $data['first_name'] ?? null,
                                lastName: $data['last_name'] ?? null,
                                email: $data['email'] ?? null,
                                phone: $data['phone'] ?? null,
                            );
                            $result = $registrationService->registerGuestByAdmin($dto);
                        }

                        // Fetch and return the model for Filament
                        return ParticipantModel::findOrFail($result->id);
                    }),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label(__('game-tables::messages.relation_managers.participants.confirm'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ParticipantModel $record): bool => $record->status === ParticipantStatus::Pending)
                    ->authorize(fn (): bool => $this->canManageParticipants())
                    ->action(function (ParticipantModel $record): void {
                        $registrationService = app(RegistrationServiceInterface::class);

                        try {
                            $registrationService->confirm($record->id);
                        } catch (TableFullException $exception) {
                            // With the waiting list on, a confirmation never exceeds the player or spectator seats
                            self::notifySeatsFull($exception);
                        }
                    }),

                Action::make('reject')
                    ->label(__('game-tables::messages.relation_managers.participants.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ParticipantModel $record): bool => $record->status === ParticipantStatus::Pending)
                    ->authorize(fn (): bool => $this->canManageParticipants())
                    ->requiresConfirmation()
                    ->action(function (ParticipantModel $record): void {
                        $registrationService = app(RegistrationServiceInterface::class);
                        $registrationService->reject($record->id);
                    }),

                Action::make('promote')
                    ->label(__('game-tables::messages.relation_managers.participants.promote'))
                    ->icon('heroicon-o-arrow-up')
                    ->color('info')
                    ->visible(fn (ParticipantModel $record): bool => $record->status === ParticipantStatus::WaitingList)
                    ->authorize(fn (): bool => $this->canManageParticipants())
                    ->action(function (ParticipantModel $record): void {
                        $registrationService = app(RegistrationServiceInterface::class);

                        try {
                            $registrationService->promoteParticipant($record->id);
                        } catch (TableFullException $exception) {
                            // With the waiting list on, a promotion never exceeds the player or spectator seats
                            self::notifySeatsFull($exception);
                        }
                    }),

                // The participant's own cancellation (lock, freed seat, promotion), with its own
                // email to the participant and none to the directors
                Action::make('cancelRegistration')
                    ->label(__('game-tables::messages.relation_managers.participants.cancel_registration'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ParticipantModel $record): bool => $record->status->canBeCancelled())
                    ->authorize(fn (): bool => $this->canManageParticipants())
                    ->requiresConfirmation()
                    ->modalDescription(__('game-tables::messages.relation_managers.participants.cancel_registration_description'))
                    ->action(function (ParticipantModel $record): void {
                        try {
                            app(RegistrationServiceInterface::class)->cancelByAdmin($record->id);
                        } catch (CannotCancelException) {
                            // Cancelled, rejected or marked meanwhile
                            Notification::make()
                                ->title(__('game-tables::messages.errors.cannot_cancel'))
                                ->danger()
                                ->send();
                        }
                    }),

                // Only someone with a confirmed seat, once the table has started; no emails, no promotion
                Action::make('markNoShow')
                    ->label(__('game-tables::messages.relation_managers.participants.no_show'))
                    ->icon('heroicon-o-user-minus')
                    ->color('gray')
                    ->visible(function (ParticipantModel $record): bool {
                        /** @var GameTableModel $ownerRecord */
                        $ownerRecord = $this->getOwnerRecord();

                        return $record->status === ParticipantStatus::Confirmed
                            && ! $ownerRecord->starts_at->isFuture();
                    })
                    ->authorize(fn (): bool => $this->canManageParticipants())
                    ->requiresConfirmation()
                    ->modalDescription(__('game-tables::messages.relation_managers.participants.no_show_description'))
                    ->action(function (ParticipantModel $record): void {
                        try {
                            app(RegistrationServiceInterface::class)->markAsNoShow($record->id);
                        } catch (InvalidStatusTransitionException) {
                            Notification::make()
                                ->title(__('game-tables::messages.errors.cannot_mark_no_show'))
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // One guest per email and table, whatever the case it is typed in (as on registration)
                        if (isset($data['email']) && is_string($data['email'])) {
                            $data['email'] = mb_strtolower(trim($data['email']));
                        }

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
