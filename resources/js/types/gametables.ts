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
    campaignId: string;
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
