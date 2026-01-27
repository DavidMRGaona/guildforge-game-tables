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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\GameTables\Domain\Enums\WarningSeverity;
use Modules\GameTables\Filament\Resources\ContentWarningResource\Pages;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;

final class ContentWarningResource extends BaseResource
{
    protected static ?string $model = ContentWarningModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __('game-tables::messages.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('game-tables::messages.navigation.content_warnings');
    }

    public static function getModelLabel(): string
    {
        return __('game-tables::messages.model.content_warning.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('game-tables::messages.model.content_warning.plural');
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

                        TextInput::make('label')
                            ->label(__('game-tables::messages.fields.label'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('description')
                            ->label(__('game-tables::messages.fields.description'))
                            ->maxLength(1000),

                        Select::make('severity')
                            ->label(__('game-tables::messages.fields.severity'))
                            ->options(WarningSeverity::options())
                            ->native(false)
                            ->required(),

                        TextInput::make('icon')
                            ->label(__('game-tables::messages.fields.icon'))
                            ->maxLength(100)
                            ->placeholder('heroicon-o-exclamation-triangle'),

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

                TextColumn::make('label')
                    ->label(__('game-tables::messages.fields.label'))
                    ->searchable(),

                TextColumn::make('severity')
                    ->label(__('game-tables::messages.fields.severity'))
                    ->badge()
                    ->color(fn (WarningSeverity $state): string => $state->color())
                    ->formatStateUsing(fn (WarningSeverity $state): string => $state->label())
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
                SelectFilter::make('severity')
                    ->label(__('game-tables::messages.fields.severity'))
                    ->options(WarningSeverity::options()),
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
            ->defaultSort('severity', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentWarnings::route('/'),
            'create' => Pages\CreateContentWarning::route('/create'),
            'edit' => Pages\EditContentWarning::route('/{record}/edit'),
        ];
    }
}
