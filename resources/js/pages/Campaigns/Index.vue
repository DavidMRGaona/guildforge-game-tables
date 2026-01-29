<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PaginatedResponse } from '@/types';
import type { CampaignListItem, GameSystem, GameTableFilters } from '../../types/gametables';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { useSeo } from '@/composables/useSeo';
import { usePagination } from '@/composables/usePagination';
import { useGridLayout, type GridColumns } from '@/composables/useGridLayout';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    campaigns: PaginatedResponse<CampaignListItem>;
    gameSystems: GameSystem[];
    currentFilters: GameTableFilters;
}

const props = withDefaults(defineProps<Props>(), {
    gameSystems: () => [],
    currentFilters: () => ({}),
});

const { t } = useI18n();

useSeo({
    title: t('campaigns.title'),
    description: t('campaigns.description'),
});

const isNavigating = ref(false);
const columns: GridColumns = 3;

const { gridClasses } = useGridLayout(() => columns);

const { firstItemNumber, lastItemNumber, hasPagination, goToPrev, goToNext, canGoPrev, canGoNext } =
    usePagination(() => props.campaigns);

function handlePrev(): void {
    isNavigating.value = true;
    goToPrev();
}

function handleNext(): void {
    isNavigating.value = true;
    goToNext();
}

function getSessionProgressLabel(campaign: CampaignListItem): string {
    if (campaign.sessionCount !== null && campaign.sessionCount > 0) {
        return t('campaigns.details.totalSessions', {
            current: campaign.currentSession,
            total: campaign.sessionCount,
        });
    }
    return t('campaigns.sessionNumber', { number: campaign.currentSession });
}

function getTotalSessionsLabel(campaign: CampaignListItem): string {
    if (campaign.totalSessions === 0) {
        return t('campaigns.totalSessionsUndetermined');
    }
    return String(campaign.totalSessions);
}

function getSessionProgress(campaign: CampaignListItem): number | null {
    if (campaign.sessionCount === null || campaign.sessionCount === 0) {
        return null;
    }
    return (campaign.currentSession / campaign.sessionCount) * 100;
}

function getImageUrl(campaign: CampaignListItem): string | null {
    return buildCardImageUrl(campaign.imagePublicId);
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                    {{ t('campaigns.title') }}
                </h1>
                <p class="mt-2 text-lg text-stone-600 dark:text-stone-400">
                    {{ t('campaigns.description') }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="props.campaigns.data.length > 0" class="grid gap-6" :class="gridClasses">
                <Link
                    v-for="campaign in props.campaigns.data"
                    :key="campaign.id"
                    :href="`/campanas/${campaign.id}`"
                    class="block transition-all duration-200 hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-stone-900"
                >
                    <BaseCard :padding="false">
                        <!-- Campaign Image -->
                        <div v-if="getImageUrl(campaign)" class="relative">
                            <img
                                :src="getImageUrl(campaign)!"
                                :alt="campaign.title"
                                class="h-40 w-full object-cover"
                            />
                        </div>

                        <div class="p-4">
                            <!-- Header with title and badges -->
                            <div class="mb-3 flex flex-wrap items-start gap-2">
                                <h3
                                    class="line-clamp-2 flex-1 text-lg font-semibold text-stone-900 dark:text-stone-100"
                                >
                                    {{ campaign.title }}
                                </h3>
                                <div class="flex flex-wrap gap-1">
                                    <StatusBadge
                                        :status="campaign.status"
                                        :label="campaign.statusLabel"
                                        :color="campaign.statusColor"
                                    />
                                    <span
                                        v-if="campaign.acceptsNewPlayers"
                                        class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        {{ t('campaigns.lookingForPlayers') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Game System -->
                            <p class="mb-2 text-sm font-medium text-amber-600 dark:text-amber-500">
                                {{ campaign.gameSystemName }}
                            </p>

                            <!-- Frequency -->
                            <div
                                v-if="campaign.frequencyLabel"
                                class="mb-2 flex items-center text-sm text-stone-600 dark:text-stone-400"
                            >
                                <svg
                                    class="mr-2 h-4 w-4"
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

                            <!-- Session Progress -->
                            <div class="mb-3 flex items-center text-sm text-stone-600 dark:text-stone-400">
                                <svg
                                    class="mr-2 h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <span>{{ getSessionProgressLabel(campaign) }}</span>
                            </div>

                            <!-- Session Progress Bar (only if there's a defined session count) -->
                            <div v-if="getSessionProgress(campaign) !== null" class="mb-3">
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700"
                                >
                                    <div
                                        class="h-full bg-amber-500 transition-all"
                                        :style="{ width: `${getSessionProgress(campaign)}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Total Sessions -->
                            <div class="mb-3 flex items-center text-sm text-stone-600 dark:text-stone-400">
                                <svg
                                    class="mr-2 h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                    />
                                </svg>
                                <span
                                    >{{ t('campaigns.totalSessions') }}:
                                    {{ getTotalSessionsLabel(campaign) }}</span
                                >
                            </div>

                            <!-- Capacity (only if maxPlayers is defined) -->
                            <div v-if="campaign.maxPlayers !== null" class="mb-2">
                                <div
                                    class="mb-1 flex items-center justify-between text-xs text-stone-600 dark:text-stone-400"
                                >
                                    <span>{{ t('campaigns.players') }}</span>
                                    <span>{{ campaign.currentPlayers }} / {{ campaign.maxPlayers }}</span>
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700"
                                >
                                    <div
                                        :class="
                                            campaign.currentPlayers >= campaign.maxPlayers
                                                ? 'bg-red-500'
                                                : 'bg-green-500'
                                        "
                                        :style="{
                                            width: `${(campaign.currentPlayers / campaign.maxPlayers) * 100}%`,
                                        }"
                                        class="h-full transition-all"
                                    />
                                </div>
                            </div>

                            <!-- Footer -->
                            <div
                                class="flex items-center justify-between border-t border-stone-200 pt-3 dark:border-stone-700"
                            >
                                <div class="flex items-center text-sm text-stone-600 dark:text-stone-400">
                                    <svg
                                        class="mr-2 h-4 w-4"
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
                                    <span>{{ campaign.mainGameMasterName }}</span>
                                </div>
                            </div>
                        </div>
                    </BaseCard>
                </Link>
            </div>

            <EmptyState
                v-else
                icon="document"
                :title="t('common.noResults')"
                :description="t('campaigns.noCampaigns')"
            />

            <div
                v-if="hasPagination"
                class="mt-8 flex items-center justify-between border-t border-stone-200 pt-6 dark:border-stone-700"
            >
                <p class="text-sm text-stone-700 dark:text-stone-300">
                    {{ t('common.showing') }}
                    <span class="font-medium">
                        {{ firstItemNumber }}
                    </span>
                    -
                    <span class="font-medium">
                        {{ lastItemNumber }}
                    </span>
                    {{ t('common.of') }}
                    <span class="font-medium">{{ props.campaigns.meta.total }}</span>
                </p>

                <div class="flex gap-2">
                    <BaseButton
                        variant="secondary"
                        :disabled="!canGoPrev"
                        :loading="isNavigating"
                        @click="handlePrev"
                    >
                        {{ t('common.previous') }}
                    </BaseButton>
                    <BaseButton
                        variant="secondary"
                        :disabled="!canGoNext"
                        :loading="isNavigating"
                        @click="handleNext"
                    >
                        {{ t('common.next') }}
                    </BaseButton>
                </div>
            </div>
        </main>
    </DefaultLayout>
</template>
