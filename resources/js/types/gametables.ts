export interface GameSystem {
    id: string;
    name: string;
    slug: string;
    logoUrl: string | null;
}

export interface GameMaster {
    id: string;
    gameTableId: string;
    userId: string | null;
    displayName: string;
    role: string;
    roleLabel: string;
    customTitle: string | null;
    isMain: boolean;
    isNamePublic: boolean;
}

export interface CampaignGameMaster {
    id: string;
    userId: string | null;
    displayName: string;
    role: string;
    roleLabel: string;
    customTitle: string | null;
    isMain: boolean;
    isNamePublic: boolean;
}

export interface EventFilter {
    id: string;
    title: string;
    count: number;
}

export interface GameTableListItem {
    id: string;
    title: string;
    slug: string;
    gameSystemName: string;
    startsAt: string;
    durationMinutes: number;
    tableFormat: {
        value: string;
        label: string;
        color: string;
    };
    tableType: {
        value: string;
        label: string;
    };
    status: {
        value: string;
        label: string;
        color: string;
    };
    location: string | null;
    onlineUrl: string | null;
    minPlayers: number;
    maxPlayers: number;
    currentPlayers: number;
    spotsAvailable: number;
    isFull: boolean;
    isPublished: boolean;
    creatorName: string;
    mainGameMasterName: string;
    eventId: string | null;
    eventTitle: string | null;
    imagePublicId: string | null;
}

export interface Participant {
    id: string;
    gameTableId: string;
    userId: string;
    userName: string;
    role: string;
    roleLabel: string;
    roleColor: string;
    status: string;
    statusLabel: string;
    statusColor: string;
    waitingListPosition: number | null;
    notes: string | null;
    confirmedAt: string | null;
    createdAt: string;
}

export interface GameTable {
    id: string;
    title: string;
    slug: string;
    synopsis: string | null;
    gameSystemId: string;
    gameSystemName: string;
    campaignId: string | null;
    campaignTitle: string | null;
    eventId: string | null;
    eventTitle: string | null;
    createdBy: string;
    creatorName: string;
    tableType: string;
    tableTypeLabel: string;
    tableFormat: string;
    tableFormatLabel: string;
    tableFormatColor: string;
    status: string;
    statusLabel: string;
    statusColor: string;
    startsAt: string;
    durationMinutes: number;
    location: string | null;
    onlineUrl: string | null;
    minPlayers: number;
    maxPlayers: number;
    maxSpectators: number;
    minimumAge: number | null;
    language: string;
    languageLabel: string;
    experienceLevel: string | null;
    experienceLevelLabel: string | null;
    characterCreation: string | null;
    characterCreationLabel: string | null;
    genres: string[];
    tone: string | null;
    toneLabel: string | null;
    safetyTools: string[];
    contentWarnings: string[];
    customWarnings: string[];
    registrationType: string;
    registrationTypeLabel: string;
    membersEarlyAccessDays: number;
    registrationOpensAt: string | null;
    registrationClosesAt: string | null;
    effectiveRegistrationClosesAt: string | null;
    autoConfirm: boolean;
    acceptsRegistrationsInProgress: boolean;
    isPublished: boolean;
    publishedAt: string | null;
    notes: string | null;
    imagePublicId: string | null;
    gameMasters: GameMaster[];
    mainGameMasterName: string;
    currentPlayers: number;
    currentSpectators: number;
    spotsAvailable: number;
    spectatorSpotsAvailable: number;
    waitingListCount: number;
    createdAt: string;
    updatedAt: string;
}

export interface CampaignListItem {
    id: string;
    title: string;
    slug: string;
    gameSystemName: string;
    creatorName: string;
    status: string;
    statusLabel: string;
    statusColor: string;
    frequency: string | null;
    frequencyLabel: string | null;
    maxPlayers: number | null;
    currentPlayers: number;
    isRecruiting: boolean;
    acceptsNewPlayers: boolean;
    sessionCount: number | null;
    currentSession: number;
    totalSessions: number;
    imagePublicId: string | null;
    gameMasters: CampaignGameMaster[];
    mainGameMasterName: string;
}

