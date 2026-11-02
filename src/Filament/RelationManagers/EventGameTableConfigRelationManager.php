<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\RelationManagers;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Forms\Components\TimePicker;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;

/**
 * @property Schema $configForm
 */
final class EventGameTableConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'gameTableConfig';

    protected static ?string $recordTitleAttribute = 'event_id';

    // The module registers its view namespace at runtime, so Larastan cannot resolve it as a view-string
    protected string $view = 'game-tables::filament.relation-managers.game-table-config-form'; // @phpstan-ignore property.defaultValue

    /** @var array<string, mixed> */
    public array $configData = [];

    /**
     * Days of stored slots that fall outside the event (moved after they were saved): offered too,
     * so saving does not move those slots without the admin seeing it.
     *
     * @var list<string>
     */
    #[Locked]
    public array $slotDaysOutsideEvent = [];

    public static function getTitle(Model $ownerRecord, string $pageClass): string
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
        $timezone = app(TimezoneServiceInterface::class);
        $slotDay = static fn (TimeSlotDefinition $slot): string => $timezone->toDisplayWallTime($slot->startTime, 'Y-m-d');
        $this->slotDaysOutsideEvent = array_values(array_diff(
            array_unique(array_map($slotDay, $config->timeSlots())),
            $this->eventDays(),
        ));

        $this->configForm->fill([
            'tables_enabled' => $config->isEnabled(),
            'scheduling_mode' => $config->schedulingMode()->value,
            // The day and hours the admin typed: the site's calendar and wall time
            'time_slots' => array_map(fn (TimeSlotDefinition $slot): array => [
                'label' => $slot->label,
                'day' => $slotDay($slot),
                'start_time' => $timezone->toDisplayWallTime($slot->startTime, 'H:i'),
                'end_time' => $timezone->toDisplayWallTime($slot->endTime, 'H:i'),
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
            // The picker reads a storage-zone value and shows it in the site's zone
            'creation_opens_at' => $config->creationOpensAt() !== null ? $timezone->toStorage($config->creationOpensAt())->format('Y-m-d H:i:s') : null,
            'early_access_type' => $earlyAccessTier?->accessType->value,
            'early_access_roles' => $earlyAccessTier?->allowedRoles,
            'early_access_permission' => $earlyAccessTier?->requiredPermission,
            'early_access_days_before' => $earlyAccessTier?->daysBeforeOpening,
        ]);
    }

    public function configForm(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('configData');
    }

    /**
     * @return array<Component>
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
                            Select::make('day')
                                ->label(__('game-tables::messages.event_config.time_slot_day'))
                                ->options(fn (): array => $this->slotDayOptions())
                                ->in(fn (): array => array_keys($this->slotDayOptions()))
                                ->default(fn (): string => $this->eventDays()[0])
                                ->selectablePlaceholder(false)
                                ->native(false)
                                ->visible(fn (): bool => $this->isMultiDayEvent()),
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
                        ->columns(fn (): int => $this->isMultiDayEvent() ? 6 : 5)
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
        $eventId = $this->getOwnerRecord()->getKey();
        $service = app(EventGameTableConfigServiceInterface::class);

        // Check raw Livewire state BEFORE getState() to avoid Filament
        // processing hidden fields whose state keys were already removed
        if (empty($this->configData['tables_enabled'])) {
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

        // tables_enabled is true — all fieldsets are visible, getState() is safe
        $formData = $this->configForm->getState();

        // Build eligibility override if enabled
        $eligibilityOverride = null;
        if (! empty($formData['eligibility_override_enabled']) && ! empty($formData['eligibility_access_level'])) {
            $eligibilityOverride = [
                'access_level' => $formData['eligibility_access_level'],
                'allowed_roles' => $formData['eligibility_allowed_roles'] ?? null,
                'required_permission' => $formData['eligibility_required_permission'] ?? null,
            ];
        }

        // Build time slots with full datetime (using the chosen day of the event as reference)
        $timeSlots = [];
        if ($formData['scheduling_mode'] === SchedulingMode::SlotBased->value && ! empty($formData['time_slots'])) {
            // The hours are the site's wall time on the slot's day there (the event's first day
            // unless it spans several), saved as instants with their offset
            $timezone = app(TimezoneServiceInterface::class);
            $firstDay = $this->eventDays()[0];
            $choosesDay = $this->isMultiDayEvent();
            foreach ($formData['time_slots'] as $slot) {
                if (empty($slot['label']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                    continue;
                }
                $day = $choosesDay && ! empty($slot['day']) ? (string) $slot['day'] : $firstDay;
                $start = $timezone->fromDisplayWallTime($day.' '.$slot['start_time']);
                $end = $timezone->fromDisplayWallTime($day.' '.$slot['end_time']);
                // An end hour not after the start hour is on the next day: the slot crosses midnight
                if ($end <= $start) {
                    $end = $timezone->fromDisplayWallTime($this->nextDay($day).' '.$slot['end_time']);
                }
                $timeSlots[] = [
                    'label' => $slot['label'],
                    'start_time' => $timezone->iso($start),
                    'end_time' => $timezone->iso($end),
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
     * Days of the event in the site's calendar (`Y-m-d`), from the day it starts to the day it ends.
     *
     * @return non-empty-list<string>
     */
    private function eventDays(): array
    {
        $timezone = app(TimezoneServiceInterface::class);
        $event = $this->getOwnerRecord();
        $start = $event->getAttribute('start_date');
        $end = $event->getAttribute('end_date');

        $first = $start instanceof DateTimeInterface
            ? $timezone->toDisplayWallTime($start, 'Y-m-d')
            : $timezone->today()->format('Y-m-d');
        $last = $end instanceof DateTimeInterface ? $timezone->toDisplayWallTime($end, 'Y-m-d') : $first;

        $days = [$first];
        for ($day = $this->nextDay($first); $day <= $last; $day = $this->nextDay($day)) {
            $days[] = $day;
        }

        return $days;
    }

    private function isMultiDayEvent(): bool
    {
        return count($this->eventDays()) > 1;
    }

    /**
     * The event's days, plus those of stored slots left outside it, named in the site's language.
     *
     * @return array<string, string>
     */
    private function slotDayOptions(): array
    {
        $days = array_unique([...$this->eventDays(), ...$this->slotDaysOutsideEvent]);
        sort($days);

        $options = [];
        foreach ($days as $day) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
            $options[$day] = $date instanceof CarbonImmutable
                ? Str::ucfirst($date->settings(['locale' => app()->getLocale()])->isoFormat('dddd LL'))
                : $day;
        }

        return $options;
    }

    /** The calendar day after a `Y-m-d` day, counted on dates alone so no clock change gets in the way. */
    private function nextDay(string $day): string
    {
        return (new DateTimeImmutable($day, new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
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
