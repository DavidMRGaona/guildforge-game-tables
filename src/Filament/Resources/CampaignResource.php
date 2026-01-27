<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use App\Filament\Resources\BaseResource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
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
                Section::make(__('game-tables::messages.sections.basic_info'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('game-tables::messages.fields.title'))
                            ->required()
                            ->maxLength(255),

                        Select::make('game_system_id')
                            ->label(__('game-tables::messages.fields.game_system'))
                            ->options(GameSystemModel::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->native(false)
                            ->required(),

                        Textarea::make('description')
                            ->label(__('game-tables::messages.fields.description'))
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->columns(2),

                Section::make(__('game-tables::messages.sections.campaign_info'))
                    ->schema([
                        Select::make('status')
                            ->label(__('game-tables::messages.fields.campaign_status'))
                            ->options(CampaignStatus::options())
                            ->default(CampaignStatus::Recruiting->value)
                            ->native(false)
                            ->required(),

                        Select::make('frequency')
                            ->label(__('game-tables::messages.fields.frequency'))
                            ->options(CampaignFrequency::options())
                            ->native(false),

                        TextInput::make('session_count')
                            ->label(__('game-tables::messages.fields.session_count'))
                            ->numeric()
                            ->minValue(1),

                        TextInput::make('current_session')
                            ->label(__('game-tables::messages.fields.current_session'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Toggle::make('accepts_new_players')
                            ->label(__('game-tables::messages.fields.accepts_new_players'))
                            ->default(true),
                    ])
                    ->columns(3),
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
