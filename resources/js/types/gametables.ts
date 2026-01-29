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
