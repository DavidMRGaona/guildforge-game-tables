<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { GameTable, GameMaster } from '../../types/gametables';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import FormatBadge from '../../components/FormatBadge.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import ContentWarningBadge from '../../components/ContentWarningBadge.vue';
import SafetyToolBadge from '../../components/SafetyToolBadge.vue';
import { useSeo } from '@/composables/useSeo';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';
import { buildHeroImageUrl } from '@/utils/cloudinary';

interface Props {
    table: GameTable;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

useSeo({
    title: props.table.title,
    description: props.table.synopsis || t('gameTables.description'),
    type: 'article',
    canonical: `/mesas/${props.table.slug}`,
});

const formattedDate = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleDateString(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const formattedTime = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleTimeString(locale.value, {
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

const playerCapacityPercentage = computed(() => {
    if (props.table.maxPlayers === 0) return 0;
    return Math.min((props.table.currentPlayers / props.table.maxPlayers) * 100, 100);
});

const spectatorCapacityPercentage = computed(() => {
    if (props.table.maxSpectators === 0) return 0;
    return Math.min((props.table.currentSpectators / props.table.maxSpectators) * 100, 100);
});

const formatRegistrationDate = (dateString: string | null): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value, {
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
        props.table.registrationClosesAt ||
        props.table.membersEarlyAccessDays > 0
    );
});

const imageUrl = computed(() => buildHeroImageUrl(props.table.imagePublicId));
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Back link -->
            <div class="mb-6">
                <Link
                    href="/mesas"
                    class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:text-stone-400 dark:hover:text-stone-300 dark:focus:ring-offset-stone-900"
                >
                    <svg
                        class="mr-1 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    {{ t('common.back') }}
                </Link>
            </div>

            <article
                class="overflow-hidden rounded-lg bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50"
            >
                <!-- Game Table Image -->
                <div v-if="imageUrl" class="relative">
                    <img
                        :src="imageUrl"
                        :alt="table.title"
                        class="h-64 w-full object-cover sm:h-80"
                    />
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
                                class="rounded-full bg-stone-100 px-3 py-1 text-sm font-medium text-stone-700 dark:bg-stone-700 dark:text-stone-300"
                            >
                                {{ table.tableTypeLabel }}
                            </span>
                        </div>

                        <h1
                            class="mb-4 text-3xl font-bold text-gray-900 sm:text-4xl dark:text-stone-100"
                        >
                            {{ table.title }}
                        </h1>

                        <p class="text-xl font-medium text-amber-600 dark:text-amber-500">
                            {{ table.gameSystemName }}
                        </p>
                    </div>

                    <!-- Section 2: Key Information -->
                    <div
                        class="mb-6 rounded-lg bg-stone-50 p-4 dark:bg-stone-900/30"
                    >
                        <h2 class="sr-only">{{ t('gameTables.details.gameSystem') }}</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Date and Time -->
                            <div>
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.dateAndTime') }}
                                </h3>
                                <div class="flex items-center text-stone-700 dark:text-stone-300">
                                    <svg
                                        class="mr-2 h-5 w-5 text-amber-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <div>
                                        <p>{{ formattedDate }}</p>
                                        <p class="text-sm">{{ formattedTime }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Duration -->
                            <div>
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.duration') }}
                                </h3>
                                <div class="flex items-center text-stone-700 dark:text-stone-300">
                                    <svg
                                        class="mr-2 h-5 w-5 text-amber-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <span>{{ formattedDuration }}</span>
                                </div>
                            </div>

                            <!-- Location -->
                            <div>
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.location') }}
                                </h3>
                                <div class="flex items-center text-stone-700 dark:text-stone-300">
                                    <svg
                                        class="mr-2 h-5 w-5 text-amber-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                    <span>{{ table.location || t('gameTables.online') }}</span>
                                </div>
                            </div>

                            <!-- Game Masters -->
                            <div>
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.gameMaster') }}
                                </h3>
                                <div class="text-stone-700 dark:text-stone-300">
                                    <!-- When all GMs have the same role, show as a single list -->
                                    <template v-if="allSameRole">
                                        <ul class="space-y-1">
                                            <li
                                                v-for="gm in formattedAllGameMasters"
                                                :key="gm.id"
                                                class="flex items-center"
                                            >
                                                <svg
                                                    class="mr-2 h-5 w-5 text-amber-600"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                    />
                                                </svg>
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
                                                <svg
                                                    class="mr-2 h-5 w-5 text-amber-600"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                    />
                                                </svg>
                                                <span>{{ gm.formattedName }}</span>
                                            </li>
                                        </ul>

                                        <!-- Co-Directors as a separate list -->
                                        <div v-if="formattedCoGameMasters.length > 0" class="mt-2">
                                            <span class="text-sm font-medium">{{ t('gameTables.coGameMasters') }}:</span>
                                            <ul class="ml-7 mt-1 space-y-1 text-sm">
                                                <li
                                                    v-for="gm in formattedCoGameMasters"
                                                    :key="gm.id"
                                                >
                                                    {{ gm.formattedName }}
                                                </li>
                                            </ul>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Format -->
                            <div>
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.filters.format') }}
                                </h3>
                                <p class="text-stone-700 dark:text-stone-300">
                                    {{ table.tableFormatLabel }}
                                </p>
                            </div>

                            <!-- Table Type -->
                            <div>
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.tableType') }}
                                </h3>
                                <p class="text-stone-700 dark:text-stone-300">
                                    {{ table.tableTypeLabel }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Capacity -->
                    <div class="mb-6">
                        <h2 class="mb-4 text-lg font-semibold text-stone-900 dark:text-stone-100">
                            {{ t('gameTables.capacity') }}
                        </h2>

                        <!-- Players -->
                        <div class="mb-4">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-stone-700 dark:text-stone-300">
                                    {{ t('gameTables.roles.players') }}
                                    <span class="text-stone-500 dark:text-stone-400">
                                        ({{ t('gameTables.playersRequired', { min: table.minPlayers, max: table.maxPlayers }) }})
                                    </span>
                                </span>
                                <span class="font-medium text-stone-900 dark:text-stone-100">
                                    {{ table.currentPlayers }} / {{ table.maxPlayers }}
                                </span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                <div
                                    class="h-full transition-all"
                                    :class="[
                                        playerCapacityPercentage >= 100 ? 'bg-red-500' :
                                        playerCapacityPercentage >= 75 ? 'bg-amber-500' : 'bg-green-500'
                                    ]"
                                    :style="{ width: `${playerCapacityPercentage}%` }"
                                />
                            </div>
                            <p v-if="table.spotsAvailable > 0" class="mt-1 text-sm text-green-600 dark:text-green-400">
                                {{ table.spotsAvailable }} {{ t('gameTables.spotsAvailable') }}
                            </p>
                            <p v-else class="mt-1 text-sm font-medium text-red-600 dark:text-red-400">
                                {{ t('gameTables.full') }}
                            </p>
                        </div>

                        <!-- Spectators -->
                        <div v-if="table.maxSpectators > 0">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-stone-700 dark:text-stone-300">
                                    {{ t('gameTables.roles.spectators') }}
                                </span>
                                <span class="font-medium text-stone-900 dark:text-stone-100">
                                    {{ table.currentSpectators }} / {{ table.maxSpectators }}
                                </span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                <div
                                    class="h-full bg-blue-500 transition-all"
                                    :style="{ width: `${spectatorCapacityPercentage}%` }"
                                />
                            </div>
                            <p v-if="table.spectatorSpotsAvailable > 0" class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                {{ table.spectatorSpotsAvailable }} {{ t('gameTables.spotsAvailable') }}
                            </p>
                            <p v-else class="mt-1 text-sm text-stone-500 dark:text-stone-400">
                                {{ t('gameTables.registration.spectatorsFull') }}
                            </p>
                        </div>
                    </div>

                    <!-- Section 4: Registration Info (highlighted blue) -->
                    <div
                        v-if="hasRegistrationInfo"
                        class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30"
                    >
                        <h2 class="mb-3 flex items-center text-lg font-semibold text-blue-900 dark:text-blue-100">
                            <svg
                                class="mr-2 h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                            {{ t('gameTables.registration.title') }}
                        </h2>

                        <div class="space-y-2 text-sm text-blue-800 dark:text-blue-200">
                            <p>
                                <span class="font-medium">{{ t('gameTables.registrationTypeLabel') || 'Tipo' }}:</span>
                                {{ table.registrationTypeLabel }}
                            </p>

                            <p v-if="table.registrationOpensAt">
                                <span class="font-medium">{{ t('gameTables.registration.opensAtDate') }}:</span>
                                {{ formatRegistrationDate(table.registrationOpensAt) }}
                            </p>

                            <p v-if="table.registrationClosesAt">
                                <span class="font-medium">{{ t('gameTables.registration.closesAtDate') }}:</span>
                                {{ formatRegistrationDate(table.registrationClosesAt) }}
                            </p>

                            <p
                                v-if="table.membersEarlyAccessDays > 0"
                                class="flex items-center font-medium text-blue-700 dark:text-blue-300"
                            >
                                <svg
                                    class="mr-1 h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                {{ t('gameTables.registration.earlyAccessDays', { days: table.membersEarlyAccessDays }) }}
                            </p>
                        </div>
                    </div>

                    <!-- Section 5: Synopsis -->
                    <div v-if="table.synopsis" class="mb-6">
                        <h2 class="mb-2 text-lg font-semibold text-stone-900 dark:text-stone-100">
                            {{ t('gameTables.content.synopsis') }}
                        </h2>
                        <p class="whitespace-pre-line text-stone-700 dark:text-stone-300">
                            {{ table.synopsis }}
                        </p>
                    </div>

                    <!-- Section 6: Details -->
                    <div class="mb-6 space-y-4">
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-stone-100">
                            {{ t('gameTables.details.gameSystem') }}
                        </h2>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Experience Level -->
                            <div v-if="table.experienceLevelLabel">
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.experienceLevel') }}
                                </h3>
                                <p class="text-stone-700 dark:text-stone-300">
                                    {{ table.experienceLevelLabel }}
                                </p>
                            </div>

                            <!-- Character Creation -->
                            <div v-if="table.characterCreationLabel">
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.characterCreation') }}
                                </h3>
                                <p class="text-stone-700 dark:text-stone-300">
                                    {{ table.characterCreationLabel }}
                                </p>
                            </div>

                            <!-- Language -->
                            <div v-if="table.languageLabel">
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.language') }}
                                </h3>
                                <p class="text-stone-700 dark:text-stone-300">{{ table.languageLabel }}</p>
                            </div>

                            <!-- Tone -->
                            <div v-if="table.toneLabel">
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.tone') }}
                                </h3>
                                <p class="text-stone-700 dark:text-stone-300">{{ table.toneLabel }}</p>
                            </div>

                            <!-- Minimum Age -->
                            <div v-if="table.minimumAge">
                                <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                    {{ t('gameTables.minimumAge') }}
                                </h3>
                                <p class="text-stone-700 dark:text-stone-300">
                                    {{ t('gameTables.ageRequirement', { age: table.minimumAge }) }}
                                </p>
                            </div>
                        </div>

                        <!-- Genres -->
                        <div v-if="table.genres.length > 0">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('gameTables.genres') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(genre, index) in table.genres"
                                    :key="index"
                                    class="rounded-full bg-stone-200 px-3 py-1 text-sm text-stone-700 dark:bg-stone-700 dark:text-stone-300"
                                >
                                    {{ genre }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Section 7: Safety and Warnings -->
                    <div v-if="hasSafetySection" class="mb-6 space-y-4">
                        <h2 class="text-lg font-semibold text-stone-900 dark:text-stone-100">
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
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
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
                            class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/30"
                        >
                            <h3 class="mb-2 flex items-center text-sm font-semibold text-amber-900 dark:text-amber-100">
                                <svg
                                    class="mr-2 h-5 w-5 text-amber-600 dark:text-amber-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                                {{ t('gameTables.customWarningsTitle') }}
                            </h3>
                            <ul class="list-inside list-disc space-y-1 text-sm text-amber-800 dark:text-amber-200">
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
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('gameTables.campaign') }}
                            </h3>
                            <Link
                                :href="`/campanas/${table.campaignId}`"
                                class="text-amber-600 hover:text-amber-700 dark:text-amber-500 dark:hover:text-amber-400"
                            >
                                {{ table.campaignTitle }}
                            </Link>
                        </div>

                        <!-- Notes -->
                        <div v-if="table.notes">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('gameTables.notes') }}
                            </h3>
                            <p class="whitespace-pre-line text-stone-700 dark:text-stone-300">
                                {{ table.notes }}
                            </p>
                        </div>
                    </div>

                    <!-- Section 9: Module slot for registration actions -->
                    <div class="mb-6">
                        <ModuleSlot name="game-table-registration" />
                    </div>

                    <div class="border-t border-gray-200 pt-6 dark:border-stone-700">
                        <Link href="/mesas">
                            <BaseButton variant="primary">
                                {{ t('gameTables.viewAll') }}
                            </BaseButton>
                        </Link>
                    </div>
                </div>
            </article>
        </div>
    </DefaultLayout>
</template>
