<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use DateMalformedStringException;
use DateTimeImmutable;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\MembersOnlyException;
use Modules\GameTables\Domain\Exceptions\RegistrationClosedException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

final readonly class EligibilityService implements EligibilityServiceInterface
{
    private SeatCounter $seats;

    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private ?MembershipCheckerInterface $membershipChecker = null,
        private GameTableSettingsReader $settings = new GameTableSettingsReader,
        private ?CampaignRepositoryInterface $campaigns = null,
        private ?CampaignRosterRepositoryInterface $roster = null,
    ) {
        $this->seats = new SeatCounter($participantRepository, $settings);
    }

    public function checkEligibility(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
        ParticipantRole $role = ParticipantRole::Player,
    ): void {
        $this->evaluate($gameTable, $userId, $isMember, $now, $role);
    }

    /**
     * Every rule of a user registration; refusals are exceptions.
     *
     * @return bool whether the registration goes to the waiting list
     *
     * @throws RegistrationClosedException
     * @throws MembersOnlyException
     * @throws AlreadyRegisteredException
     * @throws TableFullException
     */
    private function evaluate(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?DateTimeImmutable $now,
        ParticipantRole $role,
    ): bool {
        $now ??= CarbonImmutable::now()->toDateTimeImmutable();

        // Check if registration is open
        if (! $gameTable->canRegister()) {
            throw $this->closedByStatus($gameTable);
        }

        // Invite tables are filled by the organisation from the panel: nobody signs up by themselves,
        // whatever the dates, the membership or the seats
        if ($gameTable->requiresInvitation()) {
            throw RegistrationClosedException::invitationOnly($gameTable->id->value);
        }

        // The sessions of a campaign closed to new players are for its roster only
        if ($this->closedToOutsiders($gameTable, $userId)) {
            throw RegistrationClosedException::rosterOnly($gameTable->id->value);
        }

        // The time of the table closes registrations whatever its dates and the early access
        $this->ensureTableTimeAdmits($gameTable, $now);

        // Check registration window
        // Members' early access is part of the membership integration setting
        if (! $gameTable->canUserRegister($isMember, $now, app(TimezoneServiceInterface::class)->displayTimezone(), $this->settings->isMembershipIntegrationEnabled())) {
            if ($gameTable->requiresMembership() && ! $isMember) {
                throw MembersOnlyException::forTable($gameTable->id->value);
            }

            if (! $gameTable->isPublicRegistrationOpen($now)) {
                if ($gameTable->registrationOpensAt instanceof DateTimeImmutable && $now < $gameTable->registrationOpensAt) {
                    throw RegistrationClosedException::notYetOpen($gameTable->id->value);
                }
                throw RegistrationClosedException::alreadyClosed($gameTable->id->value);
            }
        }

        // Check if already registered
        $existing = $this->participantRepository->findByTableAndUser($gameTable->id, $userId);
        if ($existing instanceof Participant && $existing->isActive()) {
            throw AlreadyRegisteredException::forTable($userId, $gameTable->id->value);
        }

        return $this->checkSeats($gameTable, $role, $now);
    }

    /**
     * Spectators need a free spectator seat, whatever the player seats; they have no waiting list.
     * Players need a free player seat or, when the setting is on and the waiting list is still
     * open (not once the table has started, also on tables that admit registrations in play), go
     * to the waiting list.
     * Taken seats are those of SeatCounter, the count the table page shows.
     *
     * @return bool whether a player registration goes to the waiting list
     *
     * @throws TableFullException
     */
    private function checkSeats(GameTable $gameTable, ParticipantRole $role, DateTimeImmutable $now): bool
    {
        if ($role === ParticipantRole::Spectator) {
            $this->checkSpectatorSeats($gameTable);

            return false;
        }

        if ($gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable))) {
            return false;
        }

        if (! $this->settings->isWaitingListEnabled() || ! $gameTable->isWaitingListOpen($now)) {
            throw TableFullException::forTable($gameTable->id->value);
        }

        return true;
    }

    /**
     * A table closes its registrations at its start, or at its end when it admits registrations
     * in play, whatever its configured dates.
     *
     * @throws RegistrationClosedException
     */
    private function ensureTableTimeAdmits(GameTable $gameTable, DateTimeImmutable $now): void
    {
        if (! $gameTable->isPastRegistrationDeadline($now)) {
            return;
        }

        throw $gameTable->acceptsRegistrationsInProgress
            ? RegistrationClosedException::tableEnded($gameTable->id->value)
            : RegistrationClosedException::tableStarted($gameTable->id->value);
    }

    /**
     * Why a table whose status admits no registrations is closed (GameTable::closedStatusReason()):
     * a published table in play or finished says it has started or ended, as the time says once
     * the start has passed.
     */
    private function closedByStatus(GameTable $gameTable): RegistrationClosedException
    {
        $id = $gameTable->id->value;

        return match ($gameTable->closedStatusReason()) {
            'table_started' => RegistrationClosedException::tableStarted($id),
            'table_ended' => RegistrationClosedException::tableEnded($id),
            default => RegistrationClosedException::forTable($id),
        };
    }

    /**
     * @throws TableFullException
     */
    private function checkSpectatorSeats(GameTable $gameTable): void
    {
        if ($gameTable->availableSpectatorSlots($this->seats->takenSpectatorSeats($gameTable)) === 0) {
            throw TableFullException::forSpectators($gameTable->id->value);
        }
    }

    /**
     * @return array{eligible: bool, reason: string|null, waitingList: bool}
     */
    public function canRegister(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
        ParticipantRole $role = ParticipantRole::Player,
    ): array {
        try {
            $waitingList = $this->evaluate($gameTable, $userId, $isMember, $now, $role);

            return ['eligible' => true, 'reason' => null, 'waitingList' => $waitingList];
        } catch (RegistrationClosedException|MembersOnlyException|AlreadyRegisteredException|TableFullException $e) {
            return ['eligible' => false, 'reason' => $e->reason, 'waitingList' => false];
        }
    }

    /**
     * @return array{eligible: bool, reason: string|null, message: string|null, canRegisterAt: string|null, waitingList: bool, spectator: array{eligible: bool, reason: string|null, message: string|null}}
     *
     * @throws DateMalformedStringException
     */
    public function canRegisterById(string $gameTableId, string $userId): array
    {
        $timezone = app(TimezoneServiceInterface::class);

        $tableId = new GameTableId($gameTableId);
        $gameTable = $this->gameTableRepository->find($tableId);

        if (! $gameTable instanceof GameTable) {
            return [
                'eligible' => false,
                'reason' => 'not_found',
                'message' => __('game-tables::messages.errors.table_not_found'),
                'canRegisterAt' => null,
                'waitingList' => false,
                'spectator' => $this->refusal('not_found'),
            ];
        }

        $isMember = $this->checkMembership($userId);
        $userAge = null; // Age checking would require user profile data

        $now = CarbonImmutable::now()->toDateTimeImmutable();
        // The same calculation as ensureUserCanRegister(), once per role the page offers
        $result = $this->canRegister($gameTable, $userId, $isMember, $userAge, $now);
        $spectator = $this->canRegister($gameTable, $userId, $isMember, $userAge, $now, ParticipantRole::Spectator);

        // Determine when user can register if early access is in effect
        $canRegisterAt = null;
        // No opening date to announce when waiting can never reopen the table: nobody signs up
        // by themselves (invitation only), or it has started or ended
        $neverReopens = in_array($result['reason'], ['invitation_only', 'table_started', 'table_ended'], true);
        if (! $result['eligible'] && ! $neverReopens && $gameTable->registrationOpensAt instanceof DateTimeImmutable) {
            if ($isMember && $gameTable->membersEarlyAccessDays > 0 && $this->settings->isMembershipIntegrationEnabled()) {
                $earlyAccessDate = $gameTable->memberEarlyAccessStartsAt($timezone->displayTimezone());
                if ($earlyAccessDate instanceof DateTimeImmutable && $now < $earlyAccessDate) {
                    $canRegisterAt = $timezone->iso($earlyAccessDate);
                }
            } elseif ($now < $gameTable->registrationOpensAt) {
                $canRegisterAt = $timezone->iso($gameTable->registrationOpensAt);
            }
        }

        return [
            'eligible' => $result['eligible'],
            'reason' => $result['reason'],
            'message' => $result['reason'] ? $this->translateReason($result['reason']) : null,
            'canRegisterAt' => $canRegisterAt,
            'waitingList' => $result['waitingList'],
            'spectator' => [
                'eligible' => $spectator['eligible'],
                'reason' => $spectator['reason'],
                'message' => $spectator['reason'] !== null ? $this->translateReason($spectator['reason']) : null,
            ],
        ];
    }

    /**
     * @return array{eligible: bool, reason: string|null, message: string|null, waitingList: bool, spectator: array{eligible: bool, reason: string|null, message: string|null}}
     */
    public function guestEligibility(string $gameTableId): array
    {
        // The email is not known yet: the server checks it when the form is sent
        $player = $this->canGuestRegister($gameTableId, null);
        $spectator = $this->canGuestRegister($gameTableId, null, ParticipantRole::Spectator);

        return [
            ...$player,
            'spectator' => [
                'eligible' => $spectator['eligible'],
                'reason' => $spectator['reason'],
                'message' => $spectator['message'],
            ],
        ];
    }

    /**
     * @return array{eligible: false, reason: string, message: string}
     */
    private function refusal(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason, 'message' => $this->translateReason($reason)];
    }

    /**
     * The table belongs to a campaign that takes no new players (on hold, closed to newcomers,
     * finished) and the person is not on its roster. Without a user (a guest) nobody is on it.
     */
    private function closedToOutsiders(GameTable $gameTable, ?string $userId): bool
    {
        if (! $gameTable->campaignId instanceof CampaignId || ! $this->campaigns instanceof CampaignRepositoryInterface || ! $this->roster instanceof CampaignRosterRepositoryInterface) {
            return false;
        }

        $campaign = $this->campaigns->find($gameTable->campaignId);

        if (! $campaign instanceof Campaign || $campaign->canAcceptNewPlayers()) {
            return false;
        }

        return $userId === null || ! $this->roster->contains($gameTable->campaignId, $userId);
    }

    private function checkMembership(string $userId): bool
    {
        return $this->membershipChecker?->isActiveMember($userId) ?? false;
    }

    private function translateReason(string $reason): string
    {
        return match ($reason) {
            'registration_closed' => __('game-tables::messages.errors.registration_closed'),
            'registration_not_open' => __('game-tables::messages.errors.registration_not_open'),
            'members_only' => __('game-tables::messages.errors.members_only'),
            'invitation_only' => __('game-tables::messages.errors.invitation_only'),
            'campaign_roster_only' => __('game-tables::messages.errors.campaign_roster_only'),
            'table_started' => __('game-tables::messages.errors.table_started'),
            'table_ended' => __('game-tables::messages.errors.table_ended'),
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
     * @return array{eligible: bool, reason: string|null, message: string|null, waitingList: bool}
     */
    public function canGuestRegister(string $gameTableId, ?string $email, ParticipantRole $role = ParticipantRole::Player): array
    {
        $tableId = new GameTableId($gameTableId);
        $gameTable = $this->gameTableRepository->find($tableId);

        if (! $gameTable instanceof GameTable) {
            return $this->guestRefusal('not_found');
        }

        // Invite tables are filled by the organisation from the panel: the visitor is told so, as a
        // signed-in user is, instead of being sent to log in
        if ($gameTable->requiresInvitation()) {
            return $this->guestRefusal('invitation_only');
        }

        // Members-only tables admit no guests
        if ($gameTable->requiresMembership()) {
            return $this->guestRefusal('guests_not_allowed');
        }

        // A guest is always an outsider to the roster of a closed campaign
        if ($this->closedToOutsiders($gameTable, null)) {
            return $this->guestRefusal('campaign_roster_only');
        }

        // Check if registration is open
        if (! $gameTable->canRegister()) {
            return $this->guestRefusal($this->closedByStatus($gameTable)->reason);
        }

        // The clock RegistrationService sets the guest's confirmation deadline with
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        // The time of the table closes registrations whatever its dates
        try {
            $this->ensureTableTimeAdmits($gameTable, $now);
        } catch (RegistrationClosedException $e) {
            return $this->guestRefusal($e->reason);
        }

        // Check registration window for public (guests don't get early access)
        if (! $gameTable->isPublicRegistrationOpen($now)) {
            if ($gameTable->registrationOpensAt instanceof DateTimeImmutable && $now < $gameTable->registrationOpensAt) {
                return $this->guestRefusal('registration_not_open');
            }

            return $this->guestRefusal('registration_closed');
        }

        // Check if a guest with this email is already registered (or waiting)
        if ($email !== null) {
            $existing = $this->participantRepository->findByTableAndEmail($tableId, $email);
            if ($existing instanceof Participant && $existing->isActive()) {
                return $this->guestRefusal('guest_already_registered');
            }
        }

        try {
            $waitingList = $this->checkSeats($gameTable, $role, $now);
        } catch (TableFullException $e) {
            return $this->guestRefusal($e->reason);
        }

        return [
            'eligible' => true,
            'reason' => null,
            'message' => null,
            'waitingList' => $waitingList,
        ];
    }

    /**
     * @return array{eligible: false, reason: string, message: string, waitingList: false}
     */
    private function guestRefusal(string $reason): array
    {
        return [...$this->refusal($reason), 'waitingList' => false];
    }

    public function ensureUserCanRegister(string $gameTableId, string $userId, ParticipantRole $role = ParticipantRole::Player): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($gameTableId));

        if (! $gameTable instanceof GameTable) {
            throw RegistrationNotAllowedException::because('not_found', $this->translateReason('not_found'));
        }

        // The same check that decides what the table page offers
        $result = $this->canRegister($gameTable, $userId, $this->checkMembership($userId), null, CarbonImmutable::now()->toDateTimeImmutable(), $role);

        if (! $result['eligible']) {
            $reason = $result['reason'] ?? 'registration_closed';

            throw RegistrationNotAllowedException::because($reason, $this->translateReason($reason));
        }
    }

    public function ensureGuestCanRegister(string $gameTableId, ?string $email, ParticipantRole $role = ParticipantRole::Player): void
    {
        $result = $this->canGuestRegister($gameTableId, $email, $role);

        if (! $result['eligible']) {
            $reason = $result['reason'] ?? 'registration_closed';

            throw RegistrationNotAllowedException::because($reason, $result['message'] ?? $this->translateReason($reason));
        }
    }

    public function userRulesRefusal(string $gameTableId, string $userId, ParticipantRole $role = ParticipantRole::Player, ?DateTimeImmutable $now = null): ?string
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($gameTableId));

        if (! $gameTable instanceof GameTable) {
            return 'not_found';
        }

        $result = $this->canRegister($gameTable, $userId, $this->checkMembership($userId), null, $now ?? CarbonImmutable::now()->toDateTimeImmutable(), $role);

        if ($result['eligible'] || in_array($result['reason'], ['table_full', 'spectators_full'], true)) {
            return null;
        }

        return $result['reason'];
    }
}
