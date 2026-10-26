<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Time;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Illuminate\Testing\PendingCommand;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterQueryInterface;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventCreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Console\Commands\SendTableRemindersCommand;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Events\ParticipantRejected;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;
use Modules\GameTables\Filament\RelationManagers\EventGameTableConfigRelationManager;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Infrastructure\Listeners\CancelParticipantsOnTableCancellation;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnCancellation;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnGuestRegistration;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnRegistration;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnWaitingListPromotion;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Listeners\SendCampaignInvitation;
use Modules\GameTables\Listeners\SendCampaignSessionScheduled;
use Modules\GameTables\Listeners\SendCancellationConfirmation;
use Modules\GameTables\Listeners\SendConfirmationNotification;
use Modules\GameTables\Listeners\SendGuestRegistrationConfirmation;
use Modules\GameTables\Listeners\SendRegistrationConfirmation;
use Modules\GameTables\Listeners\SendRejectionNotification;
use Modules\GameTables\Listeners\SendRosterMemberAdded;
use Modules\GameTables\Notifications\CampaignInvitationNotification;
use Modules\GameTables\Notifications\CampaignRosterAddedNotification;
use Modules\GameTables\Notifications\CampaignSessionScheduledNotification;
use Modules\GameTables\Notifications\CancellationConfirmation;
use Modules\GameTables\Notifications\GuestCancellationConfirmation;
use Modules\GameTables\Notifications\GuestEmailConfirmationRequest;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;
use Modules\GameTables\Notifications\OrganizationCancellationNotification;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;
use Modules\GameTables\Notifications\ParticipantConfirmedNotification;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Modules\GameTables\Notifications\ParticipantRejectedNotification;
use Modules\GameTables\Notifications\RegistrationConfirmation;
use Modules\GameTables\Notifications\TableCancelledNotification;
use Modules\GameTables\Notifications\TableReminderNotification;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * With UTC storage the instants in the database are UTC, but whatever a person types or reads (the
 * web forms, the panel, the JSON) is the site's Madrid wall time, and calendar days are Madrid days.
 */
final class UtcStorageTest extends ModuleTestCase
{
    use InteractsWithModulePanel;
    use UsesUtcStorage;

    /** 12:00 in Madrid (CEST). */
    private const string TABLE_START = '2026-07-01T10:00:00Z';

    private const string MADRID_TIME = '01/07/2026 12:00';

    private const string MADRID_ISO = '2026-07-01T12:00:00+02:00';

    private ?GameSystemModel $fixtureSystem = null;

