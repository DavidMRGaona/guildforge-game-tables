<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Forms\Components;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Modules\GameTables\Domain\Enums\GameMasterRole;

final class GameMasterRepeater
{
    /**
     * Build the game master repeater component for campaigns.
     * Campaigns have direct GM associations (no inheritance).
     *
     * @param  int  $minItems  Minimum number of items (default: 1)
     * @param  int  $defaultItems  Default number of items when creating (default: 1)
     */
    public static function make(
        string $name = 'gameMasters',
        int $minItems = 1,
        int $defaultItems = 1,
    ): Repeater {
        return Repeater::make($name)
            ->label(__('game-tables::messages.fields.game_masters'))
            ->addActionLabel(__('game-tables::messages.actions.add_game_master'))
            ->schema(self::getSchema())
            ->defaultItems($defaultItems)
            ->minItems($minItems)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => self::getItemLabel($state))
            ->columnSpanFull();
    }

    /**
     * Build the game master repeater component for game tables.
     * Tables can have inherited GMs from campaigns and local GMs.
     *
     * @param  int  $minItems  Minimum number of items (default: 0, since inherited GMs count)
     */
    public static function makeForTable(
        string $name = 'gameMasters',
        int $minItems = 0,
    ): Repeater {
        return Repeater::make($name)
            ->label(__('game-tables::messages.fields.game_masters'))
            ->addActionLabel(__('game-tables::messages.actions.add_game_master'))
            ->schema(self::getSchemaForTable())
            ->defaultItems(0)
            ->minItems($minItems)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => self::getItemLabelForTable($state))
            ->columnSpanFull();
    }

    /**
     * Get the item label for a game master.
     *
     * @param  array<string, mixed>  $state
     */
    private static function getItemLabel(array $state): ?string
    {
        if (! empty($state['custom_title'])) {
            return $state['custom_title'];
        }

        if (! empty($state['first_name'])) {
            return $state['first_name'];
        }

        return __('game-tables::messages.fields.game_master');
    }

    /**
     * Get the item label for a game master in a table context (with inheritance info).
     *
     * @param  array<string, mixed>  $state
     */
    private static function getItemLabelForTable(array $state): ?string
    {
        $label = self::getItemLabel($state);

        if (! empty($state['is_inherited'])) {
            $label .= ' ' . __('game-tables::messages.fields.inherited_badge');
        }

        if (! empty($state['excluded'])) {
            $label .= ' ' . __('game-tables::messages.fields.excluded_badge');
        }

        return $label;
    }

    /**
     * Get the schema for a single game master item (campaigns).
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getSchema(): array
    {
        return [
            Hidden::make('id'),

            Select::make('gm_type')
                ->label(__('game-tables::messages.fields.gm_type'))
                ->options([
                    'user' => __('game-tables::messages.fields.gm_type_user'),
                    'external' => __('game-tables::messages.fields.gm_type_external'),
                ])
                ->default('user')
                ->required()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function (Select $component, Get $get): void {
                    $userId = $get('user_id');
                    $component->state($userId !== null ? 'user' : 'external');
                }),

            Select::make('user_id')
                ->label(__('game-tables::messages.fields.user'))
                ->options(fn (): array => UserModel::query()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('gm_type') === 'user')
                ->required(fn (Get $get): bool => $get('gm_type') === 'user'),

            Grid::make(2)
                ->schema([
                    TextInput::make('first_name')
                        ->label(__('game-tables::messages.fields.first_name'))
                        ->required(fn (Get $get): bool => $get('gm_type') === 'external'),
                    TextInput::make('last_name')
                        ->label(__('game-tables::messages.fields.last_name')),
                ])
                ->visible(fn (Get $get): bool => $get('gm_type') === 'external'),

            Grid::make(2)
                ->schema([
                    TextInput::make('email')
                        ->label(__('game-tables::messages.fields.email'))
                        ->email(),
                    TextInput::make('phone')
                        ->label(__('game-tables::messages.fields.phone'))
                        ->tel(),
                ])
                ->visible(fn (Get $get): bool => $get('gm_type') === 'external'),

            Grid::make(2)
                ->schema([
                    Select::make('role')
                        ->label(__('game-tables::messages.fields.gm_role'))
                        ->options(GameMasterRole::options())
                        ->default(GameMasterRole::Main->value)
                        ->native(false)
                        ->required(),

                    TextInput::make('custom_title')
                        ->label(__('game-tables::messages.fields.custom_title'))
                        ->placeholder(__('game-tables::messages.fields.custom_title_placeholder'))
                        ->helperText(__('game-tables::messages.fields.custom_title_help'))
                        ->maxLength(100),
                ]),

            Grid::make(2)
                ->schema([
                    Toggle::make('notify_by_email')
                        ->label(__('game-tables::messages.fields.notify_by_email'))
                        ->default(true),
                    Toggle::make('is_name_public')
                        ->label(__('game-tables::messages.fields.is_name_public'))
                        ->default(true),
                ]),

            Textarea::make('notes')
                ->label(__('game-tables::messages.fields.notes'))
                ->rows(2)
                ->maxLength(500),
        ];
    }

    /**
     * Get the schema for a single game master item (game tables - with inheritance support).
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getSchemaForTable(): array
    {
        return [
            Hidden::make('id'),
            Hidden::make('is_inherited'),

            // Show inheritance badge for inherited GMs
            Placeholder::make('inherited_notice')
                ->label('')
                ->content(fn (Get $get): HtmlString => new HtmlString(
                    '<div class="text-sm text-primary-600 dark:text-primary-400 font-medium flex items-center gap-1">' .
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' .
                    __('game-tables::messages.fields.inherited_from_campaign') .
                    '</div>'
                ))
                ->visible(fn (Get $get): bool => (bool) $get('is_inherited')),

            // Exclude toggle for inherited GMs
            Toggle::make('excluded')
                ->label(__('game-tables::messages.fields.exclude_from_table'))
                ->helperText(__('game-tables::messages.fields.exclude_from_table_help'))
                ->visible(fn (Get $get): bool => (bool) $get('is_inherited'))
                ->live(),

            // Regular GM fields (hidden if excluded)
            Select::make('gm_type')
                ->label(__('game-tables::messages.fields.gm_type'))
                ->options([
                    'user' => __('game-tables::messages.fields.gm_type_user'),
                    'external' => __('game-tables::messages.fields.gm_type_external'),
                ])
                ->default('user')
                ->required()
                ->live()
                ->dehydrated(false)
                ->visible(fn (Get $get): bool => ! $get('is_inherited') || ! $get('excluded'))
                ->disabled(fn (Get $get): bool => (bool) $get('is_inherited'))
                ->afterStateHydrated(function (Select $component, Get $get): void {
                    $userId = $get('user_id');
                    $component->state($userId !== null ? 'user' : 'external');
                }),

            Select::make('user_id')
                ->label(__('game-tables::messages.fields.user'))
                ->options(fn (): array => UserModel::query()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('gm_type') === 'user' && (! $get('is_inherited') || ! $get('excluded')))
                ->disabled(fn (Get $get): bool => (bool) $get('is_inherited'))
                ->required(fn (Get $get): bool => $get('gm_type') === 'user' && ! $get('is_inherited')),

            Grid::make(2)
                ->schema([
                    TextInput::make('first_name')
                        ->label(__('game-tables::messages.fields.first_name'))
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited'))
                        ->required(fn (Get $get): bool => $get('gm_type') === 'external' && ! $get('is_inherited')),
                    TextInput::make('last_name')
                        ->label(__('game-tables::messages.fields.last_name'))
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited')),
                ])
                ->visible(fn (Get $get): bool => $get('gm_type') === 'external' && (! $get('is_inherited') || ! $get('excluded'))),

            Grid::make(2)
                ->schema([
                    TextInput::make('email')
                        ->label(__('game-tables::messages.fields.email'))
                        ->email()
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited')),
                    TextInput::make('phone')
                        ->label(__('game-tables::messages.fields.phone'))
                        ->tel()
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited')),
                ])
                ->visible(fn (Get $get): bool => $get('gm_type') === 'external' && (! $get('is_inherited') || ! $get('excluded'))),

            Grid::make(2)
                ->schema([
                    Select::make('role')
                        ->label(__('game-tables::messages.fields.gm_role'))
                        ->options(GameMasterRole::options())
                        ->default(GameMasterRole::Main->value)
                        ->native(false)
                        ->required(fn (Get $get): bool => ! $get('is_inherited'))
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited')),

                    TextInput::make('custom_title')
                        ->label(__('game-tables::messages.fields.custom_title'))
                        ->placeholder(__('game-tables::messages.fields.custom_title_placeholder'))
                        ->helperText(__('game-tables::messages.fields.custom_title_help'))
                        ->maxLength(100)
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited')),
                ])
                ->visible(fn (Get $get): bool => ! $get('is_inherited') || ! $get('excluded')),

            Grid::make(2)
                ->schema([
                    Toggle::make('notify_by_email')
                        ->label(__('game-tables::messages.fields.notify_by_email'))
                        ->default(true)
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited')),
                    Toggle::make('is_name_public')
                        ->label(__('game-tables::messages.fields.is_name_public'))
                        ->default(true)
                        ->disabled(fn (Get $get): bool => (bool) $get('is_inherited')),
                ])
                ->visible(fn (Get $get): bool => ! $get('is_inherited') || ! $get('excluded')),

            Textarea::make('notes')
                ->label(__('game-tables::messages.fields.notes'))
                ->rows(2)
                ->maxLength(500)
                ->disabled(fn (Get $get): bool => (bool) $get('is_inherited'))
                ->visible(fn (Get $get): bool => ! $get('is_inherited') || ! $get('excluded')),
        ];
    }
}
