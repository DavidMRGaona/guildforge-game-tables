<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Enums\ParticipantRole;

final readonly class RegisterParticipantDTO
{
    public function __construct(
        public string $gameTableId,
        public ?string $userId,
        public ParticipantRole $role = ParticipantRole::Player,
        public ?string $notes = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            gameTableId: $data['game_table_id'],
            userId: $data['user_id'] ?? null,
            role: isset($data['role'])
                ? ($data['role'] instanceof ParticipantRole ? $data['role'] : ParticipantRole::from($data['role']))
                : ParticipantRole::Player,
            notes: $data['notes'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }
}
