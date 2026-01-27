<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { GameTableListItem, GameSystem } from '../../types/gametables';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import GameTableCard from '../../components/GameTableCard.vue';
import { useSeo } from '@/composables/useSeo';

interface Props {
    tables: GameTableListItem[];
    gameSystems: GameSystem[];
    currentMonth: string;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

useSeo({
    title: t('gameTables.calendar'),
    description: t('gameTables.description'),
});

const isNavigating = ref(false);

const currentMonthDate = computed(() => new Date(props.currentMonth));

const monthName = computed(() => {
    const formatted = currentMonthDate.value.toLocaleDateString(locale.value, {
        month: 'long',
        year: 'numeric',
    });
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
});

interface DayGroup {
    isoDate: string;
    displayDate: string;
    weekday: string;
    day: number;
    tables: GameTableListItem[];
}

const groupedTables = computed((): DayGroup[] => {
    const groups: Record<string, GameTableListItem[]> = {};

    props.tables.forEach((table) => {
        const date = new Date(table.startsAt);
        // Use ISO date string (YYYY-MM-DD) as key for reliable parsing
        const isoKey = date.toISOString().substring(0, 10);

        if (!groups[isoKey]) {
            groups[isoKey] = [];
        }

        groups[isoKey].push(table);
    });

    return Object.entries(groups)
        .map(([isoDate, tables]) => {
            const date = new Date(isoDate + 'T12:00:00');
            return {
                isoDate,
                displayDate: date.toLocaleDateString(locale.value, {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }),
                weekday: date.toLocaleDateString(locale.value, { weekday: 'long' }),
                day: date.getDate(),
                tables: tables.sort(
                    (a, b) => new Date(a.startsAt).getTime() - new Date(b.startsAt).getTime()
                ),
            };
        })
        .sort((a, b) => a.isoDate.localeCompare(b.isoDate));
});

function goToPreviousMonth(): void {
    isNavigating.value = true;
    const prevMonth = new Date(currentMonthDate.value);
    prevMonth.setMonth(prevMonth.getMonth() - 1);
    const monthParam = prevMonth.toISOString().substring(0, 7);
    router.visit(`/mesas/calendario?month=${monthParam}`);
}

function goToNextMonth(): void {
    isNavigating.value = true;
    const nextMonth = new Date(currentMonthDate.value);
    nextMonth.setMonth(nextMonth.getMonth() + 1);
    const monthParam = nextMonth.toISOString().substring(0, 7);
    router.visit(`/mesas/calendario?month=${monthParam}`);
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                            {{ t('gameTables.calendar') }}
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
                            :aria-selected="false"
                            :aria-label="t('gameTables.filters.listView')"
                            class="rounded-l-lg border border-stone-300 bg-white px-3 py-2 text-stone-600 transition-colors hover:bg-stone-50 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-300 dark:hover:bg-stone-600"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        </Link>
                        <Link
                            href="/mesas/calendario"
                            role="tab"
                            :aria-selected="true"
                            :aria-label="t('gameTables.filters.calendarView')"
                            class="rounded-r-lg border border-amber-600 bg-amber-600 px-3 py-2 text-white"
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
            <!-- Month Navigation -->
            <div class="mb-6 flex items-center justify-between">
                <BaseButton
                    variant="secondary"
                    :loading="isNavigating"
                    @click="goToPreviousMonth"
                >
                    <svg
                        class="h-5 w-5"
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
                    {{ t('common.previous') }}
                </BaseButton>

                <h2 class="text-2xl font-bold text-stone-900 dark:text-stone-100">
                    {{ monthName }}
                </h2>

                <BaseButton variant="secondary" :loading="isNavigating" @click="goToNextMonth">
                    {{ t('common.next') }}
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                </BaseButton>
            </div>

            <!-- Calendar View -->
            <div v-if="groupedTables.length > 0" class="space-y-8">
                <div v-for="group in groupedTables" :key="group.isoDate" class="space-y-4">
                    <!-- Date Header -->
                    <div class="sticky top-0 z-10 border-l-4 border-amber-600 bg-white py-2 pl-4 shadow-sm dark:bg-stone-800">
                        <h3 class="text-lg font-semibold text-stone-900 dark:text-stone-100">
                            {{ group.weekday }}, {{ group.day }}
                        </h3>
                    </div>

                    <!-- Tables for this day -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <GameTableCard
                            v-for="table in group.tables"
                            :key="table.id"
                            :table="table"
                        />
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-lg border border-dashed border-stone-300 p-12 text-center dark:border-stone-700"
            >
                <svg
                    class="mx-auto h-16 w-16 text-stone-400"
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
                <h3 class="mt-4 text-lg font-medium text-stone-900 dark:text-stone-100">
                    {{ t('gameTables.noTablesThisMonth') }}
                </h3>
                <p class="mt-2 text-stone-500 dark:text-stone-400">
                    {{ t('gameTables.tryDifferentMonth') }}
                </p>
            </div>
        </main>
    </DefaultLayout>
</template>
