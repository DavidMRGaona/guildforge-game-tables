<script setup lang="ts">
import HelpLink from '@/components/knowledge/HelpLink.vue';
import { Calendar, ChevronLeft, Clock, Globe, MapPin, TriangleAlert, User } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Campaign, JoinBox, SubjectSurveyLinks } from '../../types/gametables';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import FormatBadge from '../../components/FormatBadge.vue';
import SafetyToolBadge from '../../components/SafetyToolBadge.vue';
import ContentWarningBadge from '../../components/ContentWarningBadge.vue';
import GameTableCard from '../../components/GameTableCard.vue';
import CampaignJoinBox from '../../components/campaigns/CampaignJoinBox.vue';
import CapacityBar from '../../components/CapacityBar.vue';
import SurveyLinks from '../../components/SurveyLinks.vue';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';
import { buildHeroImageUrl } from '@/utils/cloudinary';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    campaign: Campaign;
    // What the viewer can do with the roster
    joinBox: JoinBox;
    // Its surveys for this person, when a surveys module offers any
    surveyLinks?: SubjectSurveyLinks | null;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

// Only web links become a link: the forms already refuse other schemes, this keeps it so for any stored value
const safeOnlineUrl = computed(() =>
    /^https?:\/\//i.test(props.campaign.onlineUrl ?? '') ? props.campaign.onlineUrl : null
);

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

// Where it is played: its address, or «Online» for a campaign played only online (as the table page)
const placeLabel = computed(() => {
    if (props.campaign.location) return props.campaign.location;
    return props.campaign.tableFormat === 'online' ? t('gameTables.online') : null;
});

