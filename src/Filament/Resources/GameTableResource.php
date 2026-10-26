<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\BaseResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\Language;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Forms\Components\GameMasterRepeater;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\StatusLogRelationManager;
use Modules\GameTables\Filament\Support\PublicationLock;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;

final class GameTableResource extends BaseResource
{
    use ChecksPermissions;

    protected static ?string $model = GameTableModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?int $navigationSort = 1;

    /**
     * Approving or rejecting needs the update permission: seeing the table or being its author is not enough.
     */
    public static function canModerate(): bool
    {
        return self::userCan('gametables:gametables.update');
    }

    public static function getNavigationGroup(): string
    {
        return (string) __('game-tables::messages.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('game-tables::messages.navigation.tables');
    }

    public static function getModelLabel(): string
    {
        return __('game-tables::messages.model.game_table.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('game-tables::messages.model.game_table.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $settings = app(GameTableSettingsReader::class);

        return $schema
            ->components([
                Tabs::make('GameTableTabs')
                    ->tabs([
                        Tab::make(__('game-tables::messages.tabs.basic_info'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('game-tables::messages.fields.title'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Select::make('campaign_id')
                                    ->label(__('game-tables::messages.fields.campaign'))
                                    ->options(CampaignModel::query()->pluck('title', 'id'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                        if ($state === null) {
                                            return;
                                        }

                                        $campaign = CampaignModel::with('gameMasters')->find($state);
                                        if ($campaign === null) {
                                            return;
                                        }

                                        // Copy game system from campaign
                                        $set('game_system_id', $campaign->game_system_id);

                                        // A maximum the whole roster fits in
                                        $set('max_players', $campaign->max_players ?? max(
                                            app(GameTableSettingsReader::class)->defaultMaxPlayers(),
                                            self::rosterSize($campaign->id),
                                        ));

                                        // Do not overwrite game masters if there are already real ones
                                        /** @var array<int, array<string, mixed>> $currentGameMasters */
                                        $currentGameMasters = $get('gameMasters') ?? [];
                                        if (self::hasRealGameMasters($currentGameMasters)) {
                                            return;
                                        }

                                        if ($campaign->gameMasters->isEmpty()) {
                                            return;
                                        }

                                        // Copy game masters to the repeater (for display only - actual inheritance handled in save)
                                        $gameMastersData = $campaign->gameMasters->map(
                                            fn (GameMasterModel $gm): array => [
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
                                            ]
                                        )->toArray();

                                        $set('gameMasters', $gameMastersData);
                                    }),

                                Select::make('game_system_id')
                                    ->label(__('game-tables::messages.fields.game_system'))
                                    ->options(GameSystemModel::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->disabled(fn (Get $get): bool => $get('campaign_id') !== null)
                                    ->dehydrated(),

                                Select::make('event_id')
                                    ->label(__('game-tables::messages.fields.event'))
                                    ->options(
                                        EventModel::query()
                                            ->where('is_published', true)
                                            ->where('start_date', '>=', now())
                                            ->orderBy('start_date', 'asc')
                                            ->pluck('title', 'id')
                                    )
                                    ->searchable()
                                    ->native(false),

                                Textarea::make('synopsis')
                                    ->label(__('game-tables::messages.fields.synopsis'))
                                    ->rows(3)
                                    ->maxLength(2000)
                                    ->columnSpanFull(),

                                FileUpload::make('image_public_id')
                                    ->label(__('game-tables::messages.fields.image'))
                                    ->image()
                                    ->disk('images')
                                    ->directory(fn (): string => 'game-tables/'.now()->format('Y/m'))
                                    ->getUploadedFileNameForStorageUsing(
                                        fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                                    )
                                    ->maxSize(2048)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make(__('game-tables::messages.tabs.direction'))
                            ->icon('heroicon-o-star')
                            ->schema([
                                Section::make(__('game-tables::messages.sections.game_masters'))
                                    ->schema([
                                        GameMasterRepeater::makeForTable('gameMasters'),
                                    ]),
                            ]),

                        Tab::make(__('game-tables::messages.tabs.schedule'))
                            ->icon('heroicon-o-clock')
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label(__('game-tables::messages.fields.start_time'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->required(),

                                TextInput::make('duration_minutes')
                                    ->label(__('game-tables::messages.fields.duration_minutes'))
                                    ->numeric()
                                    ->minValue(30)
                                    ->maxValue($settings->maxDurationMinutes())
                                    ->default($settings->defaultDurationMinutes())
                                    ->required(),

                                Select::make('table_format')
                                    ->label(__('game-tables::messages.fields.table_format'))
                                    ->options(TableFormat::options())
                                    ->default(TableFormat::InPerson->value)
                                    ->native(false)
                                    ->required()
                                    ->live(),

                                TextInput::make('location')
                                    ->label(__('game-tables::messages.fields.location'))
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => in_array($get('table_format'), [TableFormat::InPerson->value, TableFormat::Hybrid->value])),

                                TextInput::make('online_url')
                                    ->label(__('game-tables::messages.fields.online_url'))
                                    ->url()
                                    ->maxLength(500)
                                    ->visible(fn (Get $get): bool => in_array($get('table_format'), [TableFormat::Online->value, TableFormat::Hybrid->value])),
                            ])
                            ->columns(2),

                        Tab::make(__('game-tables::messages.tabs.capacity'))
                            ->icon('heroicon-o-users')
                            ->schema([
                                Select::make('table_type')
                                    ->label(__('game-tables::messages.fields.table_type'))
                                    ->options(TableType::options())
                                    ->default(TableType::OneShot->value)
                                    ->native(false)
                                    ->required(),

                                TextInput::make('min_players')
                                    ->label(__('game-tables::messages.fields.min_players'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue($settings->maxPlayersLimit())
                                    ->default($settings->defaultMinPlayers())
                                    ->required(),

                                TextInput::make('max_players')
                                    ->label(__('game-tables::messages.fields.max_players'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue($settings->maxPlayersLimit())
                                    ->default($settings->defaultMaxPlayers())
                                    ->required()
                                    ->rule(self::fitsTheCampaignRoster()),

                                TextInput::make('max_spectators')
                                    ->label(__('game-tables::messages.fields.max_spectators'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue($settings->maxSpectatorsLimit())
                                    ->default($settings->defaultMaxSpectators()),

                                TextInput::make('minimum_age')
                                    ->label(__('game-tables::messages.fields.minimum_age'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(99),

                                Select::make('language')
                                    ->label(__('game-tables::messages.fields.language'))
                                    ->options(Language::nativeOptions())
                                    ->default('es')
                                    ->native(false)
                                    ->required(),

                                Select::make('experience_level')
                                    ->label(__('game-tables::messages.fields.experience_level'))
                                    ->options(ExperienceLevel::options())
                                    ->native(false)
                                    ->required(),

                                Select::make('character_creation')
                                    ->label(__('game-tables::messages.fields.character_creation'))
                                    ->options(CharacterCreation::options())
                                    ->native(false)
                                    ->required(),
                            ])
                            ->columns(4),

                        Tab::make(__('game-tables::messages.tabs.content'))
                            ->icon('heroicon-o-shield-exclamation')
                            ->schema([
                                CheckboxList::make('genres')
                                    ->label(__('game-tables::messages.fields.genres'))
                                    ->options(Genre::options())
                                    ->columns(4)
                                    ->columnSpanFull(),

                                Select::make('tone')
                                    ->label(__('game-tables::messages.fields.tone'))
                                    ->options(Tone::options())
                                    ->native(false),

                                CheckboxList::make('safety_tools')
                                    ->label(__('game-tables::messages.fields.safety_tools'))
                                    ->options(SafetyTool::options())
                                    ->columns(4)
                                    ->columnSpanFull(),

                                Select::make('contentWarnings')
                                    ->label(__('game-tables::messages.fields.content_warnings'))
                                    ->multiple()
                                    ->relationship('contentWarnings', 'name')
                                    ->options(ContentWarningModel::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false)
                                    ->preload()
                                    ->columnSpanFull(),

                                TagsInput::make('custom_warnings')
                                    ->label(__('game-tables::messages.fields.custom_warnings'))
                                    ->placeholder(__('game-tables::messages.fields.custom_warnings_placeholder'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make(__('game-tables::messages.tabs.registration'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Select::make('registration_type')
                                    ->label(__('game-tables::messages.fields.registration_type'))
                                    ->options(RegistrationType::options())
                                    ->default($settings->defaultRegistrationType()->value)
                                    ->native(false)
                                    ->required(),

                                TextInput::make('members_early_access_days')
                                    ->label(__('game-tables::messages.fields.members_early_access_days'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(30)
                                    ->default($settings->defaultMembersEarlyAccessDays())
                                    ->required(),

                                DateTimePicker::make('registration_opens_at')
                                    ->label(__('game-tables::messages.fields.registration_opens_at'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i'),

                                DateTimePicker::make('registration_closes_at')
                                    ->label(__('game-tables::messages.fields.registration_closes_at'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->helperText(__('game-tables::messages.fields.registration_closes_at_help'))
                                    ->rule(self::closesNoLaterThanTheTable()),

                                Toggle::make('auto_confirm')
                                    ->label(__('game-tables::messages.fields.auto_confirm'))
                                    ->default($settings->defaultAutoConfirm()),

                                Toggle::make('accepts_registrations_in_progress')
                                    ->label(__('game-tables::messages.fields.accepts_registrations_in_progress'))
                                    ->default(false)
                                    ->helperText(__('game-tables::messages.fields.accepts_registrations_in_progress_help')),

                                Toggle::make('delegates_registrations')
                                    ->label(__('game-tables::messages.fields.delegates_registrations'))
                                    ->default(false)
                                    ->helperText(__('game-tables::messages.fields.delegates_registrations_help'))
                                    ->visible(fn (Get $get): bool => blank($get('campaign_id'))),

                                TextInput::make('notification_email')
                                    ->label(__('game-tables::messages.fields.notification_email'))
                                    ->email()
                                    ->maxLength(255)
                                    ->helperText(__('game-tables::messages.fields.notification_email_help')),
                            ])
                            ->columns(3),

                        Tab::make(__('game-tables::messages.tabs.publication'))
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Toggle::make('is_published')
                                    ->label(__('game-tables::messages.fields.is_published'))
                                    ->default(false)
                                    // A web-created table is published by approving it, never from here
                                    ->disabled(fn (?Model $record): bool => PublicationLock::isLocked($record))
                                    ->helperText(fn (?Model $record): string => PublicationLock::hint($record) ?? __('game-tables::messages.fields.is_published_help')),

                                Select::make('status')
                                    ->label(__('game-tables::messages.fields.table_status'))
                                    // Its own status and the moves between «Programada» and «Completa»;
                                    // starting, completing and cancelling have their own actions
                                    ->options(
                                        fn (?Model $record): array => collect(TableStatus::manualChoicesFrom($record?->getAttribute('status')))
                                            ->mapWithKeys(fn (TableStatus $status): array => [$status->value => $status->label()])
                                            ->toArray()
                                    )
                                    ->default(TableStatus::Scheduled->value)
                                    ->native(false)
                                    ->required(),

                                Textarea::make('notes')
                                    ->label(__('game-tables::messages.fields.notes'))
                                    ->rows(2)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('game-tables::messages.fields.title'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gameSystem.name')
                    ->label(__('game-tables::messages.fields.game_system'))
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label(__('game-tables::messages.fields.start_time'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('table_format')
                    ->label(__('game-tables::messages.fields.table_format'))
                    ->badge()
                    ->color(fn (TableFormat $state): string => $state->color())
                    ->formatStateUsing(fn (TableFormat $state): string => $state->label()),

                TextColumn::make('status')
                    ->label(__('game-tables::messages.fields.table_status'))
                    ->badge()
                    ->color(fn (TableStatus $state): string => $state->color())
                    ->formatStateUsing(fn (TableStatus $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('participants_count')
                    ->label(__('game-tables::messages.model.participant.plural'))
                    ->counts('participants')
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label(__('game-tables::messages.fields.is_published'))
                    ->boolean(),

                IconColumn::make('frontend_creation_status')
                    ->label(__('game-tables::messages.fields.created_from_web'))
                    ->icon(fn (?FrontendCreationStatus $state): ?string => $state?->icon())
                    ->color(fn (?FrontendCreationStatus $state): ?string => $state?->color())
                    ->tooltip(fn (?FrontendCreationStatus $state): ?string => $state?->label())
                    ->visible(fn (): bool => true)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('game-tables::messages.fields.table_status'))
                    ->options(TableStatus::options()),
                SelectFilter::make('table_format')
                    ->label(__('game-tables::messages.fields.table_format'))
                    ->options(TableFormat::options()),
                SelectFilter::make('game_system_id')
                    ->label(__('game-tables::messages.fields.game_system'))
                    ->options(GameSystemModel::query()->where('is_active', true)->pluck('name', 'id')),
                TernaryFilter::make('is_published')
                    ->label(__('game-tables::messages.fields.is_published')),
                SelectFilter::make('frontend_creation_status')
                    ->label(__('game-tables::messages.fields.frontend_creation_status'))
                    // Rejected is no longer written (the enum case stays for old code paths that may still read it)
                    ->options(Arr::except(FrontendCreationStatus::options(), [FrontendCreationStatus::Rejected->value])),
            ])
            ->recordActions([
                Action::make('approve')
                    ->authorize(fn (): bool => self::canModerate())
                    ->label(__('game-tables::messages.moderation.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('game-tables::messages.moderation.approve_confirmation'))
                    ->modalDescription(__('game-tables::messages.moderation.approve_description'))
                    ->visible(fn (GameTableModel $record): bool => $record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                    ->action(function (GameTableModel $record): void {
                        $service = app(FrontendCreationServiceInterface::class);
                        try {
                            $service->approveFrontendCreation($record->id);
                        } catch (NotEditableException) {
                            self::warnStatusAlreadyChanged();

                            return;
                        } catch (SessionBelowRosterException $e) {
                            self::refuseBelowRoster($e);

                            return;
                        }

                        Notification::make()
                            ->title(__('game-tables::messages.notifications.table_approved'))
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->authorize(fn (): bool => self::canModerate())
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
                    ->visible(fn (GameTableModel $record): bool => $record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                    ->action(function (GameTableModel $record, array $data): void {
                        $service = app(FrontendCreationServiceInterface::class);
                        try {
                            $service->rejectFrontendCreation($record->id, $data['reason']);
                        } catch (NotEditableException) {
                            self::warnStatusAlreadyChanged();

                            return;
                        }

                        Notification::make()
                            ->title(__('game-tables::messages.notifications.table_rejected'))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('approve')
                        ->authorize(fn (): bool => self::canModerate())
                        ->label(__('game-tables::messages.bulk_actions.approve'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $service = app(FrontendCreationServiceInterface::class);
                            $approvedCount = 0;
                            $skipped = false;
                            $refused = false;

                            /** @var GameTableModel $record */
                            foreach ($records as $record) {
                                // Only what the author sent to review: never a draft or an approved table
                                if ($record->frontend_creation_status === FrontendCreationStatus::PendingReview) {
                                    try {
                                        $service->approveFrontendCreation($record->id);
                                        $approvedCount++;
                                    } catch (NotEditableException) {
                                        $skipped = true;
                                    } catch (SessionBelowRosterException $e) {
                                        // Left in review: the others are still approved
                                        self::refuseBelowRoster($e);
                                        $refused = true;
                                    }
                                }
                            }

                            // Nothing approved and a refusal or a warning shown: they say it all
                            if ($approvedCount > 0 || (! $refused && ! $skipped)) {
                                Notification::make()
                                    ->title(__('game-tables::messages.bulk_actions.approved_count', ['count' => $approvedCount]))
                                    ->success()
                                    ->send();
                            }

                            if ($skipped) {
                                self::warnStatusAlreadyChanged();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject')
                        ->authorize(fn (): bool => self::canModerate())
                        ->label(__('game-tables::messages.bulk_actions.reject'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('reason')
                                ->label(__('game-tables::messages.fields.rejection_reason'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $service = app(FrontendCreationServiceInterface::class);
                            $rejectedCount = 0;
                            $skipped = false;

                            /** @var GameTableModel $record */
                            foreach ($records as $record) {
                                if ($record->frontend_creation_status === FrontendCreationStatus::PendingReview) {
                                    try {
                                        $service->rejectFrontendCreation($record->id, $data['reason']);
                                        $rejectedCount++;
                                    } catch (NotEditableException) {
                                        $skipped = true;
                                    }
                                }
                            }

                            // Nothing rejected and a warning shown: the warning says it all
                            if ($rejectedCount > 0 || ! $skipped) {
                                Notification::make()
                                    ->title(__('game-tables::messages.bulk_actions.rejected_count', ['count' => $rejectedCount]))
                                    ->success()
                                    ->send();
                            }

                            if ($skipped) {
                                self::warnStatusAlreadyChanged();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    private static function warnStatusAlreadyChanged(): void
    {
        Notification::make()
            ->title(__('game-tables::messages.notifications.status_already_changed'))
            ->warning()
            ->send();
    }

    private static function refuseBelowRoster(SessionBelowRosterException $e): void
    {
        Notification::make()
            ->title($e->getMessage())
            ->danger()
            ->send();
    }

    public static function getRelations(): array
    {
        return [
            ParticipantsRelationManager::class,
            StatusLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGameTables::route('/'),
            'create' => CreateGameTable::route('/create'),
            'edit' => EditGameTable::route('/{record}/edit'),
        ];
    }

    /**
     * The closing date cannot pass the moment the server closes registrations anyway
     * (GameTable::isPastRegistrationDeadline()): the start of the table or, when it admits
     * registrations in play, its end.
     */
    private static function closesNoLaterThanTheTable(): Closure
    {
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            // The rule must never throw: reading a malformed date from the form state can already
            // fail, and the field itself reports it
            $timezone = app(TimezoneServiceInterface::class);

            try {
                // The rule sees the wall time the admin typed in this field, while the form state of
                // the start is already in the storage zone
                $closesAt = self::toDate($value, $timezone->displayTimezone());
                $startsAt = self::toDate($get('starts_at'), $timezone->storageTimezone());
                $inPlay = (bool) $get('accepts_registrations_in_progress');
                $duration = $get('duration_minutes');
            } catch (\Throwable) {
                return;
            }

            if (! $closesAt instanceof CarbonImmutable || ! $startsAt instanceof CarbonImmutable) {
                return;
            }

            // The duration field reports its own error
            if ($inPlay && (! is_numeric($duration) || (int) $duration < 1 || (int) $duration > 525600)) {
                return;
            }

            $limit = $inPlay ? $startsAt->addMinutes((int) $duration) : $startsAt;

            if ($closesAt <= $limit) {
                return;
            }

            $fail(__('game-tables::messages.validation.'.($inPlay ? 'registration_closes_after_end' : 'registration_closes_after_start'), [
                'date' => $timezone->format($limit),
            ]));
        };
    }

    /**
     * A session never has fewer seats than the roster of its campaign. A session that has started or
     * ended is left alone: the roster may have grown since, and it no longer enrols anybody.
     */
    private static function fitsTheCampaignRoster(): Closure
    {
        return fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
            if ($record instanceof GameTableModel && self::hasStartedOrEnded($record)) {
                return;
            }

            $campaignId = $get('campaign_id');

            if (! is_numeric($value) || ! is_string($campaignId) || $campaignId === '') {
                return;
            }

            $rosterSize = self::rosterSize($campaignId);

            if ((int) $value < $rosterSize) {
                $fail(__('game-tables::messages.validation.max_players_below_roster', ['count' => $rosterSize]));
            }
        };
    }

    private static function hasStartedOrEnded(GameTableModel $table): bool
    {
        return $table->status->isFinal()
            || $table->status === TableStatus::InProgress
            || $table->starts_at <= CarbonImmutable::now();
    }

    private static function rosterSize(string $campaignId): int
    {
        return app(CampaignRosterRepositoryInterface::class)->count(new CampaignId($campaignId));
    }

    private static function toDate(mixed $value, DateTimeZone $zone): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        // The rule must never throw: an unparseable date is reported by the field itself
        try {
            return CarbonImmutable::parse($value, $zone);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Check if the game masters array contains actual data (not just empty default items).
     *
     * @param  array<int, array<string, mixed>>  $gameMasters
     */
    private static function hasRealGameMasters(array $gameMasters): bool
    {
        if ($gameMasters === []) {
            return false;
        }

        return array_any($gameMasters, fn ($gm) => ! empty($gm['user_id']) || ! empty($gm['first_name']) || ! empty($gm['email']));
    }
}
