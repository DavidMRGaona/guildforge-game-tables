export type ParticipantStatus = 'pending' | 'confirmed' | 'waiting_list' | 'cancelled' | 'rejected' | 'no_show' | 'expired';
export type ParticipantRole = 'player' | 'spectator';

/** Whether one role can register, and why not. */
export interface RoleEligibility {
    eligible: boolean;
    reason: string | null;
    message: string | null;
}

/** What the table page offers a signed-in user: the server applies the same rules on submit. */
export interface EligibilityResponse extends RoleEligibility {
    canRegisterAt: string | null;
    /** An eligible player registration goes to the waiting list (player seats taken). */
    waitingList: boolean;
    /** Spectators only need a free spectator seat; they have no waiting list. */
    spectator: RoleEligibility;
    /** The user's registration can still be cancelled by them (active, table not started). */
    canCancel: boolean;
}

/** What the guest form says before it is sent: a place held until the deadline, or not guaranteed. */
export interface GuestConfirmationNotice {
    mode: 'hold' | 'wait';
    /** ISO 8601, only when the place is held */
    deadline: string | null;
}

/** What the table page offers a visitor without an account (the email is checked on submit). */
export interface GuestEligibility extends RoleEligibility {
    waitingList: boolean;
    spectator: RoleEligibility;
    confirmation?: GuestConfirmationNotice | null;
}

export interface ParticipantData {
    id: string;
    gameTableId: string;
    userId: string;
    userName: string;
    role: ParticipantRole;
    roleLabel: string;
    status: ParticipantStatus;
    statusLabel: string;
    statusColor: string;
    waitingListPosition: number | null;
    notes: string | null;
    confirmedAt: string | null;
    createdAt: string;
}

/** A roster member's place in a campaign session (null for everyone else). */
export type CampaignSessionRosterState = 'enrolled' | 'waiting' | 'absent' | 'none';

/** The campaign side of a session page (CampaignSessionBoxDTO), null for tables without campaign. */
export interface CampaignSessionInfo {
    campaign_title: string;
    // Null while the campaign is not published: it has no public page
    campaign_slug: string | null;
    // Closed to people outside the roster
    closed: boolean;
    is_roster_member: boolean;
    roster_state: CampaignSessionRosterState | null;
    // The place among those waiting
    waiting_list_position: number | null;
    can_rejoin: boolean;
    // Why a member without a seat cannot take it back now
    rejoin_refusal: string | null;
}

export interface RegisterRequest {
    role?: ParticipantRole | undefined;
    notes?: string | undefined;
}

export interface RegistrationResponse {
    data: ParticipantData;
    message: string;
}

/**
 * Get the color name for a participant status.
 */
export function getStatusColor(status: ParticipantStatus): string {
    switch (status) {
        case 'confirmed':
            return 'green';
        case 'pending':
            return 'yellow';
        case 'waiting_list':
            return 'blue';
        case 'cancelled':
            return 'gray';
        case 'rejected':
            return 'red';
        case 'no_show':
            return 'orange';
        default:
            return 'gray';
    }
}

/**
 * Check if a status is considered active (confirmed).
 */
export function isActiveStatus(status: ParticipantStatus): boolean {
    return status === 'confirmed';
}

/**
 * Check if a status is in a waiting state (pending or waiting_list).
 */
export function isWaitingStatus(status: ParticipantStatus): boolean {
    return status === 'waiting_list' || status === 'pending';
}

/**
 * Check if a status is final (cancelled, rejected, no_show, expired).
 */
export function isFinalStatus(status: ParticipantStatus): boolean {
    return status === 'cancelled' || status === 'rejected' || status === 'no_show' || status === 'expired';
}
