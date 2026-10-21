<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources;

use Closure;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\BaseResource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\InvitationsRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\JoinRequestsRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\RosterRelationManager;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Filament\Forms\Components\GameMasterRepeater;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages;
use Modules\GameTables\Filament\Support\PublicationLock;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

final class CampaignResource extends BaseResource
{
    use ChecksPermissions;

    protected static ?string $model = CampaignModel::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 2;

    /**
     * Approving or rejecting needs the update permission: seeing the campaign or being its author is not enough.
     */
    public static function canModerate(): bool
    {
        return self::userCan('gametables:campaigns.update');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('game-tables::messages.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('game-tables::messages.navigation.campaigns');
    }

    public static function getModelLabel(): string
    {
        return __('game-tables::messages.model.campaign.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('game-tables::messages.model.campaign.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('CampaignTabs')
                    ->tabs([
                        Tab::make(__('game-tables::messages.tabs.basic_info'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('game-tables::messages.fields.title'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Select::make('game_system_id')
                                    ->label(__('game-tables::messages.fields.game_system'))
                                    ->options(GameSystemModel::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false)
                                    ->required(),

                                Select::make('status')
                                    ->label(__('game-tables::messages.fields.campaign_status'))
                                    ->options(CampaignStatus::options())
                                    ->default(CampaignStatus::Recruiting->value)
                                    ->native(false)
                                    ->required(),

                                Textarea::make('description')
                                    ->label(__('game-tables::messages.fields.description'))
                                    ->rows(3)
                                    ->maxLength(2000)
                                    ->columnSpanFull(),

                                FileUpload::make('image_public_id')
                                    ->label(__('game-tables::messages.fields.image'))
                                    ->image()
                                    ->disk('images')
                                    ->directory(fn (): string => 'campaigns/' . now()->format('Y/m'))
                                    ->getUploadedFileNameForStorageUsing(
                                        fn (TemporaryUploadedFile $file): string => Str::uuid()->toString() . '.' . $file->getClientOriginalExtension()
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
                                        GameMasterRepeater::make('gameMasters', minItems: 0, defaultItems: 1),
                                    ]),
                            ]),

                        Tab::make(__('game-tables::messages.tabs.progress'))
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Select::make('frequency')
                                    ->label(__('game-tables::messages.fields.frequency'))
                                    ->options(CampaignFrequency::options())
                                    ->native(false),

                                TextInput::make('max_players')
                                    ->label(__('game-tables::messages.fields.max_players'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->rules([self::fitsTheRoster()]),

                                Select::make('roster_access')
                                    ->label(__('game-tables::messages.fields.roster_access'))
                                    ->helperText(__('game-tables::messages.fields.roster_access_help'))
                                    ->options(RosterAccess::options())
                                    ->default(RosterAccess::Anyone->value)
                                    ->native(false)
                                    ->required(),

                                TextInput::make('session_count')
                                    ->label(__('game-tables::messages.fields.session_count'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText(__('game-tables::messages.fields.session_count_help')),

                                TextInput::make('current_session')
                                    ->label(__('game-tables::messages.fields.current_session'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->helperText(__('game-tables::messages.fields.current_session_help')),
                            ])
                            ->columns(2),

                        Tab::make(__('game-tables::messages.tabs.publication'))
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Toggle::make('accepts_new_players')
                                    ->label(__('game-tables::messages.fields.accepts_new_players'))
                                    ->helperText(__('game-tables::messages.fields.accepts_new_players_help'))
                                    ->default(true),

                                Toggle::make('is_published')
                                    ->label(__('game-tables::messages.fields.is_published'))
                                    // A web-created campaign is published by approving it, never from here
                                    ->disabled(fn (?Model $record): bool => PublicationLock::isLocked($record))
                                    ->helperText(fn (?Model $record): string => PublicationLock::hint($record) ?? __('game-tables::messages.fields.is_published_campaign_help'))
                                    ->default(false),
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

                TextColumn::make('status')
                    ->label(__('game-tables::messages.fields.campaign_status'))
                    ->badge()
                    ->color(fn (CampaignStatus $state): string => $state->color())
                    ->formatStateUsing(fn (CampaignStatus $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('frequency')
                    ->label(__('game-tables::messages.fields.frequency'))
                    ->formatStateUsing(fn (?CampaignFrequency $state): string => $state?->label() ?? '-'),

                TextColumn::make('current_session')
                    ->label(__('game-tables::messages.fields.current_session'))
                    ->numeric()
                    ->sortable(),

                IconColumn::make('accepts_new_players')
                    ->label(__('game-tables::messages.fields.accepts_new_players'))
                    ->boolean(),

                IconColumn::make('is_published')
                    ->label(__('game-tables::messages.fields.is_published'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('game-tables::messages.fields.campaign_status'))
                    ->options(CampaignStatus::options()),
                SelectFilter::make('game_system_id')
                    ->label(__('game-tables::messages.fields.game_system'))
                    ->options(GameSystemModel::query()->where('is_active', true)->pluck('name', 'id')),
                TernaryFilter::make('is_published')
                    ->label(__('game-tables::messages.fields.is_published')),
            ])
            ->recordActions([
                Action::make('approve')
                    ->authorize(fn (): bool => self::canModerate())
                    ->label(__('game-tables::messages.moderation.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('game-tables::messages.moderation.campaign_approve_confirmation'))
                    ->modalDescription(__('game-tables::messages.moderation.campaign_approve_description'))
                    ->visible(fn (CampaignModel $record): bool => $record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                    ->action(function (CampaignModel $record): void {
                        try {
                            app(FrontendCreationServiceInterface::class)->approveCampaign($record->id);
                        } catch (NotEditableException) {
                            self::warnStatusAlreadyChanged();

                            return;
                        }

                        Notification::make()
                            ->title(__('game-tables::messages.notifications.campaign_approved'))
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->authorize(fn (): bool => self::canModerate())
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
                    ->visible(fn (CampaignModel $record): bool => $record->frontend_creation_status === FrontendCreationStatus::PendingReview)
                    ->action(function (CampaignModel $record, array $data): void {
                        try {
                            app(FrontendCreationServiceInterface::class)->rejectCampaign($record->id, (string) $data['reason']);
                        } catch (NotEditableException) {
                            self::warnStatusAlreadyChanged();

                            return;
                        }

                        Notification::make()
                            ->title(__('game-tables::messages.notifications.campaign_rejected'))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function warnStatusAlreadyChanged(): void
    {
        Notification::make()
            ->title(__('game-tables::messages.notifications.campaign_status_already_changed'))
            ->warning()
            ->send();
    }

    public static function getRelations(): array
    {
        return [
            RosterRelationManager::class,
            JoinRequestsRelationManager::class,
            InvitationsRelationManager::class,
        ];
    }

    /**
     * The maximum never goes below the players already in the roster.
     */
    private static function fitsTheRoster(): Closure
    {
        return fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (! $record instanceof CampaignModel || ! is_numeric($value)) {
                return;
            }

            $count = app(CampaignRosterRepositoryInterface::class)->count(new CampaignId($record->id));

            if ((int) $value < $count) {
                $fail(__('game-tables::messages.roster.errors.max_below_roster', ['count' => $count]));
            }
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
