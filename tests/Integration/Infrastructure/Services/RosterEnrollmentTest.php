<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The roster of a campaign in one of its sessions: enrolled confirmed whatever the table's
 * confirmation, never twice, never over an absence; leaving cancels the roster row only; a member
 * rejoins without the table rules. Real repositories and lock.
 */
final class RosterEnrollmentTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const array EVENTS = [
        GuestRegistered::class,
        ParticipantCancelled::class,
        ParticipantConfirmed::class,
        ParticipantPromotedFromWaitingList::class,
        ParticipantRegistered::class,
    ];

    private GameTableModel $table;

    private UserModel $john;

    private UserModel $jane;

    private UserModel $richard;

    private CampaignId $campaignId;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake(self::EVENTS);
        config()->set('modules.settings.game-tables', []);
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'status' => 'active',
        ]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'campaign_id' => $campaign->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Sesión 3',
            'slug' => 'sesion-3',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => false,
        ]);

        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);

        $this->campaignId = new CampaignId($campaign->id);
        $roster = app(CampaignRosterRepositoryInterface::class);
        foreach ([$this->john, $this->jane, $this->richard] as $member) {
            $roster->add($this->campaignId, $member->id, CarbonImmutable::now()->toDateTimeImmutable());
        }
    }

    public function test_the_roster_is_enrolled_confirmed_whatever_the_table_confirmation(): void
    {
        $enrolled = $this->service()->enrollRoster($this->table->id);

        $this->assertCount(3, $enrolled);
        foreach ([$this->john, $this->jane, $this->richard] as $member) {
            $row = $this->rowOf($member);
            $this->assertSame('confirmed', $row->status->value);
            $this->assertTrue($row->from_roster);
            $this->assertSame('player', $row->role->value);
        }
        $this->assertNothingAnnounced();
    }

    public function test_enrolling_twice_adds_nothing(): void
    {
        $this->service()->enrollRoster($this->table->id);

        $this->assertSame([], $this->service()->enrollRoster($this->table->id));
        $this->assertSame(3, ParticipantModel::query()->where('game_table_id', $this->table->id)->count());
    }

    public function test_a_loose_registration_is_adopted_without_a_new_row(): void
    {
        $loose = $this->row($this->jane, 'pending');

        $enrolled = $this->service()->enrollRoster($this->table->id);

        $this->assertEqualsCanonicalizing(
            [$this->john->id, $this->richard->id],
            array_map(fn (ParticipantResponseDTO $p): ?string => $p->userId, $enrolled),
        );
        $this->assertSame(1, ParticipantModel::query()->where('user_id', $this->jane->id)->count());
        $row = $loose->refresh();
        $this->assertSame('pending', $row->status->value);
        $this->assertTrue($row->from_roster);
    }

    public function test_an_absence_is_never_re_enrolled(): void
    {
        $absence = $this->row($this->jane, 'cancelled', fromRoster: true, reason: 'session_absence');

        $enrolled = $this->service()->enrollRoster($this->table->id);

        $this->assertCount(2, $enrolled);
        $row = $absence->refresh();
        $this->assertSame('cancelled', $row->status->value);
        $this->assertSame('session_absence', $row->getRawOriginal('cancellation_reason'));
    }

    public function test_a_left_roster_row_is_reactivated(): void
    {
        $left = $this->row($this->jane, 'cancelled', fromRoster: true, reason: 'left_roster');

        $enrolled = $this->service()->enrollRoster($this->table->id);

        $this->assertCount(3, $enrolled);
        $this->assertSame(1, ParticipantModel::query()->where('user_id', $this->jane->id)->count());
        $row = $left->refresh();
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->getRawOriginal('cancellation_reason'));
    }

    public function test_a_members_guest_registration_is_taken_over_without_a_new_row(): void
    {
        $guest = $this->guestRowOfJohn();

        $enrolled = $this->service()->enrollRoster($this->table->id);

        $this->assertEqualsCanonicalizing(
            [$this->jane->id, $this->richard->id],
            array_map(fn (ParticipantResponseDTO $p): ?string => $p->userId, $enrolled),
        );
        $this->assertSame(3, ParticipantModel::query()->where('game_table_id', $this->table->id)->count());
        $row = $guest->refresh();
        $this->assertSame($this->john->id, $row->user_id);
        $this->assertNull($row->email);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertTrue($row->from_roster);
    }

    public function test_a_guest_row_never_replaces_a_rejected_registration(): void
    {
        $this->assertGuestRowLeavesTheOwnRowAlone($this->row($this->john, 'rejected', fromRoster: true), 'rejected');
    }

    public function test_a_guest_row_never_replaces_an_absence(): void
    {
        $this->assertGuestRowLeavesTheOwnRowAlone($this->row($this->john, 'cancelled', fromRoster: true, reason: 'session_absence'), 'cancelled');
    }

    public function test_a_late_member_on_a_full_session_waits_ahead_of_outsiders(): void
    {
        $this->table->update(['max_players' => 2]);
        $leaving = $this->row(UserModel::factory()->create(), 'confirmed');
        $this->row(UserModel::factory()->create(), 'confirmed');
        $mary = $this->row(UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']), 'waiting_list', position: 1);

        $enrolled = $this->service()->enrollRoster($this->table->id, $this->john->id);

        $this->assertCount(1, $enrolled);
        $this->assertSame($this->john->id, $enrolled[0]->userId);
        $this->assertSame('waiting_list', $enrolled[0]->status->value);
        $this->assertSame(1, $enrolled[0]->waitingListRank);

        $this->service()->cancel($leaving->id);

        $this->assertSame('confirmed', $this->rowOf($this->john)->status->value);
        $this->assertSame('waiting_list', $mary->refresh()->status->value);
    }

    public function test_closed_registrations_enrol_nobody(): void
    {
        $this->table->update(['registration_closes_at' => now()->subHour()]);

        $this->assertSame([], $this->service()->enrollRoster($this->table->id));
        $this->assertSame(0, ParticipantModel::query()->where('game_table_id', $this->table->id)->count());
    }

    public function test_withdraw_cancels_only_the_roster_row_and_promotes(): void
    {
        $this->table->update(['max_players' => 3]);
        $this->service()->enrollRoster($this->table->id);
        $outsider = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $mary = $this->row($outsider, 'waiting_list', position: 1);

        // Mary's own registration is not a roster row: leaving the roster never touches it
        $this->assertFalse($this->service()->withdrawRosterMember($this->table->id, $outsider->id));
        $this->assertTrue($this->service()->withdrawRosterMember($this->table->id, $this->john->id));

        $row = $this->rowOf($this->john);
        $this->assertSame('cancelled', $row->status->value);
        $this->assertSame('left_roster', $row->getRawOriginal('cancellation_reason'));
        // A plain outsider promoted on a table with manual confirmation waits for the director
        $this->assertSame('pending', $mary->refresh()->status->value);
        $this->assertSame('confirmed', $this->rowOf($this->jane)->status->value);
        Event::assertNotDispatched(ParticipantCancelled::class);
        Event::assertDispatched(ParticipantPromotedFromWaitingList::class, fn (ParticipantPromotedFromWaitingList $event): bool => $event->participantId === $mary->id);
    }

    public function test_a_member_rejoins_without_the_table_rules(): void
    {
        $this->table->update(['registration_type' => 'members_only']);
        $absence = $this->row($this->jane, 'cancelled', fromRoster: true, reason: 'session_absence');

        $result = $this->register($this->jane, ParticipantRole::Player);

        $this->assertSame($absence->id, $result->id);
        $this->assertSame('confirmed', $result->status->value);
        $this->assertSame(1, ParticipantModel::query()->where('user_id', $this->jane->id)->count());
        $row = $absence->refresh();
        $this->assertSame('confirmed', $row->status->value);
        $this->assertTrue($row->from_roster);
        $this->assertNull($row->getRawOriginal('cancellation_reason'));
        Event::assertDispatched(ParticipantRegistered::class, fn (ParticipantRegistered $event): bool => $event->participantId === $absence->id
            && $event->notifyOrganization);
        Event::assertDispatched(ParticipantConfirmed::class, fn (ParticipantConfirmed $event): bool => $event->participantId === $absence->id
            && $event->automatic);
    }

    public function test_rejoining_a_full_session_is_refused(): void
    {
        $this->service()->enrollRoster($this->table->id);
        $this->service()->cancelByUser($this->table->id, $this->jane->id);
        $this->table->update(['max_players' => 2]);

        try {
            $this->register($this->jane, ParticipantRole::Player);
            $this->fail('A full session must refuse the rejoin');
        } catch (TableFullException $e) {
            $this->assertSame('table_full', $e->reason);
        }

        $row = $this->rowOf($this->jane);
        $this->assertSame('cancelled', $row->status->value);
        $this->assertSame('session_absence', $row->getRawOriginal('cancellation_reason'));
    }

    // Members-only: the usual rules would answer members_only, the roster path registration_closed
    public function test_rejoining_after_the_closing_is_refused(): void
    {
        $this->row($this->jane, 'cancelled', fromRoster: true, reason: 'session_absence');
        $this->table->update(['registration_closes_at' => now()->subHour(), 'registration_type' => 'members_only']);

        try {
            $this->register($this->jane, ParticipantRole::Player);
            $this->fail('A closed session must refuse the rejoin');
        } catch (RegistrationNotAllowedException $e) {
            $this->assertSame('registration_closed', $e->reason);
            $this->assertSame('Las inscripciones están cerradas', $e->getMessage());
        }

        $this->assertSame('cancelled', $this->rowOf($this->jane)->status->value);
        Event::assertNotDispatched(ParticipantRegistered::class);
    }

    public function test_a_member_already_registered_is_refused(): void
    {
        $this->service()->enrollRoster($this->table->id);

        $this->expectException(AlreadyRegisteredException::class);

        $this->register($this->jane, ParticipantRole::Player);
    }

    public function test_a_rejected_member_follows_the_table_rules(): void
    {
        $this->assertOrganisationDecisionStands('rejected');
    }

    public function test_a_no_show_member_follows_the_table_rules(): void
    {
        $this->assertOrganisationDecisionStands('no_show');
    }

    public function test_a_spectator_request_from_a_member_follows_the_table_rules(): void
    {
        $this->table->update(['registration_type' => 'members_only']);

        try {
            $this->register($this->jane, ParticipantRole::Spectator);
            $this->fail('The members-only rule must refuse a spectator');
        } catch (RegistrationNotAllowedException $e) {
            $this->assertSame('members_only', $e->reason);
        }

        $this->assertNull(ParticipantModel::query()->where('user_id', $this->jane->id)->first());
    }

    public function test_restoring_an_absence_does_not_notify_the_organisation(): void
    {
        $absence = $this->row($this->jane, 'cancelled', fromRoster: true, reason: 'session_absence');

        $result = $this->service()->restoreAbsence($this->table->id, $this->jane->id);

        $this->assertSame($absence->id, $result->id);
        $this->assertSame('confirmed', $absence->refresh()->status->value);
        $this->assertNull($absence->getRawOriginal('cancellation_reason'));
        Event::assertDispatched(ParticipantRegistered::class, fn (ParticipantRegistered $event): bool => $event->participantId === $absence->id
            && ! $event->notifyOrganization);
        Event::assertDispatched(ParticipantConfirmed::class, fn (ParticipantConfirmed $event): bool => $event->participantId === $absence->id
            && $event->automatic);
    }

    public function test_restoring_something_other_than_an_absence_fails(): void
    {
        $this->row($this->jane, 'cancelled', fromRoster: true, reason: 'left_roster');

        $this->expectException(ParticipantNotFoundException::class);

        $this->service()->restoreAbsence($this->table->id, $this->jane->id);
    }

    public function test_an_ex_member_cannot_restore_an_absence(): void
    {
        $absence = $this->row($this->jane, 'cancelled', fromRoster: true, reason: 'session_absence');
        app(CampaignRosterRepositoryInterface::class)->remove($this->campaignId, $this->jane->id);

        try {
            $this->service()->restoreAbsence($this->table->id, $this->jane->id);
            $this->fail('Only a roster member may have an absence restored');
        } catch (ParticipantNotFoundException) {
            // Expected
        }

        $this->assertSame('cancelled', $absence->refresh()->status->value);
        Event::assertNotDispatched(ParticipantRegistered::class);
    }

    /**
     * A rejected or no-show row is the organisation's say: the roster path never reactivates it,
     * the table rules answer (here, members only).
     */
    private function assertOrganisationDecisionStands(string $status): void
    {
        $this->table->update(['registration_type' => 'members_only']);
        $row = $this->row($this->jane, $status, fromRoster: true);

        try {
            $this->register($this->jane, ParticipantRole::Player);
            $this->fail('The table rules must refuse a member whose row the organisation closed');
        } catch (RegistrationNotAllowedException $e) {
            $this->assertSame('members_only', $e->reason);
        }

        $this->assertSame($status, $row->refresh()->status->value);
        Event::assertNotDispatched(ParticipantRegistered::class);
    }

    /**
     * John's own row is the organisation's decision or an absence; his active guest row (same
     * email as his account) must not take its place on enrolment.
     */
    private function assertGuestRowLeavesTheOwnRowAlone(ParticipantModel $own, string $status): void
    {
        $guest = $this->guestRowOfJohn();

        $this->assertSame([], $this->service()->enrollRoster($this->table->id, $this->john->id));

        $this->assertSame($status, $own->refresh()->status->value);
        $row = $guest->refresh();
        $this->assertNull($row->user_id);
        $this->assertSame('john@example.com', $row->email);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertFalse($row->from_roster);
    }

    private function guestRowOfJohn(): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => null,
            'role' => 'player',
            'status' => 'confirmed',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'cancellation_token' => 'token-de-prueba',
            'email_confirmed_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function register(UserModel $user, ParticipantRole $role): ParticipantResponseDTO
    {
        return $this->service()->register(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: $user->id,
            role: $role,
        ));
    }

    private function row(UserModel $user, string $status, bool $fromRoster = false, ?string $reason = null, ?int $position = null): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => $status,
            'waiting_list_position' => $position,
            'from_roster' => $fromRoster,
            'cancellation_reason' => $reason,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    private function rowOf(UserModel $user): ParticipantModel
    {
        return ParticipantModel::query()
            ->where('game_table_id', $this->table->id)
            ->where('user_id', $user->id)
            ->sole();
    }

    private function assertNothingAnnounced(): void
    {
        foreach (self::EVENTS as $event) {
            Event::assertNotDispatched($event);
        }
    }
}
