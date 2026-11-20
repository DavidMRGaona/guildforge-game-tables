<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources;

use App\Filament\Actions\RowDetailsAction;
use App\Filament\Resources\BaseResource;
use App\Filament\Support\PanelNavigationGroup;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GenreColor;
use Modules\GameTables\Domain\ValueObjects\GenreKey;
use Modules\GameTables\Filament\Resources\GenreResource\Pages\CreateGenre;
use Modules\GameTables\Filament\Resources\GenreResource\Pages\EditGenre;
use Modules\GameTables\Filament\Resources\GenreResource\Pages\ListGenres;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

/**
 * The genres tables and campaigns choose from. Their key is what tables store, so it is chosen
 * once; a genre in use is deactivated rather than deleted (GenreObserver refuses the delete).
 */
final class GenreResource extends BaseResource
{
    protected static ?string $model = GenreModel::class;

    protected static ?string $slug = 'generos';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 35;

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::Catalogues->getLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('game-tables::messages.navigation.genres');
    }

    public static function getModelLabel(): string
    {
        return __('game-tables::messages.model.genre.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('game-tables::messages.model.genre.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('game-tables::messages.sections.basic_info'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('game-tables::messages.fields.name'))
                            ->required()
                            ->maxLength(50)
                            ->rule(static fn (?GenreModel $record): Closure => self::uniqueNameRule($record))
                            // While creating, the key follows the name until someone types one
                            ->live(onBlur: true)
                            ->afterStateUpdated(static function (Get $get, Set $set, ?string $old, ?string $state, string $operation): void {
                                if ($operation !== 'create') {
                                    return;
                                }

                                $key = (string) $get('key');

                                if ($key === '' || $key === self::proposedKey($old)) {
                                    $set('key', self::proposedKey($state));
                                }
                            }),

                        TextInput::make('key')
                            ->label(__('game-tables::messages.fields.key'))
                            ->required()
                            ->maxLength(GenreKey::MAX_LENGTH)
                            ->regex(GenreKey::PATTERN)
                            ->unique(ignoreRecord: true)
                            ->helperText(static fn (string $operation): string => $operation === 'create'
                                ? __('game-tables::messages.genres.key_help_create')
                                : __('game-tables::messages.genres.key_help_edit'))
                            // Tables and campaigns store it: once created it never changes
                            ->disabled(static fn (string $operation): bool => $operation !== 'create')
                            ->dehydrated(static fn (string $operation): bool => $operation === 'create'),

                        ToggleButtons::make('color')
                            ->label(__('game-tables::messages.fields.color'))
                            ->options(self::colorOptions())
                            ->colors(array_combine(GenreColor::values(), GenreColor::values()))
                            ->in(GenreColor::values())
                            ->default(GenreColor::default()->value)
                            ->required()
                            ->inline()
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label(__('game-tables::messages.fields.is_active'))
                            ->helperText(__('game-tables::messages.genres.active_help'))
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
                    ->badge()
                    ->color(static fn (GenreModel $record): string => (GenreColor::tryFrom($record->color) ?? GenreColor::default())->value)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('usage')
                    ->label(__('game-tables::messages.fields.usage'))
                    ->state(static fn (GenreModel $record): int => array_sum($record->usage()))
                    ->alignCenter()
                    ->visibleFrom('sm'),

                IconColumn::make('is_active')
                    ->label(__('game-tables::messages.fields.is_active'))
                    ->boolean()
                    ->wrapHeader(),

                TextColumn::make('created_at')
                    ->label(__('game-tables::messages.fields.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('game-tables::messages.fields.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // The observer refuses to delete a genre in use: the rest are still deleted
                    DeleteBulkAction::make()
                        ->schema([self::deletionConfirmation()])
                        ->missingBulkProcessingFailureNotificationMessage(static fn (int $failureCount): string => trans_choice(
                            'game-tables::messages.genre_deletion.bulk_not_deleted',
                            $failureCount,
                            ['count' => $failureCount],
                        )),
                ]),
            ])
            // Only the whole list: dragging a filtered or searched one would number just the rows
            // shown and leave ties with the hidden ones
            ->reorderable('sort_order', static fn (ListGenres $livewire): bool => ! $livewire->hasTableSearch()
                && blank($livewire->getTableFilterState('is_active')['value'] ?? null))
            ->defaultSort('sort_order');
    }

    /**
     * Why a genre in use cannot be deleted, with what uses it; null when nothing does.
     */
    public static function usageDescription(GenreModel $record): ?string
    {
        $usage = $record->usage();
        $parts = array_values(array_filter([
            $usage['tables'] > 0 ? trans_choice('game-tables::messages.genre_deletion.tables', $usage['tables'], ['count' => $usage['tables']]) : null,
            $usage['campaigns'] > 0 ? trans_choice('game-tables::messages.genre_deletion.campaigns', $usage['campaigns'], ['count' => $usage['campaigns']]) : null,
        ]));

        if ($parts === []) {
            return null;
        }

        $text = count($parts) === 2
            ? __('game-tables::messages.genre_deletion.and', ['first' => $parts[0], 'second' => $parts[1]])
            : $parts[0];

        return __(
            $record->is_active ? 'game-tables::messages.genre_deletion.in_use' : 'game-tables::messages.genre_deletion.in_use_inactive',
            ['usage' => $text],
        );
    }

    /**
     * The second confirmation of a deletion, which cannot be undone
     */
    public static function deletionConfirmation(): Checkbox
    {
        return Checkbox::make('confirmation')
            ->label(__('game-tables::messages.genre_deletion.confirmation_check'))
            ->accepted()
            ->required();
    }

    /**
     * The key a name suggests: lower case, without accents, words joined by underscores
     */
    public static function proposedKey(?string $name): string
    {
        return Str::limit(Str::slug((string) $name, '_'), GenreKey::MAX_LENGTH, '');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGenres::route('/'),
            'create' => CreateGenre::route('/create'),
            'edit' => EditGenre::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function colorOptions(): array
    {
        $options = [];

        foreach (GenreColor::cases() as $color) {
            $options[$color->value] = __('game-tables::messages.genres.colors.'.$color->value);
        }

        return $options;
    }

    /**
     * No two genres with the same name, whatever their capitals, so nobody picks the wrong one
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    private static function uniqueNameRule(?GenreModel $record): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $name = mb_strtolower(trim((string) $value));
            $taken = GenreModel::query()
                ->when($record instanceof GenreModel, static fn ($query) => $query->whereKeyNot($record?->getKey()))
                ->pluck('name')
                ->contains(static fn (string $other): bool => mb_strtolower(trim($other)) === $name);

            if ($taken) {
                $fail(__('game-tables::messages.genres.name_taken'));
            }
        };
    }
}
