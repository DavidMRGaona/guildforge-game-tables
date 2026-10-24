<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\StatusLogActorKind;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;
use Modules\GameTables\Notifications\ParticipantConfirmedNotification;
use Modules\GameTables\Notifications\ParticipantRejectedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The «Gestionar inscritos» page of a table whose registrations are delegated: only a linked
 * director reaches it, approves or rejects pending requests, and never sees contact details.
 */
final class TableRegistrationsControllerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $john;

    private UserModel $mary;

    private UserModel $jane;

    private UserModel $richard;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = $this->tableDirectedBy($this->john, 'La cripta olvidada');
    }

    // The page

    public function test_a_linked_director_sees_the_registrations_page(): void
    {
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::parse('2026-10-30 12:00:00'), ['notes' => 'Llevo dados']);
        $guest = $this->guestRow($this->table, 'pending', CarbonImmutable::parse('2026-10-31 12:00:00'));
        $this->userRow($this->table, $this->richard, 'confirmed', CarbonImmutable::parse('2026-10-29 12:00:00'));

        $this->actingAs($this->john)
            ->get($this->pageUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Registrations', shouldExist: false)
                ->where('table.title', 'La cripta olvidada')
                ->where('table.slug', 'la-cripta-olvidada')
                ->where('table.starts_at', '2026-11-09T18:00:00+01:00')
                ->where('table.auto_confirm', false)
                ->where('table.max_players', 3)
                ->where('table.confirmed_players', 1)
                ->where('pending_count', 2)
                ->has('rows', 3)
                ->where('rows.0.id', $jane->id)
                ->where('rows.0.name', 'Jane Doe')
                ->where('rows.0.is_guest', false)
                ->where('rows.0.role', 'player')
                ->where('rows.0.role_label', 'Jugador')
                ->where('rows.0.status', 'pending')
                ->where('rows.0.status_label', 'Pendiente')
                ->where('rows.0.requested_at', '2026-10-30T12:00:00+01:00')
                ->where('rows.0.awaiting_email_confirmation', false)
                ->where('rows.0.can_approve', true)
                ->where('rows.0.can_reject', true)
                ->missing('rows.0.email')
                ->missing('rows.0.phone')
                ->missing('rows.0.notes')
                ->where('rows.1.id', $guest->id)
                ->where('rows.1.name', 'Janie Roe')
                ->where('rows.1.is_guest', true)
                ->where('rows.1.awaiting_email_confirmation', true)
                ->where('rows.1.can_approve', false)
                ->where('rows.1.can_reject', true)
                ->missing('rows.1.email')
                ->missing('rows.1.phone')
                ->missing('rows.1.notes')
                ->where('rows.2.name', 'Richard Roe')
                ->where('rows.2.status', 'confirmed')
                ->where('rows.2.can_approve', false)
                ->where('rows.2.can_reject', false)
                ->where('seo.title', 'Inscritos de La cripta olvidada')
                ->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_the_page_never_carries_contact_details(): void
    {
        $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::parse('2026-10-30 12:00:00'), ['notes' => 'Nota privada']);
        $this->guestRow($this->table, 'pending', CarbonImmutable::parse('2026-10-31 12:00:00'));

        $content = (string) $this->actingAs($this->john)->get($this->pageUrl())->assertOk()->getContent();

        $this->assertStringNotContainsString('jane@example.com', $content);
        $this->assertStringNotContainsString('janie@example.com', $content);
        $this->assertStringNotContainsString('600123123', $content);
        $this->assertStringNotContainsString('Nota privada', $content);
    }

    public function test_rows_list_pending_then_confirmed_then_the_waiting_list_then_the_rest(): void
    {
        $at = CarbonImmutable::parse('2026-10-20 12:00:00');
        $cancelled = $this->userRow($this->table, UserModel::factory()->create(), 'cancelled', $at);
        $secondInLine = $this->userRow($this->table, UserModel::factory()->create(), 'waiting_list', $at->addHour(), ['waiting_list_position' => 2]);
        $firstInLine = $this->userRow($this->table, UserModel::factory()->create(), 'waiting_list', $at->addHours(2), ['waiting_list_position' => 1]);
        $confirmed = $this->userRow($this->table, UserModel::factory()->create(), 'confirmed', $at->addHours(3));
        $laterPending = $this->userRow($this->table, UserModel::factory()->create(), 'pending', $at->addHours(5));
        $earlierPending = $this->userRow($this->table, UserModel::factory()->create(), 'pending', $at->addHours(4));

        $this->actingAs($this->john)
            ->get($this->pageUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 6)
                ->where('rows.0.id', $earlierPending->id)
                ->where('rows.1.id', $laterPending->id)
                ->where('rows.2.id', $confirmed->id)
                ->where('rows.3.id', $firstInLine->id)
                ->where('rows.4.id', $secondInLine->id)
                ->where('rows.5.id', $cancelled->id)
                ->where('rows.3.can_approve', false)
                ->where('rows.3.can_reject', false)
                ->where('pending_count', 2));
    }

    public function test_the_route_is_the_one_the_emails_link_to(): void
    {
        $this->assertSame(
            url('/mesas/mis-mesas/la-cripta-olvidada/inscritos'),
            route('gametables.registrations', $this->table->slug),
        );
    }

    public function test_the_actions_are_rate_limited(): void
    {
        foreach (['gametables.registrations.approve', 'gametables.registrations.reject'] as $name) {
            $this->assertContains(
                'throttle:game-tables-table-registrations',
                Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [],
            );
        }
    }

    // Who may

    public function test_someone_who_does_not_direct_the_table_is_forbidden(): void
    {
        $this->actingAs($this->mary)->get($this->pageUrl())->assertForbidden();
    }

    public function test_a_director_of_a_table_that_does_not_delegate_is_forbidden(): void
    {
        $table = $this->tableDirectedBy($this->john, 'Sin delegar', ['delegates_registrations' => false]);

        $this->actingAs($this->john)->get($this->pageUrl($table))->assertForbidden();
    }

    public function test_an_unpublished_table_is_forbidden(): void
    {
        $table = $this->tableDirectedBy($this->john, 'Sin publicar', ['is_published' => false]);

        $this->actingAs($this->john)->get($this->pageUrl($table))->assertForbidden();
    }

    public function test_a_campaign_session_is_forbidden(): void
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->john->id,
            'title' => 'La torre del mago',
            'slug' => 'la-torre-del-mago',
            'status' => 'recruiting',
        ]);
        $session = $this->tableDirectedBy($this->john, 'Sesión 1', ['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);

        $this->actingAs($this->john)->get($this->pageUrl($session))->assertForbidden();
    }

    public function test_an_anonymized_director_is_forbidden(): void
    {
        DB::table('users')->where('id', $this->john->id)->update(['anonymized_at' => CarbonImmutable::now()]);

        $this->actingAs($this->john->fresh())->get($this->pageUrl())->assertForbidden();
    }

    public function test_a_guest_is_sent_to_log_in(): void
    {
        $this->get($this->pageUrl())->assertRedirect(route('login'));
    }

    public function test_someone_who_does_not_direct_the_table_cannot_act(): void
    {
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::now());

        $this->actingAs($this->mary)->post($this->actionUrl($jane, 'aprobar'))->assertForbidden();
        $this->actingAs($this->mary)->post($this->actionUrl($jane, 'rechazar'))->assertForbidden();

        $this->assertSame('pending', $jane->refresh()->status->value);
        Notification::assertNothingSent();
    }

    // Review focus 1: the participant belongs to the table of the URL

    public function test_a_participant_of_another_table_is_not_found(): void
    {
        $other = $this->tableDirectedBy($this->mary, 'La torre del mago');
        $stranger = $this->userRow($other, $this->jane, 'pending', CarbonImmutable::now());

        $this->asDirector()->post($this->actionUrl($stranger, 'aprobar'))->assertNotFound();
        $this->asDirector()->post($this->actionUrl($stranger, 'rechazar'), ['reason' => 'No'])->assertNotFound();
        $this->asDirector()->post($this->pageUrl().'/'.Str::uuid().'/aprobar')->assertNotFound();
        $this->asDirector()->post($this->pageUrl().'/not-a-uuid/aprobar')->assertNotFound();

        $this->assertSame('pending', $stranger->refresh()->status->value);
        Notification::assertNothingSent();
    }

    // Review focus 2: only pending requests are answered

    public function test_a_registration_that_is_not_pending_is_refused(): void
    {
        $rows = [
            $this->userRow($this->table, UserModel::factory()->create(), 'confirmed', CarbonImmutable::now()),
            $this->userRow($this->table, UserModel::factory()->create(), 'rejected', CarbonImmutable::now()),
            $this->userRow($this->table, UserModel::factory()->create(), 'waiting_list', CarbonImmutable::now(), ['waiting_list_position' => 1]),
            $this->userRow($this->table, UserModel::factory()->create(), 'cancelled', CarbonImmutable::now()),
        ];

        foreach ($rows as $row) {
            $status = $row->status->value;

            foreach (['aprobar', 'rechazar'] as $action) {
                $this->asDirector()
                    ->post($this->actionUrl($row, $action))
                    ->assertRedirect($this->pageUrl())
                    ->assertSessionHas('error', 'Esta inscripción ya no está pendiente.');

                $this->assertSame($status, $row->refresh()->status->value, "{$action} on {$status}");
            }
        }

        Notification::assertNothingSent();
    }

    // Review focus 3: approving never takes the table over its seats

    /**
     * @return array<string, array{bool}>
     */
    public static function waitingList(): array
    {
        // Off is the default: pending requests hold no seat then, so only the check stops an overfill
        return ['waiting list off' => [false], 'waiting list on' => [true]];
    }

    #[DataProvider('waitingList')]
    public function test_approving_on_a_full_table_is_refused(bool $waitingList): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $waitingList);
        $this->table->forceFill(['max_players' => 1])->save();
        $this->userRow($this->table, $this->richard, 'confirmed', CarbonImmutable::parse('2026-10-29 12:00:00'));
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::parse('2026-10-30 12:00:00'));

        $this->asDirector()
            ->post($this->actionUrl($jane, 'aprobar'))
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('error', 'La mesa está completa');

        $this->assertSame('pending', $jane->refresh()->status->value);
        Notification::assertNothingSent();
    }

    public function test_approving_on_a_completed_table_is_refused(): void
    {
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::parse('2026-10-30 12:00:00'));
        $this->table->forceFill(['status' => 'completed'])->save();

        $this->asDirector()
            ->post($this->actionUrl($jane, 'aprobar'))
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('error', 'Esta partida ya ha terminado o se ha cancelado.');

        $this->assertSame('pending', $jane->refresh()->status->value);
        Notification::assertNothingSent();
    }

    // Approving

    public function test_approving_a_pending_user(): void
    {
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::parse('2026-10-30 12:00:00'));

        $this->asDirector()
            ->post($this->actionUrl($jane, 'aprobar'))
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('success', 'Has aprobado la inscripción de Jane Doe.');

        $this->assertSame('confirmed', $jane->refresh()->status->value);
        Notification::assertSentOnDemand(ParticipantConfirmedNotification::class);

        $log = ParticipantStatusLogModel::query()
            ->where('participant_id', $jane->id)
            ->where('to_status', 'confirmed')
            ->sole();
        $this->assertSame($this->john->id, $log->actor_user_id);
        $this->assertSame(StatusLogActorKind::Account, $log->actor_kind);
    }

    public function test_approving_a_guest_awaiting_their_email_confirmation_is_refused(): void
    {
        $guest = $this->guestRow($this->table, 'pending', CarbonImmutable::parse('2026-10-31 12:00:00'));

        $this->asDirector()
            ->post($this->actionUrl($guest, 'aprobar'))
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('error', 'Esta persona aún no ha confirmado su email; podrás aprobarla cuando lo haga.');

        // The director never vouches for the email (RegistrationService::vouchForGuestBeforeApproval)
        $guest->refresh();
        $this->assertSame('pending', $guest->status->value);
        $this->assertNull($guest->email_confirmed_at);
        Notification::assertNothingSent();
    }

    // Rejecting

    public function test_rejecting_a_guest_with_a_reason_emails_it(): void
    {
        $guest = $this->guestRow($this->table, 'pending', CarbonImmutable::parse('2026-10-31 12:00:00'));

        $this->asDirector()
            ->post($this->actionUrl($guest, 'rechazar'), ['reason' => 'La partida es para jugadores con experiencia'])
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('success', 'Has rechazado la solicitud de Janie Roe.');

        $this->assertSame('rejected', $guest->refresh()->status->value);
        Notification::assertSentOnDemand(
            ParticipantRejectedNotification::class,
            fn (ParticipantRejectedNotification $n): bool => $n->reason === 'La partida es para jugadores con experiencia',
        );

        $log = ParticipantStatusLogModel::query()
            ->where('participant_id', $guest->id)
            ->where('to_status', 'rejected')
            ->sole();
        $this->assertSame($this->john->id, $log->actor_user_id);
        $this->assertSame('La partida es para jugadores con experiencia', $log->reason);
    }

    public function test_rejecting_without_a_reason(): void
    {
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::now());

        $this->asDirector()
            ->post($this->actionUrl($jane, 'rechazar'), ['reason' => null])
            ->assertSessionHas('success', 'Has rechazado la solicitud de Jane Doe.');

        $this->assertSame('rejected', $jane->refresh()->status->value);
        Notification::assertSentOnDemand(
            ParticipantRejectedNotification::class,
            fn (ParticipantRejectedNotification $n): bool => $n->reason === null,
        );
    }

    public function test_a_blank_reason_is_no_reason_in_the_email_or_the_history(): void
    {
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::now());

        $this->asDirector()
            ->post($this->actionUrl($jane, 'rechazar'), ['reason' => '   '])
            ->assertSessionHas('success', 'Has rechazado la solicitud de Jane Doe.');

        Notification::assertSentOnDemand(
            ParticipantRejectedNotification::class,
            fn (ParticipantRejectedNotification $n): bool => $n->reason === null,
        );
        $log = ParticipantStatusLogModel::query()
            ->where('participant_id', $jane->id)
            ->where('to_status', 'rejected')
            ->sole();
        $this->assertNull($log->reason);
        $this->assertSame($this->john->id, $log->actor_user_id);
    }

    public function test_a_reason_over_500_characters_is_refused(): void
    {
        $jane = $this->userRow($this->table, $this->jane, 'pending', CarbonImmutable::now());

        $this->asDirector()
            ->post($this->actionUrl($jane, 'rechazar'), ['reason' => str_repeat('a', 501)])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $jane->refresh()->status->value);
        Notification::assertNothingSent();
    }

    // Helpers

    private function asDirector(): self
    {
        return $this->actingAs($this->john)->from($this->pageUrl());
    }

    private function pageUrl(?GameTableModel $table = null): string
    {
        return '/mesas/mis-mesas/'.($table ?? $this->table)->slug.'/inscritos';
    }

    private function actionUrl(ParticipantModel $participant, string $action): string
    {
        return $this->pageUrl().'/'.$participant->id.'/'.$action;
    }

    /**
     * A published table that delegates its registrations, directed by the user (linked game master).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function tableDirectedBy(UserModel $user, string $title, array $attributes = []): GameTableModel
    {
        $table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->mary->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'starts_at' => CarbonImmutable::parse('2026-11-09 18:00:00'),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 3,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'is_published' => true,
            'delegates_registrations' => true,
            ...$attributes,
        ]);
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'GM',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userRow(GameTableModel $table, UserModel $user, string $status, CarbonImmutable $requestedAt, array $attributes = []): ParticipantModel
    {
        $row = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            ...$attributes,
        ]);

        return $this->requestedAt($row, $requestedAt);
    }

    /**
     * A guest who has not followed the confirmation link of their email yet.
     */
    private function guestRow(GameTableModel $table, string $status, CarbonImmutable $requestedAt): ParticipantModel
    {
        $row = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => null,
            'first_name' => 'Janie',
            'last_name' => 'Roe',
            'email' => 'janie@example.com',
            'phone' => '600123123',
            'role' => 'player',
            'status' => $status,
            'email_confirmed_at' => null,
            'confirmation_mode' => 'hold',
            'confirmation_expires_at' => CarbonImmutable::now()->addDay(),
            'confirmation_token' => Str::random(64),
            'cancellation_token' => Str::random(64),
        ]);

        return $this->requestedAt($row, $requestedAt);
    }

    private function requestedAt(ParticipantModel $row, CarbonImmutable $at): ParticipantModel
    {
        // A query update: the status log observer only cares about statuses
        ParticipantModel::query()->whereKey($row->id)->update(['created_at' => $at]);

        return $row->refresh();
    }
}
