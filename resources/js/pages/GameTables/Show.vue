<script setup lang="ts">
import { Calendar, ChevronLeft, Clipboard, Clock, MapPin, TriangleAlert, User } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { GameTable, GameMaster } from '../../types/gametables';
import type { CampaignSessionInfo } from '../../types/registration';
import BaseButton from '@/components/ui/BaseButton.vue';
import CapacityBar from '../../components/CapacityBar.vue';
import FormatBadge from '../../components/FormatBadge.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import ContentWarningBadge from '../../components/ContentWarningBadge.vue';
import SafetyToolBadge from '../../components/SafetyToolBadge.vue';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';
import { buildHeroImageUrl } from '@/utils/cloudinary';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    table: GameTable;
    /** Set on a campaign session: whether the campaign is closed to people outside its roster */
    campaignSession?: CampaignSessionInfo | null;
    /** A director of a table that delegates its registrations: offers «Gestionar inscritos» */
    canManageRegistrations?: boolean;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

// A closed campaign admits its roster only, whatever the table's own registration type says
const registrationTypeText = computed(() =>
    props.campaignSession?.closed === true
        ? t('gameTables.campaignSession.rosterOnly')
        : props.table.registrationTypeLabel
);

const formattedDate = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const formattedTime = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleTimeString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        hour: '2-digit',
        minute: '2-digit',
    });
});

const durationHours = computed(() => {
    return Math.floor(props.table.durationMinutes / 60);
});

const durationMinutesRemainder = computed(() => {
    return props.table.durationMinutes % 60;
});

const formattedDuration = computed(() => {
    const hours = durationHours.value;
    const minutes = durationMinutesRemainder.value;

    if (hours > 0 && minutes > 0) {
        return t('gameTables.durationHoursMinutes', { hours, minutes });
    } else if (hours > 0) {
        return t('gameTables.durationHours', { hours });
    } else {
        return t('gameTables.durationMinutes', { minutes });
    }
});

// Group game masters by role type (main directors vs co-directors)
const mainGameMasters = computed(() => {
    return props.table.gameMasters.filter((gm: GameMaster) => gm.isMain);
});

const coGameMasters = computed(() => {
    return props.table.gameMasters.filter((gm: GameMaster) => !gm.isMain);
});

// All GMs are the same type - show as single list
const allSameRole = computed(() => {
    return mainGameMasters.value.length === 0 || coGameMasters.value.length === 0;
});

/**
 * Format the display name for a game master.
 * - If isNamePublic is true: "John Doe (Role Label)" or "John Doe (Custom Title)"
 * - If isNamePublic is false: Role label or custom title only
 * - If multiple GMs have the same display, number them
 */
const formatGameMasterName = (gm: GameMaster, allGms: GameMaster[]): string => {
    const title = gm.customTitle || gm.roleLabel;

    if (gm.isNamePublic) {
        // Check if there are duplicates with same name
        const duplicates = allGms.filter(
            (other) => other.isNamePublic && other.displayName === gm.displayName
        );

        if (duplicates.length > 1) {
            const position = duplicates.findIndex((d) => d.id === gm.id) + 1;
            return `${gm.displayName} (${title} #${position})`;
        }

        return `${gm.displayName} (${title})`;
    }

    // Name not public - show only title
    // Check for duplicate titles among non-public names
    const nonPublicWithSameTitle = allGms.filter(
        (other) => !other.isNamePublic && (other.customTitle || other.roleLabel) === title
    );

    if (nonPublicWithSameTitle.length > 1) {
        const position = nonPublicWithSameTitle.findIndex((d) => d.id === gm.id) + 1;
        return `${title} #${position}`;
    }

    return title;
};

const formattedMainGameMasters = computed(() => {
    return mainGameMasters.value.map((gm) => ({
        ...gm,
        formattedName: formatGameMasterName(gm, props.table.gameMasters),
    }));
});

const formattedCoGameMasters = computed(() => {
    return coGameMasters.value.map((gm) => ({
        ...gm,
        formattedName: formatGameMasterName(gm, props.table.gameMasters),
    }));
});

// All game masters formatted for single-list display
const formattedAllGameMasters = computed(() => {
    return props.table.gameMasters.map((gm) => ({
        ...gm,
        formattedName: formatGameMasterName(gm, props.table.gameMasters),
    }));
});

const formatRegistrationDate = (dateString: string | null): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const hasCustomWarnings = computed(() => {
    return props.table.customWarnings && props.table.customWarnings.length > 0;
});