const imageUrl = computed(() => buildHeroImageUrl(props.campaign.imagePublicId));
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6">
            <Link
                href="/campanas"
                class="inline-flex items-center text-sm text-base-secondary hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            >
                <ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" />
                {{ t('common.back') }}
            </Link>
        </div>

        <article class="overflow-hidden rounded-lg bg-surface shadow">
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
                    <FormatBadge
                        v-if="campaign.tableFormat && campaign.tableFormatLabel"
                        :format="campaign.tableFormat"
                        :label="campaign.tableFormatLabel"
                        :color="campaign.tableFormatColor ?? 'gray'"
                        size="md"
                    />
                    <span
                        v-if="campaign.acceptsNewPlayers && campaign.status !== 'recruiting'"
                        class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-sm font-medium text-base-primary"
                    >
                        {{ t('campaigns.lookingForPlayers') }}
                    </span>
                </div>

                <h1 class="mb-4 text-3xl font-bold text-base-primary sm:text-4xl">
                    {{ campaign.title }}
                    <HelpLink guide="game-tables.join-campaign" class="ml-1 align-middle" />
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
                <div class="mb-6 grid grid-cols-1 gap-4 rounded-lg bg-muted p-4 sm:grid-cols-2">
                    <!-- Frequency -->
                    <div v-if="campaign.frequencyLabel">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('campaigns.frequency') }}
                        </h3>
                        <div class="flex items-center text-base-secondary">
                            <Calendar class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                            <span>{{ campaign.frequencyLabel }}</span>
                        </div>
                        <p
                            v-if="campaign.scheduleNotes"
                            class="mt-1 whitespace-pre-line text-sm text-base-secondary"
                        >
                            {{ campaign.scheduleNotes }}
                        </p>
                    </div>

                    <!-- Schedule notes, when there is no frequency to go with -->
                    <div v-if="!campaign.frequencyLabel && campaign.scheduleNotes">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('campaigns.scheduleNotes') }}
                        </h3>
                        <p class="whitespace-pre-line text-base-secondary">
                            {{ campaign.scheduleNotes }}
                        </p>
                    </div>

                    <!-- Location -->
                    <div v-if="placeLabel">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.location') }}
                        </h3>
                        <div class="flex items-center text-base-secondary">
                            <MapPin class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                            <span>{{ placeLabel }}</span>
                        </div>
                    </div>

                    <!-- Online link: only its directors and roster get it -->
                    <div v-if="safeOnlineUrl">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('campaigns.onlineLink') }}
                        </h3>
                        <div class="flex items-center text-base-secondary">
                            <Globe class="mr-2 h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                            <a
                                :href="safeOnlineUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="break-all text-primary underline underline-offset-2 hover:no-underline focus:outline-none focus:ring-2 focus:ring-primary-500"
                                >{{ safeOnlineUrl }}</a
                            >
                        </div>
                    </div>

                    <!-- Format -->
                    <div v-if="campaign.tableFormatLabel">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.filters.format') }}
                        </h3>
                        <p class="text-base-secondary">
                            {{ campaign.tableFormatLabel }}
                        </p>
                    </div>

                    <!-- Duration -->
                    <div v-if="campaign.expectedDurationMonths">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('campaigns.expectedDuration') }}
                        </h3>
                        <div class="flex items-center text-base-secondary">
                            <Clock class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                            <span>
                                {{
                                    t('campaigns.monthsCount', {
                                        count: campaign.expectedDurationMonths,
                                    })
                                }}
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
                                    <User class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                                    <span>{{
                                        gm.isNamePublic
                                            ? gm.displayName
                                            : t('campaigns.anonymousGm')
                                    }}</span>
                                    <span
                                        v-if="gm.customTitle"
                                        class="ml-1 text-sm text-base-secondary"
                                        >({{ gm.customTitle }})</span
                                    >
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex items-center text-base-secondary">
                                    <User class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
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
                                <User class="mr-2 h-5 w-5 text-primary" aria-hidden="true" />
                                <span>{{
                                    gm.isNamePublic ? gm.displayName : t('campaigns.anonymousGm')
                                }}</span>
                                <span v-if="gm.customTitle" class="ml-1 text-sm text-base-secondary"
                                    >({{ gm.customTitle }})</span
                                >
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
                                {{
                                    t('campaigns.playersCount', {
                                        current: campaign.currentPlayers,
                                        max: campaign.maxPlayers,
                                    })
                                }}
                                <span v-if="campaign.minPlayers !== null" class="text-base-muted">
                                    ({{
                                        t('gameTables.playersRequired', {
                                            min: campaign.minPlayers,
                                            max: campaign.maxPlayers,
                                        })
                                    }})
                                </span>
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

                    <!-- Language -->
                    <div v-if="campaign.languageLabel">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('campaigns.language') }}
                        </h3>
                        <p class="text-base-secondary">{{ campaign.languageLabel }}</p>
                    </div>

                    <!-- Character Creation -->
                    <div v-if="campaign.characterCreationLabel">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.characterCreation') }}
                        </h3>
                        <p class="text-base-secondary">
                            {{ campaign.characterCreationLabel }}
                        </p>
                    </div>

                    <!-- Tone -->
                    <div v-if="campaign.toneLabel">
                        <h3 class="mb-1 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.tone') }}
                        </h3>
                        <p class="text-base-secondary">{{ campaign.toneLabel }}</p>
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

                <div v-if="surveyLinks" class="mb-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <SurveyLinks
                        :links="surveyLinks"
                        :manage-label="t('gameTables.surveys.manageCampaign')"
                    />
                </div>

                <!-- Additional Details -->
                <div class="mb-6 space-y-4">
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

                    <!-- Genres -->
                    <div v-if="campaign.genres.length > 0">
                        <h3 class="mb-2 text-sm font-semibold text-base-primary">
                            {{ t('gameTables.genres') }}
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="(genre, index) in campaign.genres"
                                :key="index"
                                class="rounded-full bg-muted px-3 py-1 text-sm text-base-secondary"
                            >
                                {{ genre }}
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

                    <!-- Custom Warnings (highlighted amber, as on the table page) -->
                    <div
                        v-if="campaign.customWarnings.length > 0"
                        class="rounded-lg border border-warning bg-warning-light p-4"
                    >
                        <h3 class="mb-2 flex items-center text-sm font-semibold text-warning">
                            <TriangleAlert class="mr-2 h-5 w-5 text-warning" aria-hidden="true" />
                            {{ t('gameTables.customWarningsTitle') }}
                        </h3>
                        <ul class="list-inside list-disc space-y-1 text-sm text-warning">
                            <li v-for="(warning, index) in campaign.customWarnings" :key="index">
                                {{ warning }}
                            </li>
                        </ul>
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
                        <div class="rounded-lg bg-success-light p-4 text-base-primary">
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
</template>
