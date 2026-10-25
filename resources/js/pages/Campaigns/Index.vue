<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PaginatedResponse } from '@/types';
import type {
    CampaignListItem,
    FilterOption,
    GameSystem,
    GameTableFilters as GameTableFiltersType,
} from '../../types/gametables';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import CampaignCard from '../../components/CampaignCard.vue';
import GameTableFilters from '../../components/GameTableFilters.vue';
import { usePagination } from '@/composables/usePagination';
import { useGridLayout, type GridColumns } from '@/composables/useGridLayout';

interface Props {
    campaigns: PaginatedResponse<CampaignListItem>;
    gameSystems?: GameSystem[];
    currentFilters?: GameTableFiltersType;
}

const props = withDefaults(defineProps<Props>(), {
    gameSystems: () => [],
    currentFilters: () => ({}),
});

const { t } = useI18n();

const isNavigating = ref(false);
const columns: GridColumns = 3;

const { gridClasses } = useGridLayout(() => columns);

const { firstItemNumber, lastItemNumber, hasPagination, goToPrev, goToNext, canGoPrev, canGoNext } =
    usePagination(() => props.campaigns);

const totalResults = computed(() => props.campaigns.meta.total);

// The statuses the public listing can show: cancelled campaigns are left out
const campaignStatuses: FilterOption[] = [
    { value: 'recruiting', label: t('campaigns.status.recruiting') },
    { value: 'active', label: t('campaigns.status.active') },
    { value: 'on_hold', label: t('campaigns.status.onHold') },
    { value: 'completed', label: t('campaigns.status.completed') },
];

function handlePrev(): void {
    isNavigating.value = true;
    goToPrev();
}

function handleNext(): void {
    isNavigating.value = true;
    goToNext();
}
</script>

<template>
    <div class="bg-surface shadow">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                {{ t('campaigns.title') }}
            </h1>
            <p class="mt-2 text-lg text-base-secondary">
                {{ t('campaigns.description') }}
            </p>
            <!-- The tables, the other list of the module -->
            <Link
                href="/mesas"
                class="mt-2 inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-primary underline-offset-2 hover:underline"
            >
                {{ t('gameTables.viewAll') }}
                <span aria-hidden="true">→</span>
            </Link>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <GameTableFilters
            :game-systems="props.gameSystems"
            :current-filters="props.currentFilters"
            :statuses="campaignStatuses"
            :show-format="false"
            :show-event="false"
            base-path="/campanas"
            class="mb-6"
        />

        <!-- Results counter -->
        <p aria-live="polite" class="mb-4 text-sm text-base-secondary">
            {{ t('campaigns.found', { count: totalResults }) }}
        </p>

        <div v-if="props.campaigns.data.length > 0" class="grid gap-6" :class="gridClasses">
            <CampaignCard
                v-for="campaign in props.campaigns.data"
                :key="campaign.id"
                :campaign="campaign"
            />
        </div>

        <EmptyState
            v-else
            icon="document"
            :title="t('common.noResults')"
            :description="t('campaigns.noCampaigns')"
        />

        <div
            v-if="hasPagination"
            class="mt-8 flex items-center justify-between border-t border-default pt-6"
        >
            <p class="text-sm text-base-secondary">
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
    </div>
</template>
