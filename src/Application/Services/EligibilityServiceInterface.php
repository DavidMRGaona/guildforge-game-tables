<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\ParticipantRole;

interface EligibilityServiceInterface
{
    /**
     * Check if a user is eligible to register for a game table.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\RegistrationClosedException
     * @throws \Modules\GameTables\Domain\Exceptions\MembersOnlyException
     * @throws \Modules\GameTables\Domain\Exceptions\MinimumAgeException
     * @throws \Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException
     * @throws \Modules\GameTables\Domain\Exceptions\TableFullException
     */
    public function checkEligibility(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
        ParticipantRole $role = ParticipantRole::Player,
    ): void;

    /**
     * Check if a user can register (non-throwing version). An eligible player goes to the
     * waiting list (waitingList) when the player seats are taken and the waiting list is on.
     *
     * @return array{eligible: bool, reason: string|null, waitingList: bool}
     */
    public function canRegister(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
        ParticipantRole $role = ParticipantRole::Player,
    ): array;

    /**
     * Check if the table has capacity for more players.
     */
    public function hasCapacity(string $gameTableId): bool;

    /**
     * Check if the table has capacity for more spectators.
     */
    public function hasSpectatorCapacity(string $gameTableId): bool;

    /**
     * What the table page offers a user: the player registration (or the waiting list) and the
     * spectator registration, with the same rules as ensureUserCanRegister().
     *
     * @return array{eligible: bool, reason: string|null, message: string|null, canRegisterAt: string|null, waitingList: bool, spectator: array{eligible: bool, reason: string|null, message: string|null}}
     */
    public function canRegisterById(string $gameTableId, string $userId): array;

    /**
     * What the table page offers a visitor without an account, with the same rules as
     * ensureGuestCanRegister() except the email, which is not known until the form is sent.
     *
     * @return array{eligible: bool, reason: string|null, message: string|null, waitingList: bool, spectator: array{eligible: bool, reason: string|null, message: string|null}}
     */
    public function guestEligibility(string $gameTableId): array;

    /**
     * Check if a guest can register for a game table (a null email skips the duplicate check).
     *
     * @return array{eligible: bool, reason: string|null, message: string|null, waitingList: bool}
     */
    public function canGuestRegister(string $gameTableId, ?string $email, ParticipantRole $role = ParticipantRole::Player): array;

    /**
     * Refuse a user registration the table page would not offer.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException
     */
    public function ensureUserCanRegister(string $gameTableId, string $userId, ParticipantRole $role = ParticipantRole::Player): void;

    /**
     * Refuse a guest registration the table page would not offer.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException
     */
    public function ensureGuestCanRegister(string $gameTableId, ?string $email, ParticipantRole $role = ParticipantRole::Player): void;
}
