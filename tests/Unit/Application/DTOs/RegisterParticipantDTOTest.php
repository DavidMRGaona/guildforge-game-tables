<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Application\DTOs;

use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use PHPUnit\Framework\TestCase;

final class RegisterParticipantDTOTest extends TestCase
{
    public function test_it_creates_dto_for_authenticated_user(): void
    {
        $dto = new RegisterParticipantDTO(
            gameTableId: 'game-table-123',
            userId: 'user-456',
            role: ParticipantRole::Player,
            notes: 'Test notes',
        );

        $this->assertEquals('game-table-123', $dto->gameTableId);
        $this->assertEquals('user-456', $dto->userId);
        $this->assertEquals(ParticipantRole::Player, $dto->role);
        $this->assertEquals('Test notes', $dto->notes);
        $this->assertNull($dto->firstName);
        $this->assertNull($dto->email);
    }

    public function test_it_creates_dto_for_guest(): void
    {
        $dto = new RegisterParticipantDTO(
            gameTableId: 'game-table-123',
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'John',
            email: 'john@example.com',
            phone: '+1234567890',
        );

        $this->assertEquals('game-table-123', $dto->gameTableId);
        $this->assertNull($dto->userId);
        $this->assertEquals(ParticipantRole::Player, $dto->role);
        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('john@example.com', $dto->email);
        $this->assertEquals('+1234567890', $dto->phone);
    }

    public function test_is_guest_returns_true_when_user_id_is_null(): void
    {
        $dto = new RegisterParticipantDTO(
            gameTableId: 'game-table-123',
            userId: null,
            firstName: 'Jane',
            email: 'jane@example.com',
        );

        $this->assertTrue($dto->isGuest());
    }

    public function test_is_guest_returns_false_when_user_id_is_present(): void
    {
        $dto = new RegisterParticipantDTO(
            gameTableId: 'game-table-123',
            userId: 'user-456',
        );

        $this->assertFalse($dto->isGuest());
    }

    public function test_from_array_creates_guest_dto(): void
    {
        $data = [
            'game_table_id' => 'game-table-789',
            'user_id' => null,
            'role' => ParticipantRole::Player->value,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'phone' => '+9876543210',
            'notes' => 'Guest notes',
        ];

        $dto = RegisterParticipantDTO::fromArray($data);

        $this->assertEquals('game-table-789', $dto->gameTableId);
        $this->assertNull($dto->userId);
        $this->assertEquals(ParticipantRole::Player, $dto->role);
        $this->assertEquals('Alice', $dto->firstName);
        $this->assertEquals('Smith', $dto->lastName);
        $this->assertEquals('alice@example.com', $dto->email);
        $this->assertEquals('+9876543210', $dto->phone);
        $this->assertEquals('Guest notes', $dto->notes);
        $this->assertTrue($dto->isGuest());
    }

    public function test_from_array_creates_authenticated_user_dto(): void
    {
        $data = [
            'game_table_id' => 'game-table-999',
            'user_id' => 'user-111',
            'role' => ParticipantRole::Spectator->value,
            'notes' => 'User notes',
        ];

        $dto = RegisterParticipantDTO::fromArray($data);

        $this->assertEquals('game-table-999', $dto->gameTableId);
        $this->assertEquals('user-111', $dto->userId);
        $this->assertEquals(ParticipantRole::Spectator, $dto->role);
        $this->assertEquals('User notes', $dto->notes);
        $this->assertFalse($dto->isGuest());
    }

    public function test_from_array_uses_default_role_when_not_provided(): void
    {
        $data = [
            'game_table_id' => 'game-table-123',
            'user_id' => 'user-456',
        ];

        $dto = RegisterParticipantDTO::fromArray($data);

        $this->assertEquals(ParticipantRole::Player, $dto->role);
    }
}
