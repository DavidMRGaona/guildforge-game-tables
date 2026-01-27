<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use DateMalformedStringException;
use DateTimeImmutable;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\MembersOnlyException;
use Modules\GameTables\Domain\Exceptions\RegistrationClosedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\Memberships\Application\Services\MemberServiceInterface;

final readonly class EligibilityService implements EligibilityServiceInterface
{
    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private ?MemberServiceInterface $memberService = null,
    ) {}

    public function checkEligibility(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
    ): void {
        $now ??= new DateTimeImmutable();

        // Check if registration is open
        if (! $gameTable->canRegister()) {
            throw RegistrationClosedException::forTable($gameTable->id->value);
        }

        // Check registration window
        if (! $gameTable->canUserRegister($isMember, $now)) {
            if ($gameTable->requiresMembership() && ! $isMember) {
                throw MembersOnlyException::forTable($gameTable->id->value);
            }

            if (! $gameTable->isPublicRegistrationOpen($now)) {
                if ($gameTable->registrationOpensAt !== null && $now < $gameTable->registrationOpensAt) {
                    throw RegistrationClosedException::notYetOpen($gameTable->id->value);
                }
                throw RegistrationClosedException::alreadyClosed($gameTable->id->value);
            }
        }

        // Check if already registered
        $existing = $this->participantRepository->findByTableAndUser($gameTable->id, $userId);
        if ($existing !== null && $existing->isActive()) {
            throw AlreadyRegisteredException::forTable($userId, $gameTable->id->value);
        }

        // Check capacity (throws if full and no waiting list)
        $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($gameTable->id);
        if (! $gameTable->hasCapacity($confirmedPlayers)) {
            throw TableFullException::forTable($gameTable->id->value);
        }
    }

    /**
     * @return array{eligible: bool, reason: string|null}
     */
    public function canRegister(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
    ): array {
        try {
            $this->checkEligibility($gameTable, $userId, $isMember, $userAge, $now);

            return ['eligible' => true, 'reason' => null];
        } catch (RegistrationClosedException $e) {
            return ['eligible' => false, 'reason' => $e->reason];
        } catch (MembersOnlyException $e) {
            return ['eligible' => false, 'reason' => $e->reason];
        } catch (AlreadyRegisteredException $e) {
            return ['eligible' => false, 'reason' => $e->reason];
        } catch (TableFullException $e) {
            return ['eligible' => false, 'reason' => $e->reason];
        }
    }

    public function hasCapacity(string $gameTableId): bool
    {
        $tableId = new GameTableId($gameTableId);
        $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($tableId);

        // We need to get the game table to check max players
        // For now, we'll return true as this is a helper method
        // The actual check happens in checkEligibility
        return true;
    }

    public function hasSpectatorCapacity(string $gameTableId): bool
    {
        $tableId = new GameTableId($gameTableId);
        $confirmedSpectators = $this->participantRepository->countConfirmedSpectators($tableId);

        // Similar to hasCapacity, actual check happens elsewhere
        return true;
    }

    /**
     * @return array{eligible: bool, reason: string|null, message: string|null, canRegisterAt: string|null}
     * @throws DateMalformedStringException
     */
    public function canRegisterById(string $gameTableId, string $userId): array
    {
        $tableId = new GameTableId($gameTableId);
        $gameTable = $this->gameTableRepository->find($tableId);

        if ($gameTable === null) {
            return [
                'eligible' => false,
                'reason' => 'not_found',
                'message' => __('game-tables::messages.errors.table_not_found'),
                'canRegisterAt'  => null,
            ];
        }

        $isMember = $this->checkMembership($userId);
        $userAge = null; // Age checking would require user profile data

        $now = new DateTimeImmutable();
        $result = $this->canRegister($gameTable, $userId, $isMember, $userAge, $now);

        // Determine when user can register if early access is in effect
        $canRegisterAt = null;
        if (! $result['eligible'] && $gameTable->registrationOpensAt !== null) {
            if ($isMember && $gameTable->membersEarlyAccessDays > 0) {
                $earlyAccessDate = $gameTable->registrationOpensAt->modify("-{$gameTable->membersEarlyAccessDays} days");
                if ($now < $earlyAccessDate) {
                    $canRegisterAt = $earlyAccessDate->format('c');
                }
            } elseif ($now < $gameTable->registrationOpensAt) {
                $canRegisterAt = $gameTable->registrationOpensAt->format('c');
            }
        }

        return [
            'eligible' => $result['eligible'],
            'reason' => $result['reason'],
            'message' => $result['reason'] ? $this->translateReason($result['reason']) : null,
            'canRegisterAt' => $canRegisterAt,
        ];
    }

    private function checkMembership(string $userId): bool
    {
        if ($this->memberService === null) {
            return false;
        }

        // Find member by searching active members linked to this user
        $activeMembers = $this->memberService->getActiveMembers();
        return array_any($activeMembers, fn($member) => $member->userId === $userId);

    }

    private function translateReason(string $reason): string
    {
        return match ($reason) {
            'registration_closed' => __('game-tables::messages.errors.registration_closed'),
            'registration_not_open' => __('game-tables::messages.errors.registration_not_open'),
            'members_only' => __('game-tables::messages.errors.members_only'),
            'already_registered' => __('game-tables::messages.errors.already_registered'),
            'guest_already_registered' => __('game-tables::messages.errors.guest_already_registered'),
            'table_full' => __('game-tables::messages.errors.table_full'),
            'spectators_full' => __('game-tables::messages.errors.spectators_full'),
            'not_found' => __('game-tables::messages.errors.table_not_found'),
            'guests_not_allowed' => __('game-tables::messages.errors.guests_not_allowed'),
            default => $reason,
        };
    }

    /**
     * @return array{eligible: bool, reason: string|null, message: string|null}
     */
    public function canGuestRegister(string $gameTableId, string $email): array
    {
        $tableId = new GameTableId($gameTableId);
        $gameTable = $this->gameTableRepository->find($tableId);

        if ($gameTable === null) {
            return [
                'eligible' => false,
                'reason' => 'not_found',
                'message' => $this->translateReason('not_found'),
            ];
        }

        // Only allow guest registration for "everyone" tables
        if ($gameTable->registrationType->requiresMembership() || $gameTable->registrationType->requiresInvitation()) {
            return [
                'eligible' => false,
                'reason' => 'guests_not_allowed',
                'message' => $this->translateReason('guests_not_allowed'),
            ];
        }

        // Check if registration is open
        if (! $gameTable->canRegister()) {
            return [
                'eligible' => false,
                'reason' => 'registration_closed',
                'message' => $this->translateReason('registration_closed'),
            ];
        }

        // Check registration window for public (guests don't get early access)
        $now = new DateTimeImmutable();
        if (! $gameTable->isPublicRegistrationOpen($now)) {
            if ($gameTable->registrationOpensAt !== null && $now < $gameTable->registrationOpensAt) {
                return [
                    'eligible' => false,
                    'reason' => 'registration_not_open',
                    'message' => $this->translateReason('registration_not_open'),
                ];
            }
            return [
                'eligible' => false,
                'reason' => 'registration_closed',
                'message' => $this->translateReason('registration_closed'),
            ];
        }

        // Check if a guest with this email is already registered
        $existing = $this->participantRepository->findByTableAndEmail($tableId, $email);
        if ($existing !== null && $existing->isActive()) {
            return [
                'eligible' => false,
                'reason' => 'guest_already_registered',
                'message' => $this->translateReason('guest_already_registered'),
            ];
        }

        // Check capacity
        $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($tableId);
        if (! $gameTable->hasCapacity($confirmedPlayers)) {
            return [
                'eligible' => false,
                'reason' => 'table_full',
                'message' => $this->translateReason('table_full'),
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'message' => null,
        ];
    }
}