export interface Campaign {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    gameSystemId: string;
    gameSystemName: string;
    createdBy: string;
    creatorName: string;
    status: string;
    statusLabel: string;
    statusColor: string;
    frequency: string | null;
    frequencyLabel: string | null;
    expectedDurationMonths: number | null;
    startDate: string | null;
    endDate: string | null;
    maxPlayers: number | null;
    currentPlayers: number;
    spotsAvailable: number | null;
    isRecruiting: boolean;
    acceptsNewPlayers: boolean;
    sessionCount: number | null;
    currentSession: number;
    recruitmentMessage: string | null;
    settings: string | null;
    themes: string[];
    safetyTools: string[];
    contentWarnings: string[];
    minimumAge: number | null;
    language: string;
    experienceLevel: string;
    experienceLevelLabel: string;
    totalSessions: number;
    imagePublicId: string | null;
    gameMasters: CampaignGameMaster[];
    gameTables: GameTableListItem[];
    hasActiveOrUpcomingTables: boolean;
    mainGameMasterName: string;
    createdAt: string;
    updatedAt: string;
}

export interface GameTableFilters {
    systems?: string[];
    format?: string;
    status?: string;
    event?: string;
    campaign?: string;
}

export interface ProfileParticipationFilters {
    statuses: string[];
    roles: string[];
    systems: string[];
}

export interface ProfileParticipation {
    id: string;
    gameTableId: string;
    gameTableTitle: string;
    gameTableSlug: string;
    gameTableStartsAt: string | null;
    gameSystemName: string;
    role: string;
    roleKey: string;
    roleColor: string;
    status: string;
    statusKey: string;
    statusColor: string;
    waitingListPosition: number | null;
    isUpcoming: boolean;
}

export interface ProfileGameTablesData {
    upcoming: ProfileParticipation[];
    past: ProfileParticipation[];
    total: number;
}

export interface ProfileCreatedTable {
    id: string;
    title: string;
    slug: string | null;
    gameSystemName: string;
    startsAt: string | null;
    status: string;
    statusLabel: string;
    statusColor: string;
    isPublished: boolean;
    /** Whether the author may still edit it: a draft not sent to review */
    canEdit: boolean;
    tableFormat: string;
    tableFormatLabel: string;
    eventId: string | null;
    eventTitle: string | null;
    minPlayers: number;
    maxPlayers: number;
    currentPlayers: number;
}

export interface ProfileCreatedTablesData {
    tables: ProfileCreatedTable[];
    drafts: ProfileCreatedTable[];
    total: number;
    canCreate: boolean;
}

/**
 * A director of a draft as its author edits it (FrontendCreationService::getDraftGameMasters()):
 * the author's own table, so the email and phone are included. `id` is null for an author who has
 * no director record on the table yet.
 */
export interface EditableGameMaster {
    id: string | null;
    user_id: string | null;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    custom_title: string;
    is_name_public: boolean;
    role: 'main' | 'co_gm';
}

/**
 * GameTable data as returned by GameTableResponseDTO::toArray() (snake_case keys).
 * Used for Edit form pre-population where data comes directly from DTO.
 */
export interface GameTableEditData {
    id: string;
    title: string;
    slug: string | null;
    synopsis: string | null;
    game_system_id: string;
    game_system_name: string;
    campaign_id: string | null;
    campaign_title: string | null;
    event_id: string | null;
    event_title: string | null;
    created_by: string;
    creator_name: string;
    table_type: string;
    table_type_label: string;
    table_format: string;
    table_format_label: string;
    table_format_color: string;
    status: string;
    status_label: string;
    status_color: string;
    starts_at: string | null;
    duration_minutes: number;
    location: string | null;
    online_url: string | null;
    min_players: number;
    max_players: number;
    max_spectators: number;
    minimum_age: number | null;
    language: string;
    experience_level: string | null;
    experience_level_label: string | null;
    character_creation: string | null;
    character_creation_label: string | null;
    genres: Array<{ value: string; label: string }>;
    tone: string | null;
    tone_label: string | null;
    safety_tools: Array<{ value: string; label: string }>;
    content_warnings: string[];
    custom_warnings: string[];
    registration_type: string;
    registration_type_label: string;
    members_early_access_days: number;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    auto_confirm: boolean;
    accepts_registrations_in_progress: boolean;
    is_published: boolean;
    published_at: string | null;
    notes: string | null;
    image_public_id: string | null;
    game_masters: Array<{
        id: string;
        game_table_id: string;
        user_id: string | null;
        display_name: string;
        role: string;
        role_label: string;
        custom_title: string | null;
        is_main: boolean;
        is_name_public: boolean;
    }>;
    created_at: string | null;
    updated_at: string | null;
}

export interface ProfileCreatedCampaign {
    id: string;
    title: string;
    slug: string | null;
    gameSystemName: string;
    status: string;
    statusLabel: string;
    statusColor: string;
    frequency: string | null;
    frequencyLabel: string | null;
    isPublished: boolean;
    /** Whether the author may still edit it: a draft not sent to review */
    canEdit: boolean;
    isRecruiting: boolean;
    maxPlayers: number | null;
    currentPlayers: number;
}

