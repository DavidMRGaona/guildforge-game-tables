<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class RegistrationControllerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';
    protected bool $autoEnableModule = true;

    private GameTableRepositoryInterface $gameTableRepository;
    private ParticipantRepositoryInterface $participantRepository;
    private UserModel $testUser;
    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        // Rebuild route collection so module routes are resolvable by name.
        // After module boot, routes exist in RouteCollection but not in
        // CompiledRouteCollection's lookup tables. Replacing with a fresh
        // RouteCollection re-indexes all routes including the module's.
        $router = app('router');
        $oldRoutes = $router->getRoutes();
        $newRoutes = new \Illuminate\Routing\RouteCollection();
        foreach ($oldRoutes->getRoutes() as $route) {
            $newRoutes->add($route);
        }
        $router->setRoutes($newRoutes);

        $this->gameTableRepository = app(GameTableRepositoryInterface::class);
        $this->participantRepository = app(ParticipantRepositoryInterface::class);

        $this->testUser = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password',
        ]);

        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test System',
            'slug' => 'test-system',
            'game_master_title' => 'GM',
            'is_active' => true,
        ]);
    }

    public function test_guest_can_register_for_open_table(): void
    {
        $gameTable = $this->createGameTable(
            registrationType: RegistrationType::Everyone,
        );

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'John',
            'email' => 'john@example.com',
            'gdpr_consent' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $participant = $this->participantRepository->findByTableAndEmail(
            $gameTable->id,
            'john@example.com'
        );

        $this->assertNotNull($participant);
        $this->assertNull($participant->userId);
        $this->assertEquals('John', $participant->firstName);
        $this->assertEquals('john@example.com', $participant->email);
        $this->assertNotNull($participant->cancellationToken);
    }

    public function test_guest_registration_requires_first_name(): void
    {
        $gameTable = $this->createGameTable();

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'email' => 'test@example.com',
            'gdpr_consent' => true,
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    public function test_guest_registration_requires_email(): void
    {
        $gameTable = $this->createGameTable();

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'John',
            'gdpr_consent' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_guest_registration_requires_gdpr_consent(): void
    {
        $gameTable = $this->createGameTable();

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'John',
            'email' => 'john@example.com',
        ]);

        $response->assertSessionHasErrors('gdpr_consent');
    }

    public function test_guest_registration_requires_valid_email(): void
    {
        $gameTable = $this->createGameTable();

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'John',
            'email' => 'not-an-email',
            'gdpr_consent' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_guest_cannot_register_for_members_only_table(): void
    {
        $gameTable = $this->createGameTable(
            registrationType: RegistrationType::MembersOnly,
        );

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'John',
            'email' => 'john@example.com',
            'gdpr_consent' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $participant = $this->participantRepository->findByTableAndEmail(
            $gameTable->id,
            'john@example.com'
        );

        $this->assertNull($participant);
    }

    public function test_guest_cannot_register_twice_with_same_email(): void
    {
        $gameTable = $this->createGameTable();
        $email = 'duplicate@example.com';

        // First registration
        $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'First',
            'email' => $email,
            'gdpr_consent' => true,
        ]);

        // Second registration with same email
        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'Second',
            'email' => $email,
            'gdpr_consent' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_guest_cannot_register_for_invite_only_table(): void
    {
        $gameTable = $this->createGameTable(
            registrationType: RegistrationType::Invite,
        );

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'John',
            'email' => 'john@example.com',
            'gdpr_consent' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_guest_can_register_with_optional_phone(): void
    {
        $gameTable = $this->createGameTable();

        $response = $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '+1234567890',
            'gdpr_consent' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $participant = $this->participantRepository->findByTableAndEmail(
            $gameTable->id,
            'jane@example.com'
        );

        $this->assertNotNull($participant);
        $this->assertEquals('+1234567890', $participant->phone);
    }

    public function test_cancel_by_token_shows_confirmation_page(): void
    {
        $gameTable = $this->createGameTable();
        $participant = $this->createGuestParticipant($gameTable->id);

        $response = $this->get(route('gametables.cancel-confirmation', $participant->cancellationToken));

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page
            ->component('GameTables/CancelRegistration', shouldExist: false)
            ->has('participant')
            ->has('gameTable')
            ->has('token')
            ->where('token', $participant->cancellationToken)
        );
    }

    public function test_cancel_by_token_cancels_registration(): void
    {
        $gameTable = $this->createGameTable();
        $participant = $this->createGuestParticipant($gameTable->id, status: ParticipantStatus::Confirmed);

        $response = $this->delete(route('gametables.cancel-by-token', $participant->cancellationToken));

        $response->assertRedirect(route('gametables.index'));
        $response->assertSessionHas('success');

        $updated = $this->participantRepository->find($participant->id);
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isCancelled());
    }

    public function test_cancel_by_token_redirects_for_invalid_token(): void
    {
        $response = $this->delete(route('gametables.cancel-by-token', 'invalid-token'));

        $response->assertRedirect(route('gametables.index'));
        $response->assertSessionHas('error');
    }

    public function test_cancel_confirmation_redirects_for_invalid_token(): void
    {
        $response = $this->get(route('gametables.cancel-confirmation', 'invalid-token'));

        $response->assertRedirect(route('gametables.index'));
        $response->assertSessionHas('error');
    }

    public function test_guest_cannot_cancel_already_cancelled_registration(): void
    {
        $gameTable = $this->createGameTable();
        $participant = $this->createGuestParticipant($gameTable->id, status: ParticipantStatus::Cancelled);

        $response = $this->delete(route('gametables.cancel-by-token', $participant->cancellationToken));

        $response->assertRedirect(route('gametables.index'));
        $response->assertSessionHas('error');
    }

    public function test_guest_registration_creates_player_by_default(): void
    {
        $gameTable = $this->createGameTable();

        $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'gdpr_consent' => true,
        ]);

        $participant = $this->participantRepository->findByTableAndEmail(
            $gameTable->id,
            'alice@example.com'
        );

        $this->assertNotNull($participant);
        $this->assertEquals(ParticipantRole::Player, $participant->role);
    }

    public function test_guest_can_register_as_spectator(): void
    {
        $gameTable = $this->createGameTable();

        $this->post(route('gametables.register-guest', $gameTable->slug), [
            'first_name' => 'Bob',
            'email' => 'bob@example.com',
            'role' => ParticipantRole::Spectator->value,
            'gdpr_consent' => true,
        ]);

        $participant = $this->participantRepository->findByTableAndEmail(
            $gameTable->id,
            'bob@example.com'
        );

        $this->assertNotNull($participant);
        $this->assertEquals(ParticipantRole::Spectator, $participant->role);
    }

    private function createGameTable(
        ?GameTableId $id = null,
        RegistrationType $registrationType = RegistrationType::Everyone,
    ): GameTable {
        $startsAt = new DateTimeImmutable('+1 week');
        $timeSlot = new TimeSlot($startsAt, 240);

        $gameTable = new GameTable(
            id: $id ?? GameTableId::generate(),
            gameSystemId: new GameSystemId($this->gameSystem->id),
            createdBy: $this->testUser->id,
            title: 'Test Game Table',
            slug: 'test-game-table-' . Str::random(6),
            timeSlot: $timeSlot,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: TableStatus::Scheduled,
            minPlayers: 2,
            maxPlayers: 6,
            registrationType: $registrationType,
            autoConfirm: true,
            isPublished: true,
        );

        $this->gameTableRepository->save($gameTable);

        return $gameTable;
    }

    private function createGuestParticipant(
        GameTableId $gameTableId,
        ParticipantStatus $status = ParticipantStatus::Pending,
    ): Participant {
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: $gameTableId,
            userId: null,
            role: ParticipantRole::Player,
            status: $status,
            firstName: 'Test',
            email: 'test@example.com',
            cancellationToken: bin2hex(random_bytes(32)),
            createdAt: new DateTimeImmutable(),
        );

        if ($status === ParticipantStatus::Confirmed) {
            $participant->confirm();
        }

        $this->participantRepository->save($participant);

        return $participant;
    }
}
