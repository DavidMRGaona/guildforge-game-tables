<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.relation_managers.participants.title');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->afterStateHydrated(function (Select $component, ?string $state, ?array $record): void {
                        if ($record !== null && isset($record['user_id'])) {
                            $component->state($record['user_id'] !== null ? 'user' : 'external');
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

                Select::make('status')
                    ->label(__('game-tables::messages.fields.participant_status'))
                    ->options(ParticipantStatus::options())
                    ->default(ParticipantStatus::Pending->value)
                    ->required(),

                Textarea::make('notes')
                    ->label(__('game-tables::messages.fields.notes'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(2);
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
                    ->numeric()
                    ->visible(fn (): bool => $this->getOwnerRecord()->participants()->where('status', ParticipantStatus::WaitingList->value)->exists()),

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
                    ->label(__('game-tables::messages.relation_managers.participants.add')),
            ])
            ->actions([
                Action::make('confirm')
                    ->label(__('game-tables::messages.relation_managers.participants.confirm'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ParticipantModel $record): bool => $record->status === ParticipantStatus::Pending)
                    ->action(fn (ParticipantModel $record) => $record->update([
                        'status' => ParticipantStatus::Confirmed->value,
                        'confirmed_at' => now(),
                    ])),

                Action::make('reject')
                    ->label(__('game-tables::messages.relation_managers.participants.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ParticipantModel $record): bool => $record->status === ParticipantStatus::Pending)
                    ->action(fn (ParticipantModel $record) => $record->update([
                        'status' => ParticipantStatus::Rejected->value,
                    ])),

                Action::make('promote')
                    ->label(__('game-tables::messages.relation_managers.participants.promote'))
                    ->icon('heroicon-o-arrow-up')
                    ->color('info')
                    ->visible(fn (ParticipantModel $record): bool => $record->status === ParticipantStatus::WaitingList)
                    ->action(fn (ParticipantModel $record) => $record->update([
                        'status' => ParticipantStatus::Confirmed->value,
                        'waiting_list_position' => null,
                        'confirmed_at' => now(),
                    ])),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
