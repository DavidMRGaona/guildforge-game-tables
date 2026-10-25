<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PaginatedResponse } from '@/types';
import type {
    GameTableListItem,
    GameSystem,
    GameTableFilters as GameTableFiltersType,
    EventFilter,
} from '../../types/gametables';
import GameTableCard from '../../components/GameTableCard.vue';
import GameTableCardSkeleton from '../../components/GameTableCardSkeleton.vue';
import GameTableFilters from '../../components/GameTableFilters.vue';
import TablesViewToggle from '../../components/TablesViewToggle.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { usePagination } from '@/composables/usePagination';
import { useGridLayout, type GridColumns } from '@/composables/useGridLayout';

interface Props {
    tables: PaginatedResponse<GameTableListItem>;
    gameSystems?: GameSystem[];
    events?: EventFilter[];
    currentFilters?: GameTableFiltersType;
}

const props = withDefaults(defineProps<Props>(), {
    gameSystems: () => [],
    events: () => [],
    currentFilters: () => ({}),
});

const { t } = useI18n();

const isNavigating = ref(false);
const columns: GridColumns = 3;

const { gridClasses } = useGridLayout(() => columns);

const { firstItemNumber, lastItemNumber, hasPagination, goToPrev, goToNext, canGoPrev, canGoNext } =
    usePagination(() => props.tables);

const totalResults = computed(() => props.tables.meta.total);

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
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                        {{ t('gameTables.title') }}
                    </h1>
                    <p class="mt-2 text-lg text-base-secondary">
                        {{ t('gameTables.description') }}
                    </p>
                    <!-- The campaigns, the other list of the module -->
                    <Link
                        href="/campanas"
                        class="mt-2 inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-primary underline-offset-2 hover:underline"
                    >
                        {{ t('campaigns.viewAll') }}
                        <span aria-hidden="true">→</span>
                    </Link>
                </div>

                <!-- List / calendar toggle, also on phones -->
                <TablesViewToggle current="list" />
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <GameTableFilters
            :game-systems="props.gameSystems"
            :events="props.events"
            :current-filters="props.currentFilters"
            base-path="/mesas"
            class="mb-6"
        />

        <!-- Results counter -->
        <p aria-live="polite" class="mb-4 text-sm text-base-secondary">
            {{ t('gameTables.filters.resultsCount', { count: totalResults }) }}
        </p>

        <!-- Skeleton loading state -->
        <div v-if="isNavigating" class="grid gap-6" :class="gridClasses">
            <GameTableCardSkeleton v-for="n in 6" :key="n" />
        </div>

        <!-- Cards grid -->
        <div v-else-if="props.tables.data.length > 0" class="grid gap-6" :class="gridClasses">
            <GameTableCard v-for="table in props.tables.data" :key="table.id" :table="table" />
        </div>

        <EmptyState
            v-else
            icon="calendar"
            :title="t('common.noResults')"
            :description="t('gameTables.noTables')"
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
                <span class="font-medium">{{ props.tables.meta.total }}</span>
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