const hasSafetySection = computed(() => {
    return (
        props.table.safetyTools.length > 0 ||
        (props.table.contentWarnings && props.table.contentWarnings.length > 0) ||
        hasCustomWarnings.value
    );
});

const hasRegistrationInfo = computed(() => {
    return (
        props.table.registrationOpensAt ||
        props.table.effectiveRegistrationClosesAt ||
        props.table.membersEarlyAccessDays > 0
    );
});

const imageUrl = computed(() => buildHeroImageUrl(props.table.imagePublicId));
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Back link -->
        <div class="mb-6">
            <Link
                href="/mesas"
                class="inline-flex items-center text-sm text-base-secondary hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            >
                <ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" />
                {{ t('common.back') }}
            </Link>
        </div>

        <article class="overflow-hidden rounded-lg bg-surface shadow">
            <!-- Game Table Image -->
            <div v-if="imageUrl" class="relative">
                <img :src="imageUrl" :alt="table.title" class="h-64 w-full object-cover sm:h-80" />
            </div>

            <div class="p-6 sm:p-8">
                <!-- Section 1: Header -->
                <div class="mb-6">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            :status="table.status"
                            :label="table.statusLabel"
                            :color="table.statusColor"
                            size="md"
                        />
                        <FormatBadge
                            :format="table.tableFormat"
                            :label="table.tableFormatLabel"
                            :color="table.tableFormatColor"
                            size="md"
                        />
                        <span
                            class="rounded-full bg-muted px-3 py-1 text-sm font-medium text-base-secondary"
                        >
                            {{ table.tableTypeLabel }}
                        </span>
                    </div>

                    <h1 class="mb-4 text-3xl font-bold text-base-primary sm:text-4xl">
                        {{ table.title }}
                    </h1>

                    <p class="text-xl font-medium text-primary">
                        {{ table.gameSystemName }}
                    </p>

                    <Link
                        v-if="canManageRegistrations"
                        :href="`/mesas/mis-mesas/${table.slug}/inscritos`"
                        class="mt-4 inline-flex min-h-[44px] items-center justify-center rounded-md border border-default px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
                    >
                        {{ t('gameTables.myTables.manageRegistrations') }}
                    </Link>
                </div>

                <!-- Section 2: Key Information -->
                <div class="mb-6 rounded-lg bg-muted p-4">
                    <h2 class="sr-only">{{ t('gameTables.details.gameSystem') }}</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Date and Time -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.dateAndTime') }}
                            </h3>
                            <div class="flex items-center text-base-secondary">
                                <Calendar class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                                <div>
                                    <p>{{ formattedDate }}</p>
                                    <p class="text-sm">{{ formattedTime }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Duration -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.duration') }}
                            </h3>
                            <div class="flex items-center text-base-secondary">
                                <Clock class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                                <span>{{ formattedDuration }}</span>
                            </div>
                        </div>

                        <!-- Location -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.location') }}
                            </h3>
                            <div class="flex items-center text-base-secondary">
                                <MapPin class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                                <span>{{ table.location || t('gameTables.online') }}</span>
                            </div>
                        </div>

                        <!-- Game Masters -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.gameMaster') }}
                            </h3>
                            <div class="text-base-secondary">
                                <!-- When all GMs have the same role, show as a single list -->
                                <template v-if="allSameRole">
                                    <ul class="space-y-1">
                                        <!-- No game master rows: the table's main GM name (the creator by default) -->
                                        <li
                                            v-if="formattedAllGameMasters.length === 0"
                                            class="flex items-center"
                                        >
                                            <User
                                                class="mr-2 h-5 w-5 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span>{{ table.mainGameMasterName }}</span>
                                        </li>
                                        <li
                                            v-for="gm in formattedAllGameMasters"
                                            :key="gm.id"
                                            class="flex items-center"
                                        >
                                            <User
                                                class="mr-2 h-5 w-5 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span>{{ gm.formattedName }}</span>
                                        </li>
                                    </ul>
                                </template>

                                <!-- When there are both main directors and co-directors -->
                                <template v-else>
                                    <!-- Main Directors -->
                                    <ul class="space-y-1">
                                        <li
                                            v-for="gm in formattedMainGameMasters"
                                            :key="gm.id"
                                            class="flex items-center"
                                        >
                                            <User
                                                class="mr-2 h-5 w-5 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span>{{ gm.formattedName }}</span>
                                        </li>
                                    </ul>

                                    <!-- Co-Directors as a separate list -->
                                    <div v-if="formattedCoGameMasters.length > 0" class="mt-2">
                                        <span class="text-sm font-medium"
                                            >{{ t('gameTables.coGameMasters') }}:</span
                                        >
                                        <ul class="ml-7 mt-1 space-y-1 text-sm">
                                            <li v-for="gm in formattedCoGameMasters" :key="gm.id">
                                                {{ gm.formattedName }}
                                            </li>
                                        </ul>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Format -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.filters.format') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ table.tableFormatLabel }}
                            </p>
                        </div>

                        <!-- Table Type -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.tableType') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ table.tableTypeLabel }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Capacity -->
                <div class="mb-6">
                    <h2 class="mb-4 text-lg font-semibold text-base-primary">
                        {{ t('gameTables.capacity') }}
                    </h2>

                    <!-- Players -->
                    <div class="mb-4">
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="text-base-secondary">
                                {{ t('gameTables.roles.players') }}
                                <span class="text-base-muted">
                                    ({{
                                        t('gameTables.playersRequired', {
                                            min: table.minPlayers,
                                            max: table.maxPlayers,
                                        })
                                    }})
                                </span>
                            </span>
                            <span class="font-medium text-base-primary">
                                {{ table.currentPlayers }} / {{ table.maxPlayers }}
                            </span>
                        </div>
                        <CapacityBar
                            :current="table.currentPlayers"
                            :max="table.maxPlayers"
                            :label="t('gameTables.roles.players')"
                            size="md"
                        />
                        <p v-if="table.spotsAvailable > 0" class="mt-1 text-sm text-success">
                            {{ table.spotsAvailable }} {{ t('gameTables.spotsAvailable') }}
                        </p>
                        <p v-else class="mt-1 text-sm font-medium text-error">
                            {{ t('gameTables.full') }}
                        </p>
                    </div>

                    <!-- Spectators -->
                    <div v-if="table.maxSpectators > 0">
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="text-base-secondary">
                                {{ t('gameTables.roles.spectators') }}
                            </span>
                            <span class="font-medium text-base-primary">
                                {{ table.currentSpectators }} / {{ table.maxSpectators }}
                            </span>
                        </div>
                        <CapacityBar
                            :current="table.currentSpectators"
                            :max="table.maxSpectators"
                            :label="t('gameTables.roles.spectators')"
                            tone="info"
                            size="md"
                        />
                        <p v-if="table.spectatorSpotsAvailable > 0" class="mt-1 text-sm text-info">
                            {{ table.spectatorSpotsAvailable }} {{ t('gameTables.spotsAvailable') }}
                        </p>
                        <p v-else class="mt-1 text-sm text-base-muted">
                            {{ t('gameTables.registration.spectatorsFull') }}
                        </p>
                    </div>
                </div>

                <!-- Section 4: Registration Info (info box; only the icons take the info colour) -->
                <div
                    v-if="hasRegistrationInfo"
                    class="mb-6 rounded-lg border border-info bg-info-light p-4"
                >
                    <h2 class="mb-3 flex items-center text-lg font-semibold text-base-primary">
                        <Clipboard class="mr-2 h-5 w-5 text-info" aria-hidden="true" />
                        {{ t('gameTables.registration.title') }}
                    </h2>

                    <div class="space-y-2 text-sm text-base-primary">
                        <p>
                            <span class="font-medium"
                                >{{ t('gameTables.registrationTypeLabel') || 'Tipo' }}:</span
                            >
                            {{ registrationTypeText }}
                        </p>

                        <p v-if="table.registrationOpensAt">
                            <span class="font-medium"
                                >{{ t('gameTables.registration.opensAtDate') }}:</span
                            >
                            {{ formatRegistrationDate(table.registrationOpensAt) }}
                        </p>

                        <p v-if="table.effectiveRegistrationClosesAt">
                            <span class="font-medium"
                                >{{ t('gameTables.registration.closesAtDate') }}:</span
                            >
                            {{ formatRegistrationDate(table.effectiveRegistrationClosesAt) }}
                        </p>

                        <p
                            v-if="table.membersEarlyAccessDays > 0"
                            class="flex items-center font-medium text-base-primary"
                        >
                            <Clock class="mr-1 h-4 w-4 text-info" aria-hidden="true" />
                            {{
                                t('gameTables.registration.earlyAccessDays', {
                                    days: table.membersEarlyAccessDays,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <!-- Section 5: Synopsis -->
                <div v-if="table.synopsis" class="mb-6">
                    <h2 class="mb-2 text-lg font-semibold text-base-primary">
                        {{ t('gameTables.content.synopsis') }}
                    </h2>
                    <p class="whitespace-pre-line text-base-secondary">
                        {{ table.synopsis }}
                    </p>
                </div>

                <!-- Section 6: Details -->
                <div class="mb-6 space-y-4">
                    <h2 class="text-lg font-semibold text-base-primary">
                        {{ t('gameTables.details.gameSystem') }}
                    </h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Experience Level -->
                        <div v-if="table.experienceLevelLabel">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.experienceLevel') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ table.experienceLevelLabel }}
                            </p>
                        </div>

                        <!-- Character Creation -->
                        <div v-if="table.characterCreationLabel">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.characterCreation') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ table.characterCreationLabel }}
                            </p>
                        </div>

                        <!-- Language -->
                        <div v-if="table.languageLabel">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.language') }}
                            </h3>
                            <p class="text-base-secondary">{{ table.languageLabel }}</p>
                        </div>

                        <!-- Tone -->
                        <div v-if="table.toneLabel">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.tone') }}
                            </h3>
                            <p class="text-base-secondary">{{ table.toneLabel }}</p>
                        </div>

                        <!-- Minimum Age -->
                        <div v-if="table.minimumAge">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.minimumAge') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ t('gameTables.ageRequirement', { age: table.minimumAge }) }}
                            </p>
                        </div>
                    </div>

                    <!-- Genres -->
                    <div v-if="table.genres.length > 0">
                        <h3 class="mb-2 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.genres') }}
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="(genre, index) in table.genres"
                                :key="index"
                                class="rounded-full bg-muted px-3 py-1 text-sm text-base-secondary"
                            >
                                {{ genre }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Section 7: Safety and Warnings -->
                <div v-if="hasSafetySection" class="mb-6 space-y-4">
                    <h2 class="text-lg font-semibold text-base-primary">
                        {{ t('gameTables.safetyTools') }}
                    </h2>

                    <!-- Safety Tools -->
                    <div v-if="table.safetyTools.length > 0">
                        <div class="flex flex-wrap gap-2">
                            <SafetyToolBadge
                                v-for="(tool, index) in table.safetyTools"
                                :key="index"
                                :tool="tool"
                            />
                        </div>
                    </div>

                    <!-- Content Warnings -->
                    <div v-if="table.contentWarnings && table.contentWarnings.length > 0">
                        <h3 class="mb-2 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.contentWarnings') }}
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <ContentWarningBadge
                                v-for="(warning, index) in table.contentWarnings"
                                :key="index"
                                :warning="warning"
                            />
                        </div>
                    </div>

                    <!-- Custom Warnings (highlighted amber) -->
                    <div
                        v-if="hasCustomWarnings"
                        class="rounded-lg border border-warning bg-warning-light p-4"
                    >
                        <h3 class="mb-2 flex items-center text-sm font-semibold text-warning">
                            <TriangleAlert class="mr-2 h-5 w-5 text-warning" aria-hidden="true" />
                            {{ t('gameTables.customWarningsTitle') }}
                        </h3>
                        <ul class="list-inside list-disc space-y-1 text-sm text-warning">
                            <li v-for="(warning, index) in table.customWarnings" :key="index">
                                {{ warning }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Section 8: Additional Info -->
                <div class="mb-6 space-y-4">
                    <!-- Campaign -->
                    <div v-if="table.campaignTitle">
                        <h3 class="mb-2 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.campaign') }}
                        </h3>
                        <Link
                            :href="`/campanas/${table.campaignId}`"
                            class="text-primary hover:underline"
                        >
                            {{ table.campaignTitle }}
                        </Link>
                    </div>

                    <!-- Notes -->
                    <div v-if="table.notes">
                        <h3 class="mb-2 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.notes') }}
                        </h3>
                        <p class="whitespace-pre-line text-base-secondary">
                            {{ table.notes }}
                        </p>
                    </div>
                </div>

                <!-- Section 9: Module slot for registration actions -->
                <div class="mb-6">
                    <ModuleSlot name="game-table-registration" />
                </div>

                <div class="border-t border-default pt-6">
                    <Link href="/mesas">
                        <BaseButton variant="primary">
                            {{ t('gameTables.viewAll') }}
                        </BaseButton>
                    </Link>
                </div>
            </div>
        </article>
    </div>
</template>
