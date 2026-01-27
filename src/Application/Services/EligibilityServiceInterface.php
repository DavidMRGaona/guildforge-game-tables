<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameTable;

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
    ): void;

    /**
     * Check if a user can register (non-throwing version).
     *
     * @return array{eligible: bool, reason: string|null}
     */
    public function canRegister(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
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
     * Check if a user can register by table ID (for API use).
     *
     * @return array{eligible: bool, reason: string|null, message: string|null, canRegisterAt: string|null}
     */
    public function canRegisterById(string $gameTableId, string $userId): array;

    /**
     * Check if a guest can register for a game table.
     *
     * @return array{eligible: bool, reason: string|null, message: string|null}
     */
    public function canGuestRegister(string $gameTableId, string $email): array;
}
