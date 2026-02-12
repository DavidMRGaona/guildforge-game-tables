<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\RelationManagers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;

/**
 * @property Form $configForm
 */
final class EventGameTableConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'gameTableConfig';

    protected static ?string $recordTitleAttribute = 'event_id';

    protected static string $view = 'game-tables::filament.relation-managers.game-table-config-form';

    /** @var array<string, mixed> */
    public array $configData = [];

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.event_config.title');
    }

    /**
     * Always show the config tab so admins can configure game tables.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof EventModel;
    }

    public function mount(): void
    {
        parent::mount();

        $eventId = $this->getOwnerRecord()->getKey();
        $configRepository = app(EventGameTableConfigRepositoryInterface::class);
        $config = $configRepository->findByEventOrDefault($eventId);

        $earlyAccessTier = $config->earlyAccessTier();

        $this->configForm->fill([
            'tables_enabled' => $config->isEnabled(),
            'scheduling_mode' => $config->schedulingMode()->value,
            'time_slots' => array_map(fn ($slot): array => [
                'label' => $slot->label,
                'start_time' => $slot->startTime->format('H:i'),
                'end_time' => $slot->endTime->format('H:i'),
                'max_tables' => $slot->maxTables,
            ], $config->timeSlots()),
            'location_mode' => $config->locationMode()->value,
            'fixed_location' => $config->fixedLocation(),
            'eligibility_override_enabled' => $config->hasEligibilityOverride(),
            'eligibility_access_level' => $config->eligibilityOverride()?->accessLevel->value,
            'eligibility_allowed_roles' => $config->eligibilityOverride()?->allowedRoles,
            'eligibility_required_permission' => $config->eligibilityOverride()?->requiredPermission,
            // Early access fields
            'early_access_enabled' => $config->isEarlyAccessEnabled(),
            'creation_opens_at' => $config->creationOpensAt()?->format('Y-m-d H:i:s'),
            'early_access_type' => $earlyAccessTier?->accessType->value,
            'early_access_roles' => $earlyAccessTier?->allowedRoles,
            'early_access_permission' => $earlyAccessTier?->requiredPermission,
            'early_access_days_before' => $earlyAccessTier?->daysBeforeOpening,
        ]);
    }

    /**
     * @return array<string, Form>
     */
    protected function getForms(): array
    {
        return [
            'configForm' => $this->makeForm()
                ->schema($this->getFormSchema())
                ->statePath('configData'),
        ];
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make(__('game-tables::messages.event_config.general'))
                ->schema([
                    Toggle::make('tables_enabled')
                        ->label(__('game-tables::messages.event_config.tables_enabled'))
                        ->helperText(__('game-tables::messages.event_config.tables_enabled_help'))
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),
                ]),

            Fieldset::make(__('game-tables::messages.event_config.scheduling'))
                ->schema([
                    Select::make('scheduling_mode')
                        ->label(__('game-tables::messages.event_config.scheduling_mode'))
                        ->options(SchedulingMode::options())
                        ->default(SchedulingMode::FreeSchedule->value)
                        ->native(false)
                        ->live()
                        ->required(),

                    Repeater::make('time_slots')
                        ->label(__('game-tables::messages.event_config.time_slots'))
                        ->schema([
                            TextInput::make('label')
                                ->label(__('game-tables::messages.event_config.time_slot_label'))
                                ->required()
                                ->maxLength(50)
                                ->columnSpan(2),
                            TimePicker::make('start_time')
                                ->label(__('game-tables::messages.event_config.start_time'))
                                ->seconds(false)
                                ->required(),
                            TimePicker::make('end_time')
                                ->label(__('game-tables::messages.event_config.end_time'))
                                ->seconds(false)
                                ->required(),
                            TextInput::make('max_tables')
                                ->label(__('game-tables::messages.event_config.max_tables'))
                                ->numeric()
                                ->minValue(1)
                                ->nullable(),
                        ])
                        ->columns(5)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->visible(fn (Get $get): bool => $get('scheduling_mode') === SchedulingMode::SlotBased->value)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->visible(fn (Get $get): bool => (bool) $get('tables_enabled')),

            Fieldset::make(__('game-tables::messages.event_config.location'))
                ->schema([
                    Select::make('location_mode')
                        ->label(__('game-tables::messages.event_config.location_mode'))
                        ->options(LocationMode::options())
                        ->default(LocationMode::FreeChoice->value)
                        ->native(false)
                        ->live()
                        ->required(),

                    TextInput::make('fixed_location')
                        ->label(__('game-tables::messages.event_config.fixed_location'))
                        ->helperText(__('game-tables::messages.event_config.fixed_location_help'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('location_mode') === LocationMode::FixedLocation->value)
                        ->required(fn (Get $get): bool => $get('location_mode') === LocationMode::FixedLocation->value),
                ])
                ->columns(2)
                ->visible(fn (Get $get): bool => (bool) $get('tables_enabled')),

            Fieldset::make(__('game-tables::messages.event_config.table_creation'))
                ->schema([
                    DateTimePicker::make('creation_opens_at')
                        ->label(__('game-tables::messages.event_config.creation_opens_at'))
                        ->helperText(__('game-tables::messages.event_config.creation_opens_at_help'))
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->required(),
                ])
                ->visible(fn (Get $get): bool => (bool) $get('tables_enabled')),

            Section::make(__('game-tables::messages.event_config.eligibility_override'))
                ->description(__('game-tables::messages.event_config.eligibility_override_description'))
                ->schema([
                    Toggle::make('eligibility_override_enabled')
                        ->label(__('game-tables::messages.event_config.eligibility_override_enabled'))
                        ->helperText(__('game-tables::messages.event_config.eligibility_override_enabled_help'))
                        ->live(),

                    Select::make('eligibility_access_level')
                        ->label(__('game-tables::messages.event_config.eligibility_access_level'))
                        ->options(CreationAccessLevel::options())
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => (bool) $get('eligibility_override_enabled'))
                        ->required(fn (Get $get): bool => (bool) $get('eligibility_override_enabled')),

                    Select::make('eligibility_allowed_roles')
                        ->label(__('game-tables::messages.event_config.eligibility_allowed_roles'))
                        ->multiple()
                        ->options($this->getRoleOptions())
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('eligibility_override_enabled')
                            && $get('eligibility_access_level') === CreationAccessLevel::Role->value)
                        ->required(fn (Get $get): bool => $get('eligibility_access_level') === CreationAccessLevel::Role->value),

                    TextInput::make('eligibility_required_permission')
                        ->label(__('game-tables::messages.event_config.eligibility_required_permission'))
                        ->helperText(__('game-tables::messages.event_config.eligibility_required_permission_help'))
                        ->visible(fn (Get $get): bool => (bool) $get('eligibility_override_enabled')
                            && $get('eligibility_access_level') === CreationAccessLevel::Permission->value)
                        ->required(fn (Get $get): bool => $get('eligibility_access_level') === CreationAccessLevel::Permission->value),
                ])
                ->collapsed()
                ->visible(fn (Get $get): bool => (bool) $get('tables_enabled')),

            Section::make(__('game-tables::messages.event_config.early_access'))
                ->description(__('game-tables::messages.event_config.early_access_description'))
                ->schema([
                    Toggle::make('early_access_enabled')
                        ->label(__('game-tables::messages.event_config.early_access_enabled'))
                        ->helperText(__('game-tables::messages.event_config.early_access_enabled_help'))
                        ->live(),

                    Select::make('early_access_type')
                        ->label(__('game-tables::messages.event_config.early_access_type'))
                        ->options([
                            CreationAccessLevel::Role->value => __('game-tables::messages.enums.creation_access_level.role'),
                            CreationAccessLevel::Permission->value => __('game-tables::messages.enums.creation_access_level.permission'),
                        ])
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => (bool) $get('early_access_enabled')),

                    Select::make('early_access_roles')
                        ->label(__('game-tables::messages.event_config.early_access_roles'))
                        ->helperText(__('game-tables::messages.event_config.early_access_roles_help'))
                        ->multiple()
                        ->options($this->getRoleOptions())
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('early_access_enabled')
                            && $get('early_access_type') === CreationAccessLevel::Role->value)
                        ->required(fn (Get $get): bool => $get('early_access_type') === CreationAccessLevel::Role->value),

                    TextInput::make('early_access_permission')
                        ->label(__('game-tables::messages.event_config.early_access_permission'))
                        ->helperText(__('game-tables::messages.event_config.early_access_permission_help'))
                        ->visible(fn (Get $get): bool => (bool) $get('early_access_enabled')
                            && $get('early_access_type') === CreationAccessLevel::Permission->value)
                        ->required(fn (Get $get): bool => $get('early_access_type') === CreationAccessLevel::Permission->value),

                    TextInput::make('early_access_days_before')
                        ->label(__('game-tables::messages.event_config.early_access_days_before'))
                        ->helperText(__('game-tables::messages.event_config.early_access_days_before_help'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(30)
                        ->default(3)
                        ->visible(fn (Get $get): bool => (bool) $get('early_access_enabled')
                            && $get('early_access_type') !== null)
                        ->required(fn (Get $get): bool => (bool) $get('early_access_enabled')
                            && $get('early_access_type') !== null),
                ])
                ->collapsed()
                ->visible(fn (Get $get): bool => (bool) $get('tables_enabled')),
        ];
    }

    public function save(): void
    {
        $formData = $this->configForm->getState();
        $eventId = $this->getOwnerRecord()->getKey();
        $service = app(EventGameTableConfigServiceInterface::class);

        if (empty($formData['tables_enabled'])) {
            $service->updateConfig(UpdateEventGameTableConfigDTO::fromArray([
                'event_id' => $eventId,
                'tables_enabled' => false,
                'scheduling_mode' => SchedulingMode::FreeSchedule->value,
                'time_slots' => [],
                'location_mode' => LocationMode::FreeChoice->value,
                'fixed_location' => null,
                'eligibility_override' => null,
                'early_access_enabled' => false,
                'creation_opens_at' => null,
                'early_access_tier' => null,
            ]));

            Notification::make()
                ->title(__('game-tables::messages.event_config.saved'))
                ->success()
                ->send();

            return;
        }

        $event = $this->getOwnerRecord();

        // Build eligibility override if enabled
        $eligibilityOverride = null;
        if (! empty($formData['eligibility_override_enabled']) && ! empty($formData['eligibility_access_level'])) {
            $eligibilityOverride = [
                'access_level' => $formData['eligibility_access_level'],
                'allowed_roles' => $formData['eligibility_allowed_roles'] ?? null,
                'required_permission' => $formData['eligibility_required_permission'] ?? null,
            ];
        }

        // Build time slots with full datetime (using event date as reference)
        $timeSlots = [];
        if ($formData['scheduling_mode'] === SchedulingMode::SlotBased->value && ! empty($formData['time_slots'])) {
            $eventDate = $event->start_date?->format('Y-m-d') ?? date('Y-m-d');
            foreach ($formData['time_slots'] as $slot) {
                if (empty($slot['label']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                    continue;
                }
                $timeSlots[] = [
                    'label' => $slot['label'],
                    'start_time' => $eventDate . 'T' . $slot['start_time'] . ':00',
                    'end_time' => $eventDate . 'T' . $slot['end_time'] . ':00',
                    'max_tables' => $slot['max_tables'] ?? null,
                ];
            }
        }

        // Build early access tier if enabled and configured
        $earlyAccessTier = null;
        if (! empty($formData['early_access_enabled'])
            && ! empty($formData['early_access_type'])
            && ! empty($formData['early_access_days_before'])) {
            $earlyAccessTier = [
                'access_type' => $formData['early_access_type'],
                'allowed_roles' => $formData['early_access_roles'] ?? null,
                'required_permission' => $formData['early_access_permission'] ?? null,
                'days_before_opening' => (int) $formData['early_access_days_before'],
            ];
        }

        $service->updateConfig(UpdateEventGameTableConfigDTO::fromArray([
            'event_id' => $eventId,
            'tables_enabled' => true,
            'scheduling_mode' => $formData['scheduling_mode'],
            'time_slots' => $timeSlots,
            'location_mode' => $formData['location_mode'],
            'fixed_location' => $formData['fixed_location'] ?? null,
            'eligibility_override' => $eligibilityOverride,
            'early_access_enabled' => $formData['early_access_enabled'] ?? false,
            'creation_opens_at' => $formData['creation_opens_at'] ?? null,
            'early_access_tier' => $earlyAccessTier,
        ]));

        Notification::make()
            ->title(__('game-tables::messages.event_config.saved'))
            ->success()
            ->send();
    }

    /**
     * Get available roles for the dropdown.
     *
     * @return array<string, string>
     */
    private function getRoleOptions(): array
    {
        return RoleModel::query()
            ->orderBy('name')
            ->pluck('display_name', 'name')
            ->all();
    }
}
