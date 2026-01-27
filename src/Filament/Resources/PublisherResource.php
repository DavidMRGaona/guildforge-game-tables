<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources;

use Filament\Forms\Components\Section;
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
use Modules\GameTables\Filament\Resources\PublisherResource\Pages;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;

final class PublisherResource extends BaseResource
{
    protected static ?string $model = PublisherModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('game-tables::messages.navigation.catalog_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('game-tables::messages.navigation.publishers');
    }

    public static function getModelLabel(): string
    {
        return __('game-tables::messages.model.publisher.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('game-tables::messages.model.publisher.plural');
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

                        TextInput::make('description')
                            ->label(__('game-tables::messages.fields.description'))
                            ->maxLength(1000),

                        TextInput::make('country')
                            ->label(__('game-tables::messages.fields.country'))
                            ->maxLength(100),

                        TextInput::make('website_url')
                            ->label(__('game-tables::messages.fields.website_url'))
                            ->url()
                            ->maxLength(500),

                        TextInput::make('logo_url')
                            ->label(__('game-tables::messages.fields.logo_url'))
                            ->url()
                            ->maxLength(500),

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

                TextColumn::make('country')
                    ->label(__('game-tables::messages.fields.country'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('game_systems_count')
                    ->label(__('game-tables::messages.fields.game_systems_count'))
                    ->counts('gameSystems')
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
            'index' => Pages\ListPublishers::route('/'),
            'create' => Pages\CreatePublisher::route('/create'),
            'edit' => Pages\EditPublisher::route('/{record}/edit'),
        ];
    }
}
