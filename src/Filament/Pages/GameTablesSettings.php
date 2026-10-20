<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Pages;

use Closure;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\SafeModuleNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;

/**
 * @property \Filament\Schemas\Schema $form
 */
final class GameTablesSettings extends Page implements HasForms
{
    use ChecksPermissions;
    use InteractsWithForms;
    use SafeModuleNavigation;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.simple-settings';

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
        // Granted in Roles; admins hold every permission
        return self::userCan('gametables:gametables.settings');
    }

    public function mount(GameTableSettingsReader $settings): void
    {
        // Saved values over the shipped defaults (config/settings.php)
        $this->form->fill($settings->all());
    }

    /**
     * Get the form schema components for settings.
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    public static function getFormSchemaComponents(): array
    {
        return [
            Section::make(__('game-tables::messages.settings.sections.membership'))
                ->schema([
                    Toggle::make('membership_integration.enabled')
                        ->label(__('game-tables::messages.settings.fields.membership_enabled'))
                        ->helperText(__('game-tables::messages.settings.fields.membership_enabled_help')),

                    TextInput::make('defaults.members_early_access_days')
                        ->label(__('game-tables::messages.settings.fields.early_access_days'))
                        ->helperText(__('game-tables::messages.settings.fields.early_access_days_help'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(30),
                ])
                ->columns(2),

            Section::make(__('game-tables::messages.settings.sections.registration'))
                ->schema([
                    Toggle::make('registration.waiting_list_enabled')
                        ->label(__('game-tables::messages.settings.fields.waiting_list_enabled'))
                        ->helperText(__('game-tables::messages.settings.fields.waiting_list_enabled_help')),
                ]),

            Section::make(__('game-tables::messages.settings.sections.guest_registrations'))
                ->schema([
                    Select::make('guest_confirmation.mode')
                        ->label(__('game-tables::messages.settings.fields.guest_confirmation_mode'))
                        ->options([
                            'hold' => __('game-tables::messages.settings.guest_confirmation_mode.hold'),
                            'wait' => __('game-tables::messages.settings.guest_confirmation_mode.wait'),
                        ])
                        ->in(['hold', 'wait'])
                        ->native(false)
                        ->required(),

                    TextInput::make('guest_confirmation.hold_hours')
                        ->label(__('game-tables::messages.settings.fields.guest_hold_hours'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(168)
                        ->required(),

                    TextInput::make('guest_confirmation.max_unconfirmed_held')
                        ->label(__('game-tables::messages.settings.fields.guest_max_unconfirmed_held'))
                        ->helperText(__('game-tables::messages.settings.fields.guest_max_unconfirmed_held_help'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(50)
                        ->required(),

                    Select::make('guest_accounts.mode')
                        ->label(__('game-tables::messages.settings.fields.guest_accounts_mode'))
                        ->options([
                            'require_login' => __('game-tables::messages.settings.guest_accounts_mode.require_login'),
                            'allow_guest' => __('game-tables::messages.settings.guest_accounts_mode.allow_guest'),
                        ])
                        ->in(['require_login', 'allow_guest'])
                        ->native(false)
                        ->required(),

                    TextInput::make('guest_rate_limit.per_minute')
                        ->label(__('game-tables::messages.settings.fields.guest_rate_per_minute'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required(),

                    TextInput::make('guest_rate_limit.per_hour')
                        ->label(__('game-tables::messages.settings.fields.guest_rate_per_hour'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->required(),

                    TextInput::make('duplicate_signal.window_hours')
                        ->label(__('game-tables::messages.settings.fields.duplicate_window_hours'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(720)
                        ->required(),
                ])
                ->columns(2),

            Section::make(__('game-tables::messages.settings.sections.automation'))
                ->schema([
                    Toggle::make('automation.auto_status_enabled')
                        ->label(__('game-tables::messages.settings.fields.auto_status_enabled'))
                        ->helperText(__('game-tables::messages.settings.fields.auto_status_enabled_help')),
                ]),

            Section::make(__('game-tables::messages.settings.sections.defaults'))
                ->schema([
                    // The fixed bounds stay as outer bounds; each default also stays within the
                    // limit sent in the same submission, so a new table never starts above it
                    TextInput::make('defaults.duration_minutes')
                        ->label(__('game-tables::messages.settings.fields.default_duration'))
                        ->numeric()
                        ->minValue(30)
                        ->maxValue(720)
                        ->rule(self::notAbove('limits.max_duration_minutes', 'above_duration_limit'))
                        ->required(),

                    TextInput::make('defaults.min_players')
                        ->label(__('game-tables::messages.settings.fields.default_min_players'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->rule(self::notAbove('limits.max_players_limit', 'above_players_limit'))
                        ->rule(self::notAbove('defaults.max_players', 'min_above_max_players'))
                        ->required(),

                    TextInput::make('defaults.max_players')
                        ->label(__('game-tables::messages.settings.fields.default_max_players'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->rule(self::notAbove('limits.max_players_limit', 'above_players_limit'))
                        ->required(),

                    TextInput::make('defaults.max_spectators')
                        ->label(__('game-tables::messages.settings.fields.default_max_spectators'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->rule(self::notAbove('limits.max_spectators_limit', 'above_spectators_limit')),

                    Toggle::make('defaults.auto_confirm')
                        ->label(__('game-tables::messages.settings.fields.auto_confirm'))
                        ->helperText(__('game-tables::messages.settings.fields.auto_confirm_help')),

                    Select::make('defaults.registration_type')
                        ->label(__('game-tables::messages.settings.fields.default_registration_type'))
                        ->options([
                            'everyone' => __('game-tables::messages.registration_types.everyone'),
                            'members_only' => __('game-tables::messages.registration_types.members_only'),
                        ])
                        ->native(false),
                ])
                ->columns(3),

            Section::make(__('game-tables::messages.settings.sections.notifications'))
                ->schema([
                    Toggle::make('notifications.notify_on_registration')
                        ->label(__('game-tables::messages.settings.fields.notify_on_registration')),

                    Toggle::make('notifications.notify_on_cancellation')
                        ->label(__('game-tables::messages.settings.fields.notify_on_cancellation')),

                    Toggle::make('notifications.notify_waiting_list_promotion')
                        ->label(__('game-tables::messages.settings.fields.notify_waiting_list'))
                        ->helperText(__('game-tables::messages.settings.fields.notify_waiting_list_help')),

                    Toggle::make('notifications.send_reminder')
                        ->label(__('game-tables::messages.settings.fields.send_reminder'))
                        ->helperText(__('game-tables::messages.settings.fields.send_reminder_help')),

                    TextInput::make('notifications.reminder_hours_before')
                        ->label(__('game-tables::messages.settings.fields.reminder_hours_before'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(168),
                ])
                ->columns(3),

            Section::make(__('game-tables::messages.settings.sections.campaign_notifications'))
                ->description(__('game-tables::messages.settings.sections.campaign_notifications_description'))
                ->schema([
                    Toggle::make('campaign_notifications.session_scheduled')
                        ->label(__('game-tables::messages.settings.fields.campaign_notify_session_scheduled')),

                    Toggle::make('campaign_notifications.join_request_received')
                        ->label(__('game-tables::messages.settings.fields.campaign_notify_join_request_received')),

                    Toggle::make('campaign_notifications.join_request_accepted')
                        ->label(__('game-tables::messages.settings.fields.campaign_notify_join_request_accepted')),

                    Toggle::make('campaign_notifications.join_request_rejected')
                        ->label(__('game-tables::messages.settings.fields.campaign_notify_join_request_rejected')),

                    Toggle::make('campaign_notifications.roster_added')
                        ->label(__('game-tables::messages.settings.fields.campaign_notify_roster_added')),

                    Toggle::make('campaign_notifications.roster_removed')
                        ->label(__('game-tables::messages.settings.fields.campaign_notify_roster_removed')),

                    Toggle::make('campaign_notifications.player_left')
                        ->label(__('game-tables::messages.settings.fields.campaign_notify_player_left')),
                ])
                ->columns(2),

            Section::make(__('game-tables::messages.settings.sections.limits'))
                ->schema([
                    TextInput::make('limits.max_players_limit')
                        ->label(__('game-tables::messages.settings.fields.max_players_limit'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required(),

                    TextInput::make('limits.max_spectators_limit')
                        ->label(__('game-tables::messages.settings.fields.max_spectators_limit'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(200)
                        ->required(),

                    TextInput::make('limits.max_duration_minutes')
                        ->label(__('game-tables::messages.settings.fields.max_duration'))
                        ->numeric()
                        ->minValue(60)
                        ->maxValue(1440)
                        ->required(),
                ])
                ->columns(3),

            Section::make(__('game-tables::messages.settings.sections.frontend_creation'))
                ->description(__('game-tables::messages.settings.sections.frontend_creation_description'))
                ->schema([
                    Toggle::make('frontend_creation.enabled')
                        ->label(__('game-tables::messages.settings.fields.frontend_creation_enabled'))
                        ->helperText(__('game-tables::messages.settings.fields.frontend_creation_enabled_help'))
                        ->live(),

                    Select::make('frontend_creation.allowed_content')
                        ->label(__('game-tables::messages.settings.fields.allowed_content'))
                        ->options([
                            'tables' => __('game-tables::messages.settings.allowed_content.tables'),
                            'campaigns' => __('game-tables::messages.settings.allowed_content.campaigns'),
                            'both' => __('game-tables::messages.settings.allowed_content.both'),
                        ])
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('frontend_creation.enabled')),

                    Select::make('frontend_creation.access_level')
                        ->label(__('game-tables::messages.settings.fields.access_level'))
                        ->helperText(__('game-tables::messages.settings.fields.access_level_help'))
                        ->options([
                            'everyone' => __('game-tables::messages.settings.access_level.everyone'),
                            'registered' => __('game-tables::messages.settings.access_level.registered'),
                            'role' => __('game-tables::messages.settings.access_level.role'),
                            'permission' => __('game-tables::messages.settings.access_level.permission'),
                        ])
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => (bool) $get('frontend_creation.enabled')),

                    TextInput::make('frontend_creation.allowed_roles')
                        ->label(__('game-tables::messages.settings.fields.allowed_roles'))
                        ->helperText(__('game-tables::messages.settings.fields.allowed_roles_help'))
                        ->placeholder('socio, colaborador')
                        ->visible(fn (Get $get): bool =>
                            (bool) $get('frontend_creation.enabled') &&
                            $get('frontend_creation.access_level') === 'role'
                        ),

                    TextInput::make('frontend_creation.required_permission')
                        ->label(__('game-tables::messages.settings.fields.required_permission'))
                        ->helperText(__('game-tables::messages.settings.fields.required_permission_help'))
                        ->placeholder('gametables:create')
                        ->visible(fn (Get $get): bool =>
                            (bool) $get('frontend_creation.enabled') &&
                            $get('frontend_creation.access_level') === 'permission'
                        ),

                    Select::make('frontend_creation.publication.mode')
                        ->label(__('game-tables::messages.settings.fields.publication_mode'))
                        ->helperText(__('game-tables::messages.settings.fields.publication_mode_help'))
                        ->options([
                            'auto' => __('game-tables::messages.settings.publication_mode.auto'),
                            'approval' => __('game-tables::messages.settings.publication_mode.approval'),
                            'role_based' => __('game-tables::messages.settings.publication_mode.role_based'),
                        ])
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('frontend_creation.enabled')),
                ])
                ->columns(2)
                ->collapsible(),
        ];
    }

    /**
     * Refuses a value above the one held by another field of the same submission (not the stored
     * one, which this save may change). A non-numeric bound is left to that field's own rules.
     */
    private static function notAbove(string $boundPath, string $messageKey): Closure
    {
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $boundPath, $messageKey): void {
            $bound = $get($boundPath);

            if (! is_numeric($value) || ! is_numeric($bound) || (float) $value <= (float) $bound) {
                return;
            }

            $fail(__('game-tables::messages.settings.validation.'.$messageKey, ['limit' => (int) $bound]));
        };
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchemaComponents())
            ->statePath('data');
    }

    public function save(ModuleManagerServiceInterface $moduleManager): void
    {
        // Livewire methods can be called without the page's button
        abort_unless(self::canAccess(), 403);

        $formData = $this->form->getState();

        $moduleManager->updateSettings(new ModuleName('game-tables'), $formData);

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
