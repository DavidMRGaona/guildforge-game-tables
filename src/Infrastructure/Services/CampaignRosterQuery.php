<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\CampaignSessionBoxDTO;
use Modules\GameTables\Application\DTOs\JoinBoxDTO;
use Modules\GameTables\Application\DTOs\RosterPageDTO;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Application\Services\CampaignRosterQueryInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final readonly class CampaignRosterQuery implements CampaignRosterQueryInterface
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private CampaignRosterRepositoryInterface $roster,
        private CampaignJoinRequestRepositoryInterface $requests,
        private CampaignDirectionInterface $direction,
        private MembershipCheckerInterface $membership,
        private GameTableRepositoryInterface $gameTables,
        private ParticipantRepositoryInterface $participants,
        private SeatCounter $seats,
        private GameTableSettingsReader $settings,
    ) {}

    public function rosterPage(string $campaignId, DateTimeImmutable $now): RosterPageDTO
    {
        $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));
        $rosterCount = $this->roster->count($campaign->id);
        $sessions = $this->upcomingSessions($campaign->id, $now);

        // Every session's rows and taken seats in one read each, however many sessions there are
        $rowsBySession = $this->rowsByUser($sessions);
        $takenSeats = $this->takenSeats($sessions);

        $players = [];
        foreach ($this->members($campaign->id) as $member) {
            $states = [];
            foreach ($sessions as $session) {
                $states[] = [
                    'game_table_id' => $session->id->value,
                    ...$this->playerState($rowsBySession[$session->id->value][$member['user_id']] ?? null, $session, $now),
                ];
            }

            $players[] = [...$member, 'sessions' => $states];
        }

        return new RosterPageDTO(
            campaign: [
                'id' => $campaign->id->value,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'status_label' => $campaign->status->label(),
                'is_finished' => $campaign->isFinished(),
                'accepts_new_players' => $campaign->acceptsNewPlayers,
                'roster_access' => $campaign->rosterAccess->value,
                'max_players' => $campaign->maxPlayers,
                'roster_count' => $rosterCount,
            ],
            rosterAccessOptions: RosterAccess::options(),
            maxPlayersLimit: $this->settings->maxPlayersLimit(),
            requests: $this->pendingRequests($campaign),
            sessions: array_map(fn (GameTable $session): array => [
                'game_table_id' => $session->id->value,
                'title' => $session->title,
                'slug' => $session->slug,
                'starts_at' => $session->timeSlot->startsAt->format('c'),
                'date_short' => $session->timeSlot->startsAt->format('d/m'),
                'date' => $session->timeSlot->startsAt->format('d/m/Y H:i'),
                'seats_taken' => $takenSeats[$session->id->value] ?? 0,
                'max_players' => $session->maxPlayers,
                // As the server checks it when an absence is cleared
                'has_room' => $session->hasCapacity($takenSeats[$session->id->value] ?? 0),
            ], $sessions),
            players: $players,
            canAdd: ! $campaign->isFinished() && $campaign->hasRoomFor($rosterCount),
            removalNotice: $this->settings->isCampaignNotificationEnabled('roster_removed'),
        );
    }

    public function joinBox(string $campaignId, ?string $userId): JoinBoxDTO
    {
        $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

        if ($userId !== null) {
            // The campaign page shows published campaigns only, whose roster their directors may
            // open (CampaignPolicy::manageRoster); the requests awaiting an answer are theirs alone
            if ($this->direction->isDirector($campaignId, $userId)) {
                return new JoinBoxDTO(
                    'director',
                    rosterUrl: route('campaigns.roster', $campaignId),
                    pendingRequests: $this->requests->pendingCountsFor([$campaign->id])[$campaignId] ?? 0,
                );
            }

            if ($this->roster->contains($campaign->id, $userId)) {
                return new JoinBoxDTO('member');
            }

            $pending = $this->requests->findPending($campaign->id, $userId);
            if ($pending !== null) {
                return new JoinBoxDTO('pending', requestedAt: $pending->createdAt?->format('d/m/Y'));
            }
        }

        return new JoinBoxDTO(match (true) {
            $campaign->isFinished() => 'finished',
            ! $campaign->canAcceptNewPlayers() => 'not_accepting',
            ! $campaign->hasRoomFor($this->roster->count($campaign->id)) => 'full',
            $userId === null => 'guest',
            $campaign->rosterAccess === RosterAccess::MembersOnly && ! $this->membership->isActiveMember($userId) => 'members_only',
            default => 'can_request',
        });
    }

    public function sessionBox(string $gameTableId, ?string $userId, DateTimeImmutable $now): ?CampaignSessionBoxDTO
    {
        $session = Str::isUuid($gameTableId) ? $this->gameTables->find(new GameTableId($gameTableId)) : null;
        $campaign = $session?->campaignId !== null ? $this->campaigns->find($session->campaignId) : null;

        if ($session === null || $campaign === null) {
            return null;
        }

        // Closed to outsiders as the registration rules read it (EligibilityService)
        $closed = ! $campaign->canAcceptNewPlayers();
        $slug = $campaign->isPublished ? $campaign->slug : null;

        if ($userId === null || ! $this->roster->contains($campaign->id, $userId)) {
            return new CampaignSessionBoxDTO($campaign->title, $slug, $closed, false, null, null, false, null);
        }

        $row = $this->participants->findByTableAndUser($session->id, $userId);
        $state = match (true) {
            $row === null => 'none',
            $row->isOnWaitingList() => 'waiting',
            $row->isActive() => 'enrolled',
            $row->isSessionAbsence() => 'absent',
            default => 'none',
        };

        // The roster path of a registration takes a member with no row or a cancelled one
        // (RegistrationService::register()); a rejected or no-show row follows the table rules
        $refusal = null;
        $canRejoin = false;
        if ($row === null || $row->isCancelled()) {
            $refusal = match (true) {
                ! $session->admitsRosterEnrollment($now) => (string) __('game-tables::messages.errors.registration_closed'),
                ! $session->hasCapacity($this->seats->takenPlayerSeats($session)) => (string) __('game-tables::messages.errors.table_full'),
                default => null,
            };
            $canRejoin = $refusal === null;
        }

        return new CampaignSessionBoxDTO(
            campaignTitle: $campaign->title,
            campaignSlug: $slug,
            closed: $closed,
            isRosterMember: true,
            rosterState: $state,
            waitingListPosition: $state === 'waiting' && $row !== null ? $this->participants->getWaitingListRank($row->id) : null,
            canRejoin: $canRejoin,
            rejoinRefusal: $refusal,
        );
    }

    /**
     * Published, not started and not over, by start.
     *
     * @return list<GameTable>
     */
    private function upcomingSessions(CampaignId $campaignId, DateTimeImmutable $now): array
    {
        return array_values(array_filter(
            $this->gameTables->getByCampaign($campaignId),
            fn (GameTable $session): bool => $session->isPublished
                && ! $session->isFinal()
                && ! $session->timeSlot->hasStarted($now),
        ));
    }

    /**
     * The sessions' rows by session and user, in one read.
     *
     * @param  list<GameTable>  $sessions
     * @return array<string, array<string, Participant>>
     */
    private function rowsByUser(array $sessions): array
    {
        $rows = [];
        $ids = array_map(fn (GameTable $session): GameTableId => $session->id, $sessions);
        foreach ($this->participants->getByGameTables($ids) as $row) {
            if ($row->userId !== null) {
                $rows[$row->gameTableId->value][$row->userId] = $row;
            }
        }

        return $rows;
    }

    /**
     * The taken player seats of each session, counted in one query (SeatCounter's list counts).
     *
     * @param  list<GameTable>  $sessions
     * @return array<string, int>
     */
    private function takenSeats(array $sessions): array
    {
        if ($sessions === []) {
            return [];
        }

        $ids = array_map(fn (GameTable $session): string => $session->id->value, $sessions);
        $taken = [];
        foreach (SeatCounter::withPlayerSeatCounts(GameTableModel::query()->whereIn('id', $ids))->get() as $model) {
            $taken[(string) $model->id] = $this->seats->takenPlayerSeatsOf($model);
        }

        return $taken;
    }

    /**
     * A chip toggles what the roster actions accept: an absence is marked on a roster row before
     * the start (the sessions listed have not started) and cleared while the session admits
     * roster enrolment.
     *
     * @return array{state: string, can_toggle: bool}
     */
    private function playerState(?Participant $row, GameTable $session, DateTimeImmutable $now): array
    {
        return match (true) {
            $row === null => ['state' => 'none', 'can_toggle' => false],
            $row->isOnWaitingList() => ['state' => 'waiting', 'can_toggle' => $row->fromRoster],
            $row->isActive() => ['state' => 'going', 'can_toggle' => $row->fromRoster],
            $row->isSessionAbsence() => ['state' => 'absent', 'can_toggle' => $session->admitsRosterEnrollment($now)],
            default => ['state' => 'none', 'can_toggle' => false],
        };
    }

    /**
     * The roster in joining order, without anonymized accounts (as the roster repository reads it).
     *
     * @return list<array{user_id: string, name: string, joined_at: string|null}>
     */
    private function members(CampaignId $campaignId): array
    {
        return DB::table('gametables_campaign_players as p')
            ->join('users', 'users.id', '=', 'p.user_id')
            ->whereNull('users.anonymized_at')
            ->where('p.campaign_id', $campaignId->value)
            ->orderBy('p.created_at')
            ->orderBy('p.user_id')
            ->get(['p.user_id', 'users.name', 'p.created_at'])
            ->map(fn (object $row): array => [
                'user_id' => (string) $row->user_id,
                'name' => (string) $row->name,
                'joined_at' => $row->created_at !== null ? CarbonImmutable::parse((string) $row->created_at)->format('d/m/Y') : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Oldest first; the repository leaves anonymized requesters out.
     *
     * @return list<array{id: string, user_name: string, message: string|null, created_at: string|null}>
     */
    private function pendingRequests(Campaign $campaign): array
    {
        $requests = $this->requests->pendingFor($campaign->id);
        $names = UserModel::query()
            ->whereIn('id', array_map(fn (CampaignJoinRequest $request): string => $request->userId, $requests))
            ->pluck('name', 'id');

        return array_map(fn (CampaignJoinRequest $request): array => [
            'id' => $request->id->value,
            'user_name' => (string) ($names[$request->userId] ?? ''),
            'message' => $request->message,
            'created_at' => $request->createdAt?->format('d/m/Y'),
        ], $requests);
    }
}
