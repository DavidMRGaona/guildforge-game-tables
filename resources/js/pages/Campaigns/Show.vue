<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Campaign, JoinBox } from '../../types/gametables';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import SafetyToolBadge from '../../components/SafetyToolBadge.vue';
import ContentWarningBadge from '../../components/ContentWarningBadge.vue';
import GameTableCard from '../../components/GameTableCard.vue';
import CampaignJoinBox from '../../components/campaigns/CampaignJoinBox.vue';
import CapacityBar from '../../components/CapacityBar.vue';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';
import { buildHeroImageUrl } from '@/utils/cloudinary';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    campaign: Campaign;
    // What the viewer can do with the roster
    joinBox: JoinBox;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const formattedStartDate = computed(() => {
    if (!props.campaign.startDate) return null;
    const date = new Date(props.campaign.startDate);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const formattedEndDate = computed(() => {
    if (!props.campaign.endDate) return null;
    const date = new Date(props.campaign.endDate);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const spotsAvailable = computed(() => {
    if (props.campaign.maxPlayers === null) return null;
    return props.campaign.maxPlayers - props.campaign.currentPlayers;
});

const sessionProgressLabel = computed(() => {
    if (props.campaign.sessionCount !== null && props.campaign.sessionCount > 0) {
        return t('campaigns.details.totalSessions', {
            current: props.campaign.currentSession,
            total: props.campaign.sessionCount,
        });
    }
    return t('campaigns.sessionNumber', { number: props.campaign.currentSession });
});

const totalSessionsLabel = computed(() => {
    if (props.campaign.totalSessions === 0) {
        return t('campaigns.totalSessionsUndetermined');
    }
    return String(props.campaign.totalSessions);
});

const mainGameMasters = computed(() => {
    return props.campaign.gameMasters.filter((gm) => gm.isMain);
});

const coGameMasters = computed(() => {
    return props.campaign.gameMasters.filter((gm) => !gm.isMain);
});

const imageUrl = computed(() => buildHeroImageUrl(props.campaign.imagePublicId));
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link
                    href="/campanas"
                    class="inline-flex items-center text-sm text-base-secondary hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
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
                class="overflow-hidden rounded-lg bg-surface shadow"
            >
                <!-- Campaign Image -->
                <div v-if="imageUrl" class="relative">
                    <img
                        :src="imageUrl"
                        :alt="campaign.title"
                        class="h-64 w-full object-cover sm:h-80"
                    />
                </div>

                <div class="p-6 sm:p-8">
                    <!-- Header with badges -->
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            :status="campaign.status"
                            :label="campaign.statusLabel"
                            :color="campaign.statusColor"
                            size="md"
                        />
                        <span
                            v-if="campaign.acceptsNewPlayers && campaign.status !== 'recruiting'"
                            class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-sm font-medium text-base-primary"
                        >
                            {{ t('campaigns.lookingForPlayers') }}
                        </span>
                    </div>

                    <h1
                        class="mb-4 text-3xl font-bold text-base-primary sm:text-4xl"
                    >
                        {{ campaign.title }}
                    </h1>

                    <!-- Game System -->
                    <p class="mb-6 text-xl font-medium text-primary">
                        {{ campaign.gameSystemName }}
                    </p>

                    <!-- Description -->
                    <div v-if="campaign.description" class="mb-6">
                        <p class="whitespace-pre-line text-base-secondary">
                            {{ campaign.description }}
                        </p>
                    </div>

                    <!-- Key Information Grid -->
                    <div
                        class="mb-6 grid grid-cols-1 gap-4 rounded-lg bg-muted p-4 sm:grid-cols-2"
                    >
                        <!-- Frequency -->
                        <div v-if="campaign.frequencyLabel">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.frequency') }}
                            </h3>
                            <div class="flex items-center text-base-secondary">
                                <svg
                                    class="mr-2 h-5 w-5 text-primary"
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
                                <span>{{ campaign.frequencyLabel }}</span>
                            </div>
                        </div>

                        <!-- Duration -->
                        <div v-if="campaign.expectedDurationMonths">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.expectedDuration') }}
                            </h3>
                            <div class="flex items-center text-base-secondary">
                                <svg
                                    class="mr-2 h-5 w-5 text-primary"
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
                                <span>
                                    {{ t('campaigns.monthsCount', { count: campaign.expectedDurationMonths }) }}
                                </span>
                            </div>
                        </div>

                        <!-- Start Date -->
                        <div v-if="formattedStartDate">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.startDate') }}
                            </h3>
                            <p class="text-base-secondary">{{ formattedStartDate }}</p>
                        </div>

                        <!-- End Date -->
                        <div v-if="formattedEndDate">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.endDate') }}
                            </h3>
                            <p class="text-base-secondary">{{ formattedEndDate }}</p>
                        </div>

                        <!-- Session Progress -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.details.currentSession') }}
                            </h3>
                            <div class="text-base-secondary">
                                <p>{{ sessionProgressLabel }}</p>
                                <CapacityBar
                                    v-if="campaign.sessionCount !== null && campaign.sessionCount > 0"
                                    class="mt-2"
                                    :current="campaign.currentSession"
                                    :max="campaign.sessionCount"
                                    :label="t('campaigns.details.currentSession')"
                                    tone="primary"
                                />
                            </div>
                        </div>

                        <!-- Game Masters -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.gameMaster') }}
                            </h3>
                            <div class="space-y-1">
                                <template v-if="mainGameMasters.length > 0">
                                    <div
                                        v-for="gm in mainGameMasters"
                                        :key="gm.id"
                                        class="flex items-center text-base-secondary"
                                    >
                                        <svg
                                            class="mr-2 h-5 w-5 text-primary"
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
                                        <span>{{ gm.isNamePublic ? gm.displayName : t('campaigns.anonymousGm') }}</span>
                                        <span v-if="gm.customTitle" class="ml-1 text-sm text-base-secondary">({{ gm.customTitle }})</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex items-center text-base-secondary">
                                        <svg
                                            class="mr-2 h-5 w-5 text-primary"
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
                                        <span>{{ campaign.creatorName }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Co-Game Masters -->
                        <div v-if="coGameMasters.length > 0">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('gameTables.coGameMasters') }}
                            </h3>
                            <div class="space-y-1">
                                <div
                                    v-for="gm in coGameMasters"
                                    :key="gm.id"
                                    class="flex items-center text-base-secondary"
                                >
                                    <svg
                                        class="mr-2 h-5 w-5 text-primary"
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
                                    <span>{{ gm.isNamePublic ? gm.displayName : t('campaigns.anonymousGm') }}</span>
                                    <span v-if="gm.customTitle" class="ml-1 text-sm text-base-secondary">({{ gm.customTitle }})</span>
                                </div>
                            </div>
                        </div>

                        <!-- Capacity -->
                        <div v-if="campaign.maxPlayers !== null">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.capacity') }}
                            </h3>
                            <div class="text-base-secondary">
                                <p>
                                    {{ t('campaigns.playersCount', {
                                        current: campaign.currentPlayers,
                                        max: campaign.maxPlayers,
                                    }) }}
                                </p>
                                <p v-if="spotsAvailable !== null" class="text-sm">
                                    {{ spotsAvailable }} {{ t('campaigns.spotsAvailable') }}
                                </p>
                            </div>
                            <CapacityBar
                                class="mt-2"
                                :current="campaign.currentPlayers"
                                :max="campaign.maxPlayers"
                                :label="t('campaigns.capacity')"
                                size="md"
                            />
                        </div>

                        <!-- Total Sessions -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.totalSessions') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ totalSessionsLabel }}
                            </p>
                        </div>

                        <!-- Experience Level -->
                        <div v-if="campaign.experienceLevelLabel">
                            <h3 class="mb-1 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.experienceLevel') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ campaign.experienceLevelLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- Join box, full width: inside the grid it stretched one cell -->
                    <div class="mb-6">
                        <CampaignJoinBox
                            :join-box="joinBox"
                            :campaign-title="campaign.title"
                            :campaign-slug="campaign.slug"
                        />
                    </div>

                    <!-- Additional Details -->
                    <div class="mb-6 space-y-4">
                        <!-- Language -->
                        <div v-if="campaign.languageLabel">
                            <h3 class="mb-2 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.language') }}
                            </h3>
                            <p class="text-base-secondary">{{ campaign.languageLabel }}</p>
                        </div>

                        <!-- Settings -->
                        <div v-if="campaign.settings">
                            <h3 class="mb-2 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.settings') }}
                            </h3>
                            <p class="whitespace-pre-line text-base-secondary">
                                {{ campaign.settings }}
                            </p>
                        </div>

                        <!-- Themes -->
                        <div v-if="campaign.themes.length > 0">
                            <h3 class="mb-2 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.themes') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(theme, index) in campaign.themes"
                                    :key="index"
                                    class="rounded-full bg-base-secondary/10 px-3 py-1 text-sm text-base-secondary"
                                >
                                    {{ theme }}
                                </span>
                            </div>
                        </div>

                        <!-- Safety Tools -->
                        <div v-if="campaign.safetyTools.length > 0">
                            <h3 class="mb-2 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.safetyTools') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <SafetyToolBadge
                                    v-for="(tool, index) in campaign.safetyTools"
                                    :key="index"
                                    :tool="tool"
                                />
                            </div>
                        </div>

                        <!-- Content Warnings -->
                        <div v-if="campaign.contentWarnings.length > 0">
                            <h3 class="mb-2 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.contentWarnings') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <ContentWarningBadge
                                    v-for="(warning, index) in campaign.contentWarnings"
                                    :key="index"
                                    :warning="warning"
                                />
                            </div>
                        </div>

                        <!-- Minimum Age -->
                        <div v-if="campaign.minimumAge">
                            <h3 class="mb-2 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.minimumAge') }}
                            </h3>
                            <p class="text-base-secondary">
                                {{ t('campaigns.ageRequirement', { age: campaign.minimumAge }) }}
                            </p>
                        </div>

                        <!-- Recruitment Message -->
                        <div v-if="campaign.acceptsNewPlayers && campaign.recruitmentMessage">
                            <h3 class="mb-2 text-sm font-semibold text-base-primary">
                                {{ t('campaigns.recruitmentMessage') }}
                            </h3>
                            <div
                                class="rounded-lg bg-success-light p-4 text-base-primary"
                            >
                                <p class="whitespace-pre-line">{{ campaign.recruitmentMessage }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Module slot for campaign actions -->
                    <div class="mb-6">
                        <ModuleSlot name="campaign-detail-actions" />
                    </div>

                    <div class="border-t border-default pt-6">
                        <Link href="/campanas">
                            <BaseButton variant="primary">
                                {{ t('campaigns.viewAll') }}
                            </BaseButton>
                        </Link>
                    </div>
                </div>
            </article>

            <!-- Campaign tables section -->
            <section v-if="campaign.gameTables.length > 0" class="mt-8">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-2xl font-bold text-base-primary">
                        {{ t('campaigns.tables') }}
                    </h2>
                    <Link
                        v-if="campaign.hasActiveOrUpcomingTables"
                        :href="`/mesas?campaign=${campaign.id}`"
                    >
                        <BaseButton variant="primary" size="sm">
                            {{ t('campaigns.viewUpcomingTables') }}
                        </BaseButton>
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <GameTableCard
                        v-for="table in campaign.gameTables"
                        :key="table.id"
                        :table="table"
                    />
                </div>
            </section>

            <!-- Empty state when no tables -->
            <section v-else class="mt-8">
                <div class="rounded-lg border border-default bg-muted p-8 text-center">
                    <p class="text-base-secondary">
                        {{ t('campaigns.noTables') }}
                    </p>
                </div>
            </section>
        </div>
    </DefaultLayout>
</template>