export interface ProfileCreatedCampaignsData {
    campaigns: ProfileCreatedCampaign[];
    drafts: ProfileCreatedCampaign[];
    total: number;
    canCreate: boolean;
}

// Re-export registration types for convenience
export type { EligibilityResponse, ParticipantData } from './registration';

/**
 * Props for the GameTables/Show page.
 */
export interface GameTableShowProps {
    table: GameTable;
    eligibility: import('./registration').EligibilityResponse | null;
    userRegistration: import('./registration').ParticipantData | null;
}

/**
 * Default values of the public table form (panel settings: Valores por defecto).
 */
export interface TableFormDefaults {
    duration_minutes: number;
    min_players: number;
    max_players: number;
    max_spectators: number;
}

/**
 * Upper bounds of the public table form (panel settings: Límites).
 */
export interface TableFormLimits {
    max_players: number;
    max_spectators: number;
    max_duration_minutes: number;
}

/**
 * A campaign the author may create sessions of (FrontendCreationService::getCreateFormData()):
 * the campaigns they direct that admit sessions, plus the one stored on an edited table.
 */
export interface SessionCampaignOption {
    id: string;
    name: string;
    game_system_id: string;
    max_players: number | null;
    roster_count: number;
}

/** What «Añadir sesión» (`/mesas/crear?campaign={id}`) fills in the table form. */
export interface TableFormPrefill {
    table_type: 'campaign_session';
    campaign_id: string;
}

/** Who may ask to join a campaign's roster (RosterAccess enum). */
export type RosterAccessValue = 'anyone' | 'members_only';

/**
 * A campaign of «Mis campañas», as returned by CampaignResponseDTO::toArray() (snake_case keys).
 * Only the keys the page reads.
 */
export interface CampaignResponseDTO {
    id: string;
    title: string;
    slug: string | null;
    game_system_name: string;
    frequency: string | null;
    frequency_label: string | null;
    max_players: number | null;
    current_players: number;
    status: string;
    status_label: string;
    status_color: string;
    frontend_creation_status: string | null;
    frontend_creation_status_label: string | null;
    is_published: boolean;
    is_recruiting: boolean;
    accepts_new_players: boolean;
    moderation_notes: string | null;
    // Why it was rejected, while the author may still change it
    rejection_reason: string | null;
    can_edit: boolean;
    // Roster: always sent, meaningful only when directed_by_viewer
    pending_requests: number;
    directed_by_viewer: boolean;
    can_add_session: boolean;
    roster_access: RosterAccessValue;
    roster_access_label: string;
    created_at: string | null;
}

/** A player's place in one upcoming session of the roster page. */
export type RosterSessionState = 'going' | 'absent' | 'waiting' | 'none';

export interface RosterCampaign {
    id: string;
    title: string;
    slug: string | null;
    status_label: string;
    is_finished: boolean;
    accepts_new_players: boolean;
    roster_access: RosterAccessValue;
    max_players: number | null;
    roster_count: number;
}

export interface RosterRequest {
    id: string;
    user_name: string;
    message: string | null;
    // d/m/Y
    created_at: string | null;
}

export interface RosterSession {
    game_table_id: string;
    title: string;
    slug: string;
    // d/m
    date_short: string;
    // d/m/Y H:i
    date: string;
}

export interface RosterPlayerSession {
    game_table_id: string;
    state: RosterSessionState;
    can_toggle: boolean;
}

export interface RosterPlayer {
    user_id: string;
    name: string;
    // d/m/Y
    joined_at: string | null;
    // One per upcoming session, in the order of RosterPage.sessions
    sessions: RosterPlayerSession[];
}

/** Props of the Campaigns/Roster page (RosterPageDTO::toArray()). */
export interface RosterPage {
    campaign: RosterCampaign;
    roster_access_options: Record<RosterAccessValue, string>;
    max_players_limit: number;
    requests: RosterRequest[];
    sessions: RosterSession[];
    players: RosterPlayer[];
    can_add: boolean;
}

/** What the viewer of a campaign page can do with its roster (JoinBoxDTO). */
export type JoinBoxState =
    | 'director'
    | 'member'
    | 'pending'
    | 'finished'
    | 'not_accepting'
    | 'full'
    | 'guest'
    | 'members_only'
    | 'can_request';

export interface JoinBox {
    state: JoinBoxState;
    // d/m/Y, with a pending request
    requested_at: string | null;
    // For the direction, the roster page
    roster_url: string | null;
}
