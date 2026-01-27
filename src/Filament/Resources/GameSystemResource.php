<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use App\Filament\Resources\BaseResource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

final class GameSystemResource extends BaseResource
{
    protected static ?string $model = GameSystemModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('game-tables::messages.navigation.catalog_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('game-tables::messages.navigation.game_systems');
    }

    public static function getModelLabel(): string
    {
        return __('game-tables::messages.model.game_system.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('game-tables::messages.model.game_system.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('game-tables::messages.sections.basic_info'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('game-tables::messages.fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label(__('game-tables::messages.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Select::make('publisher_id')
                            ->label(__('game-tables::messages.fields.publisher'))
                            ->relationship('publisher', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('game-tables::messages.fields.name'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('slug')
                                    ->label(__('game-tables::messages.fields.slug'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->native(false),

                        TextInput::make('edition')
                            ->label(__('game-tables::messages.fields.edition'))
                            ->maxLength(100),

                        TextInput::make('year')
                            ->label(__('game-tables::messages.fields.year'))
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(2100),

                        TextInput::make('description')
                            ->label(__('game-tables::messages.fields.description'))
                            ->maxLength(1000),

                        TextInput::make('logo_url')
                            ->label(__('game-tables::messages.fields.logo_url'))
                            ->url()
                            ->maxLength(500),

                        TextInput::make('website_url')
                            ->label(__('game-tables::messages.fields.website_url'))
                            ->url()
                            ->maxLength(500),

                        TextInput::make('game_master_title')
                            ->label(__('game-tables::messages.fields.game_master_title'))
                            ->default('Director de juego')
                            ->maxLength(100)
                            ->helperText(__('game-tables::messages.fields.game_master_title_help')),

                        Toggle::make('is_active')
                            ->label(__('game-tables::messages.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('game-tables::messages.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('publisher.name')
                    ->label(__('game-tables::messages.fields.publisher'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('edition')
                    ->label(__('game-tables::messages.fields.edition'))
                    ->sortable(),

                TextColumn::make('year')
                    ->label(__('game-tables::messages.fields.year'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('game-tables::messages.fields.is_active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('game-tables::messages.fields.is_active')),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGameSystems::route('/'),
            'create' => Pages\CreateGameSystem::route('/create'),
            'edit' => Pages\EditGameSystem::route('/{record}/edit'),
        ];
    }
}
