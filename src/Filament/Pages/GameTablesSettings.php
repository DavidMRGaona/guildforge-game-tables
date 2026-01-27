<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
final class GameTablesSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.simple-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('game-tables::messages.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('game-tables::messages.navigation.settings');
    }

    public function getTitle(): string
    {
        return __('game-tables::messages.settings.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(ModuleManagerServiceInterface $moduleManager): void
    {
        $settings = $moduleManager->getSettings(new ModuleName('gametables'));

        // Load settings with defaults from config
        $defaults = config('game-tables');

        $this->form->fill(array_merge($defaults, $settings));
    }

    /**
     * Get the form schema components for settings.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    public static function getFormSchemaComponents(): array
    {
        return [
            Section::make(__('game-tables::messages.settings.sections.creation'))
                ->schema([
                    Select::make('creators')
                        ->label(__('game-tables::messages.settings.fields.creators'))
                        ->helperText(__('game-tables::messages.settings.fields.creators_help'))
                        ->options([
                            'admin' => __('game-tables::messages.settings.creators.admin'),
                            'members' => __('game-tables::messages.settings.creators.members'),
                            'permission' => __('game-tables::messages.settings.creators.permission'),
                        ])
                        ->default('admin')
                        ->native(false)
                        ->required(),
                ]),

            Section::make(__('game-tables::messages.settings.sections.membership'))
                ->schema([
                    Toggle::make('membership_integration.enabled')
                        ->label(__('game-tables::messages.settings.fields.membership_enabled'))
                        ->helperText(__('game-tables::messages.settings.fields.membership_enabled_help'))
                        ->default(true),

                    TextInput::make('defaults.members_early_access_days')
                        ->label(__('game-tables::messages.settings.fields.early_access_days'))
                        ->helperText(__('game-tables::messages.settings.fields.early_access_days_help'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(30)
                        ->default(0),
                ])
                ->columns(2),

            Section::make(__('game-tables::messages.settings.sections.defaults'))
                ->schema([
                    TextInput::make('defaults.duration_minutes')
                        ->label(__('game-tables::messages.settings.fields.default_duration'))
                        ->numeric()
                        ->minValue(30)
                        ->maxValue(720)
                        ->default(240)
                        ->required(),

                    TextInput::make('defaults.min_players')
                        ->label(__('game-tables::messages.settings.fields.default_min_players'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(3)
                        ->required(),

                    TextInput::make('defaults.max_players')
                        ->label(__('game-tables::messages.settings.fields.default_max_players'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(5)
                        ->required(),

                    TextInput::make('defaults.max_spectators')
                        ->label(__('game-tables::messages.settings.fields.default_max_spectators'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->default(0),

                    Toggle::make('defaults.auto_confirm')
                        ->label(__('game-tables::messages.settings.fields.auto_confirm'))
                        ->helperText(__('game-tables::messages.settings.fields.auto_confirm_help'))
                        ->default(true),

                    Select::make('defaults.registration_type')
                        ->label(__('game-tables::messages.settings.fields.default_registration_type'))
                        ->options([
                            'everyone' => __('game-tables::messages.registration_types.everyone'),
                            'members_only' => __('game-tables::messages.registration_types.members_only'),
                        ])
                        ->default('everyone')
                        ->native(false),
                ])
                ->columns(3),

            Section::make(__('game-tables::messages.settings.sections.notifications'))
                ->schema([
                    Toggle::make('notifications.notify_on_registration')
                        ->label(__('game-tables::messages.settings.fields.notify_on_registration'))
                        ->default(true),

                    Toggle::make('notifications.notify_on_cancellation')
                        ->label(__('game-tables::messages.settings.fields.notify_on_cancellation'))
                        ->default(true),

                    Toggle::make('notifications.notify_waiting_list_promotion')
                        ->label(__('game-tables::messages.settings.fields.notify_waiting_list'))
                        ->default(true),
                ])
                ->columns(3),

            Section::make(__('game-tables::messages.settings.sections.limits'))
                ->schema([
                    TextInput::make('limits.max_players_limit')
                        ->label(__('game-tables::messages.settings.fields.max_players_limit'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(20)
                        ->required(),

                    TextInput::make('limits.max_spectators_limit')
                        ->label(__('game-tables::messages.settings.fields.max_spectators_limit'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(200)
                        ->default(50)
                        ->required(),

                    TextInput::make('limits.max_duration_minutes')
                        ->label(__('game-tables::messages.settings.fields.max_duration'))
                        ->numeric()
                        ->minValue(60)
                        ->maxValue(1440)
                        ->default(720)
                        ->required(),
                ])
                ->columns(3),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(self::getFormSchemaComponents())
            ->statePath('data');
    }

    public function save(ModuleManagerServiceInterface $moduleManager): void
    {
        $formData = $this->form->getState();

        $moduleManager->updateSettings(new ModuleName('gametables'), $formData);

        Notification::make()
            ->title(__('common.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }
}
