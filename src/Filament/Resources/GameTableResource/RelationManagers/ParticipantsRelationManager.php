<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotPendingException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Throwable;

final class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    /**
     * The places among those waiting, computed once per request for the whole table.
     *
     * @var array<string, int>|null
     */
    private ?array $waitingListPlaces = null;

    /**
     * The rows marked «Posible duplicado», computed once per request for the whole table.
     *
     * @var array<string, true>|null
     */
    private ?array $possibleDuplicates = null;

    private const string FLAG_EMAIL_UNCONFIRMED = 'email_unconfirmed';

    private const string FLAG_POSSIBLE_DUPLICATE = 'possible_duplicate';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.relation_managers.participants.title');
    }

    /**
     * Livewire hook: an action of this request may have moved the list, so each render counts again.
     */
    public function rendering(): void
    {
        $this->waitingListPlaces = null;
        $this->possibleDuplicates = null;
    }

    /**
     * @return array<string, int>
     */
    private function waitingListPlaces(): array
    {
        /** @var GameTableModel $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $this->waitingListPlaces ??= app(ParticipantRepositoryInterface::class)
            ->getWaitingListRanks([new GameTableId($ownerRecord->id)]);
    }

    /**
     * @return array<string, true>
     */
    private function possibleDuplicates(): array
    {
        /** @var GameTableModel $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $this->possibleDuplicates ??= array_fill_keys(
            app(ParticipantRepositoryInterface::class)->possibleDuplicateIds(
                new GameTableId($ownerRecord->id),
                app(GameTableSettingsReader::class)->duplicateSignalWindowHours(),
            ),
            true,
        );
    }

    /**
     * A guest of the public form who has not followed the emailed link yet (users never confirm an
     * email here, whatever the column holds).
     */
    private function isAwaitingEmailConfirmation(ParticipantModel $record): bool
    {
        return $record->user_id === null
            && $record->email_confirmed_at === null
            && $record->status->isActive();
    }

    /**
     * @return list<string>
     */
    private function guestFlags(ParticipantModel $record): array
    {
        $flags = [];

        if ($this->isAwaitingEmailConfirmation($record)) {
            $flags[] = self::FLAG_EMAIL_UNCONFIRMED;
        }

        if (isset($this->possibleDuplicates()[$record->id])) {
            $flags[] = self::FLAG_POSSIBLE_DUPLICATE;
        }

        return $flags;
    }

    private function guestFlagTooltip(string $flag, ParticipantModel $record): ?string
    {
        $key = 'game-tables::messages.relation_managers.participants.flags.';

        if ($flag === self::FLAG_POSSIBLE_DUPLICATE) {
            return __($key.'possible_duplicate_tooltip', [
                'hours' => app(GameTableSettingsReader::class)->duplicateSignalWindowHours(),
            ]);
        }

        // Only hold mode has a deadline
        return $record->confirmation_expires_at !== null
            ? __($key.'expires_at', ['date' => $record->confirmation_expires_at->format('d/m/Y H:i')])
            : null;
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
                    // Who the registration belongs to changes only by cancelling it and adding again
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->afterStateHydrated(function (Select $component, ?string $state, ?ParticipantModel $record): void {
                        if ($record instanceof ParticipantModel) {
                            $component->state($record->user_id !== null ? 'user' : 'external');
                        }
                    }),

                Select::make('user_id')
                    ->label(__('game-tables::messages.fields.user'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->visible(fn (Get $get, string $operation, ?ParticipantModel $record): bool => ! $this->isGuestForm($get, $operation, $record))
                    ->required(fn (Get $get, string $operation): bool => $operation !== 'edit' && $get('participant_type') === 'user'),

                Grid::make(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('game-tables::messages.fields.first_name'))
                            ->required(fn (Get $get, string $operation, ?ParticipantModel $record): bool => $this->isGuestForm($get, $operation, $record)),
                        TextInput::make('last_name')
                            ->label(__('game-tables::messages.fields.last_name')),
                    ])
                    ->visible(fn (Get $get, string $operation, ?ParticipantModel $record): bool => $this->isGuestForm($get, $operation, $record)),

                Grid::make(2)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('game-tables::messages.fields.email'))
                            ->email()
                            // Adding goes through the registration service, which refuses it too
                            ->rule(
                                fn (?ParticipantModel $record): Closure => $this->emailFreeAtTable($record),
                                fn (string $operation): bool => $operation === 'edit',
                            ),
                        TextInput::make('phone')
                            ->label(__('game-tables::messages.fields.phone'))
                            ->tel(),
                    ])
                    ->visible(fn (Get $get, string $operation, ?ParticipantModel $record): bool => $this->isGuestForm($get, $operation, $record)),

                // On Edit the role is fixed: a new role is a new seat, so it means cancelling and
                // adding again, which go through the table lock and the seat rules
                Select::make('role')
                    ->label(__('game-tables::messages.fields.participant_role'))
                    ->options(ParticipantRole::participantOptions())
                    ->default(ParticipantRole::Player->value)
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),

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
     * The guest fields show for a guest: chosen in the form when adding, the record's own when
     * editing (the type cannot change, so a state sent with the form is not trusted).
     */
    private function isGuestForm(Get $get, string $operation, ?ParticipantModel $record): bool
    {
        if ($operation === 'edit') {
            return $record instanceof ParticipantModel && $record->user_id === null;
        }

        return $get('participant_type') === 'external';
    }

    /**
     * One active guest per email and table, whatever the case it is typed in (as on registration).
     */
    private function emailFreeAtTable(?ParticipantModel $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (! $record instanceof ParticipantModel || ! is_string($value) || trim($value) === '') {
                return;
            }

            $activeStatuses = array_map(
                fn (ParticipantStatus $status): string => $status->value,
                array_filter(ParticipantStatus::cases(), fn (ParticipantStatus $status): bool => $status->isActive()),
            );

            $taken = ParticipantModel::query()
                ->where('game_table_id', $record->game_table_id)
                ->whereKeyNot($record->getKey())
                ->whereNull('user_id')
                ->whereIn('status', $activeStatuses)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($value))])
                ->exists();

            if ($taken) {
                $fail(__('game-tables::messages.errors.guest_already_registered'));
            }
        };
    }

    /**
     * The status row actions are for whoever may edit the table, the same as the page they are on.
     */
    private function canManageParticipants(): bool
    {
        return Gate::allows('update', $this->getOwnerRecord());
    }

    /**
     * Deletes the row only if it is still cancelled, rejected or expired when the DELETE runs: a
     * re-registration (placeUser/placeGuest) reactivates the same row, and it may land after the
     * row was read. Nothing listens to participant deletes (the status history keeps its rows,
     * unlinked, by its foreign key), so a query delete loses nothing.
     */
    private function deleteIfCancelledOrRejected(Model $record): bool
    {
        $deletable = array_map(
            fn (ParticipantStatus $status): string => $status->value,
            array_values(array_filter(ParticipantStatus::cases(), fn (ParticipantStatus $status): bool => $status->canBeDeleted())),
        );

        return ParticipantModel::query()
            ->whereKey($record->getKey())
            ->whereIn('status', $deletable)
            ->delete() > 0;
    }

    /**
     * Bulk «Borrar»: deletes the selected rows that are still cancelled, rejected or expired, leaves the
     * others and says how many went each way. A row that fails is reported and counted apart, and
     * the loop goes on, so the notice always counts the rows already deleted.
     *
     * @param  EloquentCollection<int, Model>  $records
     */
    private function deleteCancelledOrRejected(EloquentCollection $records): void
    {
        $deleted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                $this->deleteIfCancelledOrRejected($record) ? $deleted++ : $skipped++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $key = 'game-tables::messages.relation_managers.participants.bulk_delete.';
        $notification = Notification::make()->title(
            $deleted > 0 ? trans_choice($key.'deleted', $deleted, ['count' => $deleted]) : __($key.'none_deleted'),
        );

        if ($skipped === 0 && $failed === 0) {
            $notification->success()->send();

            return;
        }

        $body = [];
        if ($skipped > 0) {
            $body[] = trans_choice($key.'skipped', $skipped, ['count' => $skipped]);
        }
        if ($failed > 0) {
            $body[] = trans_choice($key.'failed', $failed, ['count' => $failed]);
        }

        $notification->body(implode(' ', $body));
        ($deleted > 0 ? $notification->warning() : $notification->danger())->send();
    }

    /**
     * Deleting is managing the participants too; the explicit check replaces Filament's default
     * one, so it keeps its read-only rule.
     */
    private function canDeleteParticipants(): bool
    {
        return ! $this->isReadOnly() && $this->canManageParticipants();
    }

    private function notifySeatsFull(TableFullException $exception): void
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
            // The name column reads the user of each row
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('game-tables::messages.fields.name'))
                    ->getStateUsing(fn (ParticipantModel $record): string => $record->display_name)
                    ->searchable(query: fn ($query, string $search) => $query->where(function ($q) use ($search) {
                        $q->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    }))
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

                // Informative only: neither badge blocks or changes anything
                TextColumn::make('guest_flags')
                    ->label(__('game-tables::messages.relation_managers.participants.flags.label'))
                    ->badge()
                    ->state(fn (ParticipantModel $record): array => $this->guestFlags($record))
                    ->formatStateUsing(fn (string $state): string => __('game-tables::messages.relation_managers.participants.flags.'.$state))
                    ->color(fn (string $state): string => $state === self::FLAG_POSSIBLE_DUPLICATE ? 'danger' : 'warning')
                    ->tooltip(fn (ParticipantModel $record, ?string $state): ?string => $state !== null ? $this->guestFlagTooltip($state, $record) : null),

                TextColumn::make('waiting_list_position')
                    ->label(__('game-tables::messages.fields.waiting_list_position'))
                    // The place among those waiting for the same role: stored positions keep gaps
                    ->state(fn (ParticipantModel $record): ?int => $record->status === ParticipantStatus::WaitingList
                        ? ($this->waitingListPlaces()[$record->id] ?? null)
                        : null)
                    ->numeric()
                    ->visible(fn (): bool => $this->waitingListPlaces() !== []),

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
                // The rows with the «Email sin confirmar» badge
                Filter::make('email_unconfirmed')
                    ->label(__('game-tables::messages.relation_managers.participants.flags.email_unconfirmed'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('user_id')
                        ->whereNull('email_confirmed_at')
                        ->whereIn('status', array_map(
                            fn (ParticipantStatus $status): string => $status->value,
                            array_values(array_filter(ParticipantStatus::cases(), fn (ParticipantStatus $status): bool => $status->isActive())),
                        ))),
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
                            $this->notifySeatsFull($exception);
                        }
                    }),

                // The same as the emailed link: the guest gets the registration email
                Action::make('confirmEmail')
                    ->label(__('game-tables::messages.relation_managers.participants.confirm_email'))
                    ->icon('heroicon-o-envelope-open')
                    ->color('success')
                    ->visible(fn (ParticipantModel $record): bool => $this->isAwaitingEmailConfirmation($record))
                    ->authorize(fn (): bool => $this->canManageParticipants())
                    ->requiresConfirmation()
                    ->modalDescription(__('game-tables::messages.relation_managers.participants.confirm_email_description'))
                    ->action(function (ParticipantModel $record): void {
                        try {
                            app(RegistrationServiceInterface::class)->confirmGuestEmailByAdmin($record->id);
                        } catch (TableFullException $exception) {
                            // A guest without a place finds no room: it stays unconfirmed
                            $this->notifySeatsFull($exception);
                        }
                    }),

                Action::make('reject')
                    ->label(__('game-tables::messages.relation_managers.participants.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ParticipantModel $record): bool => $record->status === ParticipantStatus::Pending)
                    ->authorize(fn (): bool => $this->canManageParticipants())
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('game-tables::messages.relation_managers.participants.reject_reason'))
                            ->maxLength(500),
                    ])
                    ->action(function (ParticipantModel $record, array $data): void {
                        $registrationService = app(RegistrationServiceInterface::class);

                        try {
                            $registrationService->reject($record->id, isset($data['reason']) ? (string) $data['reason'] : null);
                        } catch (ParticipantNotPendingException $exception) {
                            // Someone else already decided it: nothing changes
                            Notification::make()
                                ->title(__('game-tables::messages.errors.'.$exception->reason))
                                ->danger()
                                ->send();
                        }
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
                            $this->notifySeatsFull($exception);
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
                    })
                    ->using(function (Model $record, array $data, EditAction $action): void {
                        $record->fill($data);

                        try {
                            $record->save();
                        } catch (UniqueConstraintViolationException $exception) {
                            if (! $record->isDirty('email')) {
                                throw $exception;
                            }

                            // Another guest took the email after the form's check (the unique index
                            // refuses it): a form error, never a 500 whose SQL carries the email
                            throw ValidationException::withMessages([
                                "mountedActions.{$action->getNestingIndex()}.data.email" => __('game-tables::messages.errors.guest_already_registered'),
                            ]);
                        }
                    }),
                // Only rows that hold no seat: the others are cancelled first (lock, freed seat, promotion)
                DeleteAction::make()
                    ->visible(fn (ParticipantModel $record): bool => $record->status->canBeDeleted())
                    ->authorize(fn (): bool => $this->canDeleteParticipants())
                    ->using(function (Model $record): bool {
                        if ($this->deleteIfCancelledOrRejected($record)) {
                            return true;
                        }

                        // Registered again meanwhile: the row is active, so it stays
                        Notification::make()
                            ->title(__('game-tables::messages.relation_managers.participants.delete_not_allowed'))
                            ->danger()
                            ->send();

                        return false;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => $this->canDeleteParticipants())
                        ->action(fn (EloquentCollection $records) => $this->deleteCancelledOrRejected($records))
                        ->successNotification(null)
                        ->failureNotification(null),
                ]),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
