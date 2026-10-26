<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * The direction's roster page of a campaign. Session states of a player: going (confirmed or
 * pending), waiting, absent or none.
 *
 * @phpstan-type RosterCampaign array{id: string, title: string, slug: string, status_label: string, is_finished: bool, accepts_new_players: bool, roster_access: string, max_players: int|null, roster_count: int}
 * @phpstan-type RosterRequest array{id: string, user_name: string, message: string|null, created_at: string|null}
 * @phpstan-type RosterInvitation array{id: string, email: string, inviter_name: string, send_count: int, expires_at: string, can_resend: bool}
 * @phpstan-type RosterSession array{game_table_id: string, title: string, slug: string, starts_at: string|null, date_short: string, date: string}
 * @phpstan-type RosterPlayerSession array{game_table_id: string, state: string, can_toggle: bool}
 * @phpstan-type RosterPlayer array{user_id: string, name: string, joined_at: string|null, sessions: list<RosterPlayerSession>}
 */
final readonly class RosterPageDTO
{
    /**
     * @param  RosterCampaign  $campaign
     * @param  array<string, string>  $rosterAccessOptions
     * @param  list<RosterRequest>  $requests
     * @param  list<RosterInvitation>  $invitations
     * @param  list<RosterSession>  $sessions
     * @param  list<RosterPlayer>  $players
     */
    public function __construct(
        public array $campaign,
        public array $rosterAccessOptions,
        public int $maxPlayersLimit,
        public array $requests,
        public array $sessions,
        public array $players,
        public bool $canAdd,
        // Whether removing a player emails them (the roster_removed notice is on)
        public bool $removalNotice,
        public array $invitations = [],
        // More invitations pending than free seats (false without a maximum)
        public bool $moreInvitationsThanSeats = false,
    ) {}

    /**
     * @return array{campaign: RosterCampaign, roster_access_options: array<string, string>, max_players_limit: int, requests: list<RosterRequest>, sessions: list<RosterSession>, players: list<RosterPlayer>, can_add: bool, removal_notice: bool, invitations: list<RosterInvitation>, more_invitations_than_seats: bool}
     */
    public function toArray(): array
    {
        return [
            'campaign' => $this->campaign,
            'roster_access_options' => $this->rosterAccessOptions,
            'max_players_limit' => $this->maxPlayersLimit,
            'requests' => $this->requests,
            'sessions' => $this->sessions,
            'players' => $this->players,
            'can_add' => $this->canAdd,
            'removal_notice' => $this->removalNotice,
            'invitations' => $this->invitations,
            'more_invitations_than_seats' => $this->moreInvitationsThanSeats,
        ];
    }
}
