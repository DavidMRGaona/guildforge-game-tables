<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PaginatedResponse } from '@/types';
import type { GameTableListItem, GameSystem, GameTableFilters as GameTableFiltersType, EventFilter } from '../../types/gametables';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import GameTableCard from '../../components/GameTableCard.vue';
import GameTableCardSkeleton from '../../components/GameTableCardSkeleton.vue';
import GameTableFilters from '../../components/GameTableFilters.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { useSeo } from '@/composables/useSeo';
import { usePagination } from '@/composables/usePagination';
import { useGridLayout, type GridColumns } from '@/composables/useGridLayout';

interface Props {
    tables: PaginatedResponse<GameTableListItem>;
    gameSystems: GameSystem[];
    events: EventFilter[];
    currentFilters: GameTableFiltersType;
}

const props = withDefaults(defineProps<Props>(), {
    gameSystems: () => [],
    events: () => [],
    currentFilters: () => ({}),
});

const { t } = useI18n();

useSeo({
    title: t('gameTables.title'),
    description: t('gameTables.description'),
});

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
    <DefaultLayout>
        <div class="bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                            {{ t('gameTables.title') }}
                        </h1>
                        <p class="mt-2 text-lg text-stone-600 dark:text-stone-400">
                            {{ t('gameTables.description') }}
                        </p>
                    </div>

                    <!-- List / Calendar toggle -->
                    <div role="tablist" class="hidden items-center sm:flex">
                        <Link
                            href="/mesas"
                            role="tab"
                            :aria-selected="true"
                            :aria-label="t('gameTables.filters.listView')"
                            class="rounded-l-lg border border-amber-600 bg-amber-600 px-3 py-2 text-white"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        </Link>
                        <Link
                            href="/mesas/calendario"
                            role="tab"
                            :aria-selected="false"
                            :aria-label="t('gameTables.filters.calendarView')"
                            class="rounded-r-lg border border-stone-300 bg-white px-3 py-2 text-stone-600 transition-colors hover:bg-stone-50 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-300 dark:hover:bg-stone-600"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <GameTableFilters
                :game-systems="props.gameSystems"
                :events="props.events"
                :current-filters="props.currentFilters"
                base-path="/mesas"
                class="mb-6"
            />

            <!-- Results counter -->
            <p
                aria-live="polite"
                class="mb-4 text-sm text-stone-600 dark:text-stone-400"
            >
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
        </main>
    </DefaultLayout>
</template>
