<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources;

use App\Filament\Resources\BaseResource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Filament\Forms\Components\GameMasterRepeater;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

final class CampaignResource extends BaseResource
{
    protected static ?string $model = CampaignModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 2;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                                    ->maxValue(20),

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
                                    ->helperText(__('game-tables::messages.fields.is_published_campaign_help'))
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
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