    private ?UserModel $fixtureCreator = null;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        app()->setLocale('es');
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
            ],
        ]);
    }

    public function test_every_model_stores_instants_in_the_storage_timezone(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Infrastructure/Persistence/Eloquent/Models/*.php') ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'Modules\\GameTables\\Infrastructure\\Persistence\\Eloquent\\Models\\'.basename($file, '.php');

            $this->assertContains(StoresInstantsInStorageTimezone::class, class_uses_recursive($class), $class);
        }
    }

    public function test_the_table_start_is_stored_in_utc_and_serialized_in_the_display_zone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01T08:00:00Z'));
        $table = $this->table(['registration_opens_at' => CarbonImmutable::parse('2026-06-20T08:00:00Z')]);

        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_tables', $table->id, 'starts_at'));

        $this->get(route('gametables.show', $table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.startsAt', self::MADRID_ISO)
                ->where('table.registrationOpensAt', '2026-06-20T10:00:00+02:00'));

        $this->get(route('gametables.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tables.data.0.startsAt', self::MADRID_ISO));
    }

    public function test_the_edit_page_prefills_the_start_with_the_madrid_wall_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01T08:00:00Z'));
        $table = $this->table(['status' => 'draft', 'is_published' => false, 'frontend_creation_status' => 'draft']);

        $this->actingAs($this->creator())
            ->get(route('gametables.edit', $table))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('table.starts_at', self::MADRID_ISO)
                ->where('startsAtWallTime', '2026-07-01T12:00'));
    }

    public function test_a_start_typed_in_the_web_form_is_read_as_madrid_wall_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01T08:00:00Z'));

        $this->actingAs($this->creator())
            ->post(route('gametables.store'), $this->tableForm('2026-07-01T12:00'))
            ->assertSessionHasNoErrors();

        $table = GameTableModel::query()->where('title', 'Mesa desde la web')->sole();
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_tables', $table->id, 'starts_at'));
    }

    public function test_a_start_sent_with_its_offset_is_kept_as_that_instant(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01T08:00:00Z'));

        $this->actingAs($this->creator())
            ->post(route('gametables.store'), $this->tableForm(self::MADRID_ISO))
            ->assertSessionHasNoErrors();

        $table = GameTableModel::query()->where('title', 'Mesa desde la web')->sole();
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_tables', $table->id, 'starts_at'));
    }

    public function test_the_typed_start_must_be_later_than_now_in_madrid_time(): void
    {
        // 11:30 in Madrid
        $this->travelTo(CarbonImmutable::parse('2026-07-01T09:30:00Z'));

        $this->actingAs($this->creator())
            ->post(route('gametables.store'), $this->tableForm('2026-07-01T11:00'))
            ->assertSessionHasErrors(['starts_at' => __('game-tables::messages.validation.starts_at_future')]);

        $this->actingAs($this->creator())
            ->post(route('gametables.store'), $this->tableForm('2026-07-01T12:00'))
            ->assertSessionHasNoErrors();
    }

    public function test_the_create_form_offers_the_event_start_as_madrid_wall_time(): void
    {
        // The form offers the events not yet ended by the system clock, which travelTo leaves alone
        $event = $this->eventWithTables(start: '2030-07-01T16:00:00Z');

        $this->actingAs($this->creator())
            ->get(route('gametables.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('formData.events', fn (Collection $events): bool => $events->contains(
                    fn (array $offered): bool => $offered['id'] === $event->id && $offered['start_date'] === '2030-07-01T18:00',
                )));
    }

    public function test_the_event_slots_keep_their_madrid_hours(): void
    {
        $this->actingAsPanelAdmin();
        $event = $this->eventWithTables(['scheduling_mode' => 'free']);

        Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.tables_enabled', true)
            ->set('configData.scheduling_mode', SchedulingMode::SlotBased->value)
            ->set('configData.time_slots', ['slot' => ['label' => 'Tarde', 'start_time' => '18:00', 'end_time' => '22:00', 'max_tables' => null]])
            ->set('configData.creation_opens_at', '2026-06-01 10:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $slots = EventGameTableConfigModel::query()->findOrFail($event->id)->time_slots ?? [];
        $this->assertCount(1, $slots);
        // 18:00 in Madrid on the event day, whatever zone the string is read in
        $this->assertSame('2026-07-01T16:00:00+00:00', (new DateTimeImmutable($slots[0]['start_time']))->setTimezone(new DateTimeZone('UTC'))->format('c'));

        Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertSet('configData.time_slots', fn (array $slots): bool => array_first($slots)['start_time'] === '18:00'
                && array_first($slots)['end_time'] === '22:00');

        $context = app(EventGameTableConfigServiceInterface::class)->getCreationContext($event->id)->toArray();
        $this->assertSame('2026-07-01T18:00:00+02:00', $context['available_time_slots'][0]['start_time']);
        $this->assertSame('2026-07-01T22:00:00+02:00', $context['available_time_slots'][0]['end_time']);
    }

    public function test_the_slots_take_the_day_of_the_event_in_madrid(): void
    {
        $this->actingAsPanelAdmin();
        // 00:30 on the 1st of July in Madrid, still the 30th of June in UTC
        $event = $this->eventWithTables(start: '2026-06-30T22:30:00Z');

        Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.tables_enabled', true)
            ->set('configData.scheduling_mode', SchedulingMode::SlotBased->value)
            ->set('configData.time_slots', ['slot' => ['label' => 'Tarde', 'start_time' => '18:00', 'end_time' => '22:00', 'max_tables' => null]])
            ->set('configData.creation_opens_at', '2026-06-01 10:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $context = app(EventGameTableConfigServiceInterface::class)->getCreationContext($event->id)->toArray();
        $this->assertSame('2026-07-01T18:00:00+02:00', $context['available_time_slots'][0]['start_time']);
        $this->assertSame('2026-07-01T22:00:00+02:00', $context['available_time_slots'][0]['end_time']);
    }

    public function test_a_slot_saved_without_offset_is_read_as_madrid_wall_time(): void
    {
        $event = $this->eventWithTables();
        // Slots saved before UTC storage: wall times of the site with no offset
        EventGameTableConfigModel::query()->whereKey($event->id)->update(['scheduling_mode' => SchedulingMode::SlotBased->value, 'time_slots' => json_encode([
            ['label' => 'Tarde', 'start_time' => '2026-07-01T18:00:00', 'end_time' => '2026-07-01T22:00:00', 'max_tables' => null],
        ])]);

        $context = app(EventGameTableConfigServiceInterface::class)->getCreationContext($event->id)->toArray();

        $this->assertSame('2026-07-01T18:00:00+02:00', $context['available_time_slots'][0]['start_time']);
    }

    public function test_a_slot_hour_given_without_offset_is_read_as_madrid_wall_time(): void
    {
        $dto = UpdateEventGameTableConfigDTO::fromArray([
            'event_id' => (string) Str::uuid(),
            'time_slots' => [['label' => 'Tarde', 'start_time' => '2026-07-01T18:00:00', 'end_time' => '2026-07-01T22:00:00+02:00']],
        ]);

        $this->assertSame('2026-07-01T16:00:00+00:00', $dto->timeSlots[0]->startTime->setTimezone(new DateTimeZone('UTC'))->format('c'));
        $this->assertSame('2026-07-01T20:00:00+00:00', $dto->timeSlots[0]->endTime->setTimezone(new DateTimeZone('UTC'))->format('c'));
    }

    /**
     * The start sent by the form is matched to a slot, and the tables of that slot counted, by
     * instant: the slot written with its offset, the stored UTC start and a Madrid wall time agree.
     */
    public function test_the_event_slot_and_its_limit_are_checked_by_instant(): void
    {
        // The form offers the events not yet ended by the system clock
        $event = $this->eventWithTables([
            'scheduling_mode' => SchedulingMode::SlotBased->value,
            'time_slots' => [
                ['label' => 'Tarde', 'start_time' => '2030-07-01T18:00:00+02:00', 'end_time' => '2030-07-01T22:00:00+02:00', 'max_tables' => 1],
            ],
        ], start: '2030-07-01T08:00:00Z');

        $this->actingAs($this->creator())
            ->post(route('gametables.store'), [...$this->tableForm('2030-07-01T18:00'), 'event_id' => $event->id])
            ->assertSessionHasNoErrors();
        $table = GameTableModel::query()->where('title', 'Mesa desde la web')->sole();
        $this->assertSame('2030-07-01 16:00:00', $this->storedValue('gametables_tables', $table->id, 'starts_at'));

        $this->actingAs($this->creator())
            ->post(route('gametables.store'), [...$this->tableForm('2030-07-01T16:00:00Z'), 'event_id' => $event->id])
            ->assertSessionHasErrors(['starts_at' => __('game-tables::messages.validation.time_slot_full', ['slot' => 'Tarde'])]);
        // 16:00 in Madrid is no slot of the event, though it is the stored hour
        $this->actingAs($this->creator())
            ->post(route('gametables.store'), [...$this->tableForm('2030-07-01T16:00'), 'event_id' => $event->id])
            ->assertSessionHasErrors(['starts_at' => __('game-tables::messages.validation.starts_at_not_a_slot')]);

        $context = app(EventGameTableConfigServiceInterface::class)->getCreationContext($event->id)->toArray();
        $this->assertSame(0, $context['available_time_slots'][0]['available_slots']);
    }

    public function test_the_config_tab_shows_the_creation_opening_in_madrid_and_saves_the_utc_instant(): void
    {
        $this->actingAsPanelAdmin();
        $event = $this->eventWithTables(['creation_opens_at' => CarbonImmutable::parse('2026-06-01T08:00:00Z')]);

        Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertSet('configData.creation_opens_at', '2026-06-01 10:00:00')
            ->set('configData.creation_opens_at', '2026-06-02 12:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2026-06-02 10:00:00', $this->storedValue('game_tables_event_configs', $event->id, 'creation_opens_at', 'event_id'));

        $context = app(EventGameTableConfigServiceInterface::class)->getCreationContext($event->id)->toArray();
        $this->assertSame('2026-06-02T12:00:00+02:00', $context['creation_opens_at']);
        $this->assertSame('2026-07-01T18:00:00+02:00', $context['event_start_date']);
    }

    public function test_early_creation_opens_seven_madrid_days_before(): void
    {
        $editor = $this->creator();
        $this->grantRole($editor, 'editor');
        // 10:00 in Madrid (CET); seven days earlier is 10:00 CEST, before the clocks go back
        $event = $this->eventWithTables([
            'creation_opens_at' => CarbonImmutable::parse('2026-10-30T09:00:00Z'),
            'early_access_enabled' => true,
            'early_access_tier' => ['access_type' => 'role', 'allowed_roles' => ['editor'], 'required_permission' => null, 'days_before_opening' => 7],
        ], start: '2026-11-07T17:00:00Z');
        $service = app(EventCreationEligibilityServiceInterface::class);

        $this->travelTo(CarbonImmutable::parse('2026-10-23T07:59:00Z'));
        $eligibility = $service->canCreateTableForEvent($event->id, $editor->id);
        $this->assertFalse($eligibility->eligible);
        $this->assertSame('2026-10-23T10:00:00+02:00', $eligibility->toArray()['can_create_at']);
        $this->assertSame('2026-10-30T10:00:00+01:00', $eligibility->toArray()['public_open_date']);

        $context = app(EventGameTableConfigServiceInterface::class)->getCreationContext($event->id)->toArray();
        $this->assertSame('2026-10-23T10:00:00+02:00', $context['early_access_opens_at']);

        $this->actingAs($editor)
            ->get(route('gametables.create', ['event' => $event->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/CreateNotEligible', false)
                ->where('canCreateAt', '2026-10-23T10:00:00+02:00'));

        $this->travelTo(CarbonImmutable::parse('2026-10-23T08:00:00Z'));
        $this->assertNotSame('2026-10-23T10:00:00+02:00', $service->canCreateTableForEvent($event->id, $editor->id)->toArray()['can_create_at']);
    }

    public function test_the_creation_priority_counts_madrid_days_before_the_event(): void
    {
        $editor = $this->creator();
        $this->grantRole($editor, 'editor');
        config()->set('modules.settings.game-tables.frontend_creation.priority_tiers', [
            ['tier' => 1, 'type' => 'role', 'value' => 'editor', 'days_before' => 7],
        ]);

        $opensAt = app(CreationEligibilityServiceInterface::class)->getCreationOpenDate($editor->id, new DateTimeImmutable('2026-10-30T09:00:00Z'));
        $this->assertNotNull($opensAt);

        $this->assertSame('2026-10-23T08:00:00+00:00', $opensAt->setTimezone(new DateTimeZone('UTC'))->format('c'));
    }

    public function test_members_early_access_counts_madrid_days(): void
    {
        $member = $this->creator();
        $this->actAsMember($member);
        // Opens at 10:00 in Madrid (CET); three days earlier it is 10:00 CEST
        $table = $this->table([
            'starts_at' => CarbonImmutable::parse('2026-11-04T17:00:00Z'),
            'registration_opens_at' => CarbonImmutable::parse('2026-10-27T09:00:00Z'),
            'members_early_access_days' => 3,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-24T07:59:00Z'));
        $this->actingAs($member)
            ->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.eligible', false)
                ->where('eligibility.canRegisterAt', '2026-10-24T10:00:00+02:00'));

        $this->travelTo(CarbonImmutable::parse('2026-10-24T08:00:00Z'));
        $this->actingAs($member)
            ->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('eligibility.eligible', true));
    }

    public function test_the_calendar_month_is_the_madrid_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01T08:00:00Z'));
        // 00:30 on the 1st of July in Madrid, and 00:30 on the 1st of August
        $july = $this->table(['starts_at' => CarbonImmutable::parse('2026-06-30T22:30:00Z')]);
        $this->table(['starts_at' => CarbonImmutable::parse('2026-07-31T22:30:00Z')]);

        $this->get(route('gametables.calendar', ['month' => '2026-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentMonth', '2026-07')
                ->where('tables', fn (Collection $tables): bool => $tables->pluck('id')->all() === [$july->id]));
    }

    public function test_the_calendar_opens_on_the_current_madrid_month(): void
    {
        // Already July in Madrid
        $this->travelTo(CarbonImmutable::parse('2026-06-30T22:30:00Z'));

        $this->get(route('gametables.calendar'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('currentMonth', '2026-07'));
    }

    public function test_the_tables_that_have_started_are_found_by_instant(): void
    {
        // With libfaketime at 00:10Z on 25 October (02:10 CEST) the two starts are 02:05 CEST and
        // 02:05 CET: the same wall time, five minutes ago and in 55 minutes
        $now = CarbonImmutable::now();
        $started = $this->table(['starts_at' => $now->subMinutes(5)]);
        $this->table(['starts_at' => $now->addMinutes(55)]);

        $ids = app(GameTableRepositoryInterface::class)->idsStartedInStatuses([TableStatus::Scheduled], $now->toDateTimeImmutable());

        $this->assertSame([$started->id], $ids);
    }

    public function test_the_started_tables_compare_a_moment_given_in_madrid(): void
    {
        $table = $this->table();
        $repository = app(GameTableRepositoryInterface::class);

        $this->assertSame([$table->id], $repository->idsStartedInStatuses([TableStatus::Scheduled], new DateTimeImmutable('2026-07-01 12:00:00', new DateTimeZone('Europe/Madrid'))));
        $this->assertSame([], $repository->idsStartedInStatuses([TableStatus::Scheduled], new DateTimeImmutable('2026-07-01 11:59:00', new DateTimeZone('Europe/Madrid'))));
    }

    public function test_the_campaign_writes_store_instants_given_in_madrid_as_utc(): void
    {
        $campaign = $this->campaign();
        $madrid = new DateTimeImmutable('2026-07-01 12:00:00', new DateTimeZone('Europe/Madrid'));
        $user = UserModel::factory()->create();

        app(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), $user->id, $madrid);
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_campaign_players', $campaign->id, 'created_at', 'campaign_id'));

        $requestId = (string) Str::uuid();
        app(CampaignJoinRequestRepositoryInterface::class)->save(new CampaignJoinRequest(
            new JoinRequestId($requestId),
            new CampaignId($campaign->id),
            UserModel::factory()->create()->id,
            JoinRequestStatus::Rejected,
            null,
            decidedBy: $this->creator()->id,
            decidedAt: $madrid,
        ));
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_campaign_join_requests', $requestId, 'decided_at'));

        $invitationId = (string) Str::uuid();
        $invitations = app(CampaignInvitationRepositoryInterface::class);
        $invitations->save(new CampaignInvitation(
            new CampaignInvitationId($invitationId),
            new CampaignId($campaign->id),
            'ana@example.com',
            $this->creator()->id,
            'Mary Major',
            hash('sha256', 'token'),
            $madrid->modify('+14 days'),
            1,
            $madrid,
        ));
        $this->assertSame('2026-07-15 10:00:00', $this->storedValue('gametables_campaign_invitations', $invitationId, 'expires_at'));
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_campaign_invitations', $invitationId, 'last_sent_at'));
        // Live until 12:00 in Madrid on the 15th
        $this->assertCount(1, $invitations->pendingForEmail('ana@example.com', new DateTimeImmutable('2026-07-15 11:59:00', new DateTimeZone('Europe/Madrid'))));
        $this->assertCount(0, $invitations->pendingForEmail('ana@example.com', new DateTimeImmutable('2026-07-15 12:00:00', new DateTimeZone('Europe/Madrid'))));
        $this->assertSame(0, $invitations->deleteExpired(new DateTimeImmutable('2026-07-15 11:59:00', new DateTimeZone('Europe/Madrid'))));
        $this->assertSame(1, $invitations->deleteExpired(new DateTimeImmutable('2026-07-15 12:00:00', new DateTimeZone('Europe/Madrid'))));
    }

    public function test_the_send_log_stores_and_compares_instants_given_in_madrid(): void
    {
        $campaign = $this->campaign();
        $log = app(CampaignInvitationSendLogInterface::class);
        $madrid = new DateTimeZone('Europe/Madrid');

        $log->recordSend($campaign->id, 'ana@example.com', new DateTimeImmutable('2026-07-01 12:00:00', $madrid));

        $this->assertSame('2026-07-01 10:00:00', substr((string) DB::table('gametables_campaign_invitation_sends')->value('occurred_at'), 0, 19));
        $this->assertSame(1, $log->sendsSince($campaign->id, 'ana@example.com', new DateTimeImmutable('2026-07-01 12:00:00', $madrid)));
        $this->assertSame(0, $log->sendsSince($campaign->id, 'ana@example.com', new DateTimeImmutable('2026-07-01 12:01:00', $madrid)));
        $this->assertSame(0, $log->pruneBefore(new DateTimeImmutable('2026-07-01 12:00:00', $madrid)));
        $this->assertSame(1, $log->pruneBefore(new DateTimeImmutable('2026-07-01 12:01:00', $madrid)));
    }

    public function test_a_rejection_blocks_invitations_for_thirty_madrid_days(): void
    {
        $campaign = $this->campaign();
        // 12:45 in Madrid (CEST) on 20 October; thirty days after 12:30 CET on 19 November
        app(CampaignInvitationSendLogInterface::class)->recordRejection($campaign->id, 'ana@example.com', new DateTimeImmutable('2026-10-20T10:45:00Z'));

        $this->travelTo(CarbonImmutable::parse('2026-11-19T11:30:00Z'));

        try {
            app(CampaignInvitationServiceInterface::class)->invite($campaign->id, 'ana@example.com', $this->creator()->id);
            $this->fail('The invitation should be refused within the thirty days.');
        } catch (RosterActionRefusedException $e) {
            $this->assertSame('invitation_limit', $e->reason);
        }
    }

    public function test_my_tables_keeps_the_tables_of_the_last_thirty_madrid_days(): void
    {
        // 12:00 in Madrid (CET) on 19 November; thirty days earlier is 12:00 CEST on 20 October
        $this->travelTo(CarbonImmutable::parse('2026-11-19T11:00:00Z'));
        $kept = $this->table(['starts_at' => CarbonImmutable::parse('2026-10-20T10:30:00Z'), 'title' => 'Mesa de octubre']);

        $this->actingAs($this->creator())
            ->get(route('gametables.my-tables'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('published', fn (Collection $published): bool => $published->pluck('id')->contains($kept->id))
                ->where('published.0.starts_at', '2026-10-20T12:30:00+02:00'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function eventWithTables(array $config = [], string $start = '2026-07-01T16:00:00Z'): EventModel
    {
        $event = EventModel::factory()->published()->create([
            'start_date' => CarbonImmutable::parse($start),
            'end_date' => CarbonImmutable::parse($start)->addHours(6),
        ]);

        EventGameTableConfigModel::create([
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => 'free',
            'location_mode' => 'free',
            ...$config,
        ]);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableForm(string $startsAt): array
    {
        return [
            'game_system_id' => $this->system()->id,
            'title' => 'Mesa desde la web',
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }

    private function grantRole(UserModel $user, string $roleName): void
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        app(AuthorizationServiceInterface::class)->invalidateCache();
    }

    private function actAsMember(UserModel $member): void
    {
        $this->app->instance(MembershipCheckerInterface::class, new readonly class($member->id) implements MembershipCheckerInterface
        {
            public function __construct(private string $memberId) {}

            public function isActiveMember(string $userId): bool
            {
                return $userId === $this->memberId;
            }
        });
    }

    public function test_the_registration_notice_to_the_table_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(NotifyOnRegistration::class)->handle(new ParticipantRegistered($participant->id, $table->id, null, 'player', ParticipantStatus::Confirmed));

        $this->assertEveryMailSays(ParticipantRegisteredNotification::class, self::MADRID_TIME);
    }

    public function test_the_guest_registration_notice_to_the_table_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(NotifyOnGuestRegistration::class)->handle($this->guestRegistered($participant));

        $this->assertEveryMailSays(ParticipantRegisteredNotification::class, self::MADRID_TIME);
    }

    public function test_the_cancellation_notice_to_the_table_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(NotifyOnCancellation::class)->handle(new ParticipantCancelled($participant->id, $table->id, null, true));

        $this->assertEveryMailSays(ParticipantCancelledNotification::class, self::MADRID_TIME);
    }

    public function test_the_waiting_list_promotion_mails_name_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(NotifyOnWaitingListPromotion::class)->handle(new ParticipantPromotedFromWaitingList($participant->id, $table->id, null));

        $this->assertEveryMailSays(WaitingListPromotionNotification::class, self::MADRID_TIME);
        $this->assertEveryMailSays(WaitingListPromotionGmNotification::class, self::MADRID_TIME);
    }

    public function test_the_table_cancelled_mail_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $this->participant($table);

        app(CancelParticipantsOnTableCancellation::class)->handle(new GameTableCancelled($table->id, $table->title, CarbonImmutable::parse(self::TABLE_START)->toDateTimeImmutable()));

        $this->assertEveryMailSays(TableCancelledNotification::class, self::MADRID_TIME);
    }

    public function test_the_cancellation_by_the_organization_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(SendCancellationConfirmation::class)->handle(new ParticipantCancelled($participant->id, $table->id, null, true, byOrganization: true));

        $this->assertEveryMailSays(OrganizationCancellationNotification::class, self::MADRID_TIME);
    }

    public function test_the_cancellation_confirmation_to_a_user_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $user = $this->creator();
        $participant = $this->participant($table, ['user_id' => $user->id, 'first_name' => null, 'email' => null]);

        app(SendCancellationConfirmation::class)->handle(new ParticipantCancelled($participant->id, $table->id, $user->id, true));

        $this->assertEveryMailSays(CancellationConfirmation::class, self::MADRID_TIME);
    }

    public function test_the_cancellation_confirmation_to_a_guest_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(SendCancellationConfirmation::class)->handle(new ParticipantCancelled($participant->id, $table->id, null, true));

        $this->assertEveryMailSays(GuestCancellationConfirmation::class, self::MADRID_TIME);
    }

    public function test_the_registration_confirmation_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $user = $this->creator();
        $participant = $this->participant($table, ['user_id' => $user->id, 'first_name' => null, 'email' => null]);

        app(SendRegistrationConfirmation::class)->handle(new ParticipantRegistered($participant->id, $table->id, $user->id, 'player', ParticipantStatus::Confirmed));

        $this->assertEveryMailSays(RegistrationConfirmation::class, self::MADRID_TIME);
    }

    public function test_the_guest_registration_confirmation_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(SendGuestRegistrationConfirmation::class)->handle($this->guestRegistered($participant));

        $this->assertEveryMailSays(GuestRegistrationConfirmation::class, self::MADRID_TIME);
    }

    public function test_the_confirmation_by_the_direction_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table);

        app(SendConfirmationNotification::class)->handle(new ParticipantConfirmed($participant->id, $table->id, null));

        $this->assertEveryMailSays(ParticipantConfirmedNotification::class, self::MADRID_TIME);
    }

    public function test_the_rejection_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $table = $this->table();
        $participant = $this->participant($table, ['status' => 'rejected']);

        app(SendRejectionNotification::class)->handle(new ParticipantRejected($participant->id, $table->id, null));

        $this->assertEveryMailSays(ParticipantRejectedNotification::class, self::MADRID_TIME);
    }

    public function test_a_guest_in_hold_mode_is_told_the_deadline_in_madrid_time(): void
    {
        Notification::fake();
        // Two hours of hold from 09:00Z would end at 11:00Z; the start (10:00Z) caps it
        $this->travelTo(CarbonImmutable::parse('2026-07-01T09:00:00Z'));
        $table = $this->table();

        $this->post(route('gametables.register-guest', $table->slug), [
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
            'role' => 'player',
            'gdpr_consent' => true,
        ])->assertSessionHasNoErrors();

        $this->assertStringContainsString(self::MADRID_TIME, (string) session('success'));
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_participants', ParticipantModel::query()->where('game_table_id', $table->id)->sole()->id, 'confirmation_expires_at'));
        // The table start and the deadline, both at 12:00 in Madrid
        $this->assertEveryMailSays(GuestEmailConfirmationRequest::class, self::MADRID_TIME);
        $this->assertStringNotContainsString('10:00', $this->renderedMails(GuestEmailConfirmationRequest::class)[0]);
    }

    public function test_the_session_scheduled_mail_names_the_start_in_madrid_time(): void
    {
        Notification::fake();
        $campaign = $this->campaign();
        $table = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $user = $this->creator();
        $participant = $this->participant($table, ['user_id' => $user->id, 'first_name' => null, 'email' => null]);

        app(SendCampaignSessionScheduled::class)->handle(new RosterMemberEnrolledInSession($participant->id, $table->id, $user->id, false));

        $this->assertEveryMailSays(CampaignSessionScheduledNotification::class, self::MADRID_TIME);
    }

    public function test_the_roster_added_mail_names_the_sessions_in_madrid_time(): void
    {
        Notification::fake();
        $campaign = $this->campaign();
        $table = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);

        app(SendRosterMemberAdded::class)->handle(new CampaignRosterMemberAdded($campaign->id, $this->creator()->id, [
            ['game_table_id' => $table->id, 'waiting' => false],
        ]));

        $this->assertEveryMailSays(CampaignRosterAddedNotification::class, self::MADRID_TIME);
    }

    public function test_the_invitation_mail_names_the_expiry_day_in_madrid(): void
    {
        Notification::fake();
        $campaign = $this->campaign();
        // 22:30Z on the 1st is already the 2nd in Madrid
        $invitation = CampaignInvitationModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'email' => 'ana@example.com',
            'invited_by_user_id' => $this->creator()->id,
            'inviter_name' => 'Mary Major',
            'token_hash' => hash('sha256', 'token'),
            'expires_at' => CarbonImmutable::parse('2026-07-01T22:30:00Z'),
            'send_count' => 1,
            'last_sent_at' => CarbonImmutable::parse('2026-06-17T22:30:00Z'),
        ]);

        app(SendCampaignInvitation::class)->handle(new CampaignInvitationSent($invitation->id, $campaign->id, 'ana@example.com', 'token'));

        $this->assertEveryMailSays(CampaignInvitationNotification::class, '02/07/2026');
    }

    public function test_the_invitation_expires_fourteen_madrid_days_after_it_is_sent(): void
    {
        // 12:00 in Madrid (CEST); fourteen days later the clocks have gone back an hour
        $this->travelTo(CarbonImmutable::parse('2026-10-20T10:00:00Z'));
        $campaign = $this->campaign();

        app(CampaignInvitationServiceInterface::class)->invite($campaign->id, 'ana@example.com', $this->creator()->id);

        $invitation = CampaignInvitationModel::query()->where('campaign_id', $campaign->id)->sole();
        // 12:00 in Madrid (CET)
        $this->assertSame('2026-11-03 11:00:00', $this->storedValue('gametables_campaign_invitations', $invitation->id, 'expires_at'));
        $this->assertSame('2026-10-20 10:00:00', $this->storedValue('gametables_campaign_invitations', $invitation->id, 'last_sent_at'));
    }

    public function test_the_invitation_notice_preview_names_the_expiry_day_in_madrid(): void
    {
        // Already the 17th in Madrid: fourteen days later is the 1st of July
        $this->travelTo(CarbonImmutable::parse('2026-06-16T22:30:00Z'));

        $notice = app(LegalMailNoticeRegistryInterface::class)->find(CampaignInvitationNotification::NOTICE_KEY);
        $this->assertNotNull($notice);

        $this->assertStringContainsString('01/07/2026', ($notice->preview)('Texto legal'));
    }

    public function test_the_reminder_names_the_start_in_madrid_time_and_binds_the_window_in_utc(): void
    {
        Notification::fake();
        config()->set('modules.settings.game-tables', ['notifications' => ['send_reminder' => true, 'reminder_hours_before' => 24]]);
        $table = $this->table();
        $participant = $this->participant($table);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendTableRemindersCommand::class));

        // Twenty-five hours before the start: outside the window
        $this->travelTo(CarbonImmutable::parse('2026-06-30T09:00:00Z'));
        $this->sendReminders();
        Notification::assertNothingSent();

        // Twenty-three hours before
        $this->travelTo(CarbonImmutable::parse('2026-06-30T11:00:00Z'));
        $this->sendReminders();

        $this->assertEveryMailSays(TableReminderNotification::class, self::MADRID_TIME);
        $this->assertSame('2026-06-30 11:00:00', $this->storedValue('gametables_participants', $participant->id, 'reminder_sent_at'));
    }

    public function test_the_panel_caps_the_closing_date_at_the_start_in_madrid_time(): void
    {
        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        $table = $this->table();
        $save = fn (string $closesAt) => Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertSet('data.starts_at', '2026-07-01 12:00:00')
            ->fillForm(['registration_closes_at' => $closesAt])
            ->call('save');

        $refused = $save('2026-07-01 12:01:00')->assertHasFormErrors(['registration_closes_at']);
        $this->assertSame(
            ['El cierre de inscripciones no puede ser posterior al inicio de la mesa ('.self::MADRID_TIME.')'],
            $refused->errors()->get('data.registration_closes_at'),
        );

        $save('2026-07-01 12:00:00')->assertHasNoFormErrors();
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_tables', $table->id, 'registration_closes_at'));
    }

    public function test_the_create_page_caps_the_closing_date_at_the_start_in_madrid_time(): void
    {
        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        $form = fn (string $closesAt): array => [
            'title' => 'Mesa del panel',
            'game_system_id' => $this->system()->id,
            'starts_at' => '2026-07-01 12:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'min_players' => 1,
            'max_players' => 4,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'any',
            'registration_closes_at' => $closesAt,
        ];

        $refused = Livewire::test(CreateGameTable::class)
            ->fillForm($form('2026-07-01 12:01:00'))
            ->call('create')
            ->assertHasFormErrors(['registration_closes_at']);
        $this->assertSame(
            ['El cierre de inscripciones no puede ser posterior al inicio de la mesa ('.self::MADRID_TIME.')'],
            $refused->errors()->get('data.registration_closes_at'),
        );

        Livewire::test(CreateGameTable::class)
            ->fillForm($form('2026-07-01 12:00:00'))
            ->call('create')
            ->assertHasNoFormErrors();

        $table = GameTableModel::query()->where('title', 'Mesa del panel')->sole();
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_tables', $table->id, 'starts_at'));
        $this->assertSame('2026-07-01 10:00:00', $this->storedValue('gametables_tables', $table->id, 'registration_closes_at'));
    }

    public function test_the_participants_table_names_the_guest_deadline_in_madrid_time(): void
    {
        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        $table = $this->table();
        $guest = $this->participant($table, [
            'status' => 'pending',
            'confirmed_at' => null,
            'email_confirmed_at' => null,
            'confirmation_token' => Str::random(64),
            'confirmation_mode' => 'hold',
            'confirmation_expires_at' => CarbonImmutable::parse(self::TABLE_START),
        ]);

        /** @var Testable<ParticipantsRelationManager> $component */
        $component = Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
        $column = $component->instance()->getTable()->getColumn('guest_flags');
        $this->assertInstanceOf(TextColumn::class, $column);
        $row = $component->instance()->getTableRecord((string) $guest->getKey());
        $this->assertNotNull($row);
        $column->record($row);

        $this->assertSame('Caduca el '.self::MADRID_TIME, $column->getTooltip('email_unconfirmed'));
    }

    public function test_the_roster_page_names_the_sessions_and_dates_in_madrid_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-01T08:00:00Z'));
        $campaign = $this->campaign();
        // 00:30 on the 1st in Madrid, still the 30th in UTC
        $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session', 'starts_at' => CarbonImmutable::parse('2026-06-30T22:30:00Z')]);
        $player = $this->creator();
        DB::table('gametables_campaign_players')->insert([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'created_at' => '2026-05-31 22:30:00',
            'updated_at' => '2026-05-31 22:30:00',
        ]);
        $requester = UserModel::factory()->create();
        $request = CampaignJoinRequestModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'user_id' => $requester->id,
            'status' => 'pending',
        ]);
        CampaignJoinRequestModel::query()->whereKey($request->id)->update(['created_at' => '2026-05-31 22:30:00']);

        $page = app(CampaignRosterQueryInterface::class)->rosterPage($campaign->id, CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame('2026-07-01T00:30:00+02:00', $page->sessions[0]['starts_at']);
        $this->assertSame('01/07', $page->sessions[0]['date_short']);
        $this->assertSame('01/07/2026 00:30', $page->sessions[0]['date']);
        $this->assertSame('01/06/2026', $page->players[0]['joined_at']);
        $this->assertSame('01/06/2026', $page->requests[0]['created_at']);
        $this->assertSame('01/06/2026', app(CampaignRosterQueryInterface::class)->joinBox($campaign->id, $requester->id)->requestedAt);
    }

    private function guestRegistered(ParticipantModel $participant): GuestRegistered
    {
        return new GuestRegistered(
            $participant->id,
            $participant->game_table_id,
            'ana@example.com',
            'Ana',
            'player',
            (string) $participant->cancellation_token,
            ParticipantStatus::Confirmed,
        );
    }

    private function sendReminders(): void
    {
        $command = $this->artisan('game-tables:send-reminders');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful();
    }

    private function creator(): UserModel
    {
        return $this->fixtureCreator ??= UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
    }

    private function system(): GameSystemModel
    {
        return $this->fixtureSystem ??= GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system()->id,
            'created_by' => $this->creator()->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada-'.Str::lower(Str::random(8)),
            'starts_at' => CarbonImmutable::parse(self::TABLE_START),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            'notification_email' => 'mesa@example.com',
            ...$attributes,
        ])->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system()->id,
            'created_by' => $this->creator()->id,
            'title' => 'La marca del este',
            'slug' => 'la-marca-del-este-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 5,
            'roster_access' => 'anyone',
            ...$attributes,
        ]);
    }

    /**
     * A confirmed guest unless told otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function participant(GameTableModel $table, array $attributes = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
            'cancellation_token' => Str::random(64),
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => CarbonImmutable::parse('2026-06-01T08:00:00Z'),
            'email_confirmed_at' => CarbonImmutable::parse('2026-06-01T08:00:00Z'),
            ...$attributes,
        ]);
    }

    private function storedValue(string $table, string $id, string $column, string $key = 'id'): ?string
    {
        $value = DB::table($table)->where($key, $id)->value($column);

        return is_string($value) ? substr($value, 0, 19) : null;
    }

    /**
     * Every mail of the class sent while notifications were faked, rendered to HTML.
     *
     * @param  class-string<BaseNotification>  $class
     * @return list<string>
     */
    private function renderedMails(string $class): array
    {
        $fake = Notification::getFacadeRoot();
        $this->assertInstanceOf(NotificationFake::class, $fake);

        $rendered = [];
        foreach ($fake->sentNotifications() as $byKey) {
            foreach ($byKey as $byClass) {
                foreach ($byClass[$class] ?? [] as $sent) {
                    $rendered[] = (string) $sent['notification']->toMail($sent['notifiable'])->render();
                }
            }
        }

        $this->assertNotEmpty($rendered, "No {$class} was sent.");

        return $rendered;
    }

    /**
     * @param  class-string<BaseNotification>  $class
     */
    private function assertEveryMailSays(string $class, string $text): void
    {
        foreach ($this->renderedMails($class) as $html) {
            $this->assertStringContainsString($text, $html, $class);
        }
    }
}
