<script setup lang="ts">
import { Calendar, ChevronLeft, ChevronRight } from '@lucide/vue';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { GameTableListItem, GameSystem } from '../../types/gametables';
import BaseButton from '@/components/ui/BaseButton.vue';
import GameTableCard from '../../components/GameTableCard.vue';
import TablesViewToggle from '../../components/TablesViewToggle.vue';
import { VENUE_TIMEZONE, venueDayKey } from '@/utils/datetime';

interface Props {
    tables: GameTableListItem[];
    gameSystems: GameSystem[];
    currentMonth: string;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const isNavigating = ref(false);

const currentMonthDate = computed(() => new Date(props.currentMonth));

const monthName = computed(() => {
    const formatted = currentMonthDate.value.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
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
        // Group by the calendar day at the venue: toISOString() would bucket
        // by UTC day and move late-evening tables to the previous day.
        const isoKey = venueDayKey(new Date(table.startsAt));

        if (!groups[isoKey]) {
            groups[isoKey] = [];
        }

        groups[isoKey].push(table);
    });

    return Object.entries(groups)
        .map(([isoDate, tables]) => {
            const date = new Date(`${isoDate}T12:00:00Z`);
            return {
                isoDate,
                displayDate: date.toLocaleDateString(locale.value, {
                    timeZone: VENUE_TIMEZONE,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }),
                weekday: date.toLocaleDateString(locale.value, {
                    timeZone: VENUE_TIMEZONE,
                    weekday: 'long',
                }),
                day: Number(isoDate.slice(8)),
                tables: tables.sort(
                    (a, b) => new Date(a.startsAt).getTime() - new Date(b.startsAt).getTime()
                ),
            };
        })
        .sort((a, b) => a.isoDate.localeCompare(b.isoDate));
});

/**
 * Shift a 'YYYY-MM' month by whole months.
 *
 * Done as plain calendar arithmetic on the string: going through a Date would
 * mix UTC parsing with local getters and could land on the wrong month.
 */
function shiftMonth(month: string, delta: number): string {
    const [year = 0, monthNumber = 1] = month.split('-').map(Number);
    const monthsSinceYearZero = year * 12 + (monthNumber - 1) + delta;

    return `${Math.floor(monthsSinceYearZero / 12)}-${String((monthsSinceYearZero % 12) + 1).padStart(2, '0')}`;
}

function goToPreviousMonth(): void {
    isNavigating.value = true;
    router.visit(`/mesas/calendario?month=${shiftMonth(props.currentMonth, -1)}`);
}

function goToNextMonth(): void {
    isNavigating.value = true;
    const monthParam = shiftMonth(props.currentMonth, 1);
    router.visit(`/mesas/calendario?month=${monthParam}`);
}
</script>

<template>
    <div class="bg-surface shadow">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                        {{ t('gameTables.calendar') }}
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
                <TablesViewToggle current="calendar" />
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Month Navigation -->
        <div class="mb-6 flex items-center justify-between">
            <BaseButton variant="secondary" :loading="isNavigating" @click="goToPreviousMonth">
                <ChevronLeft class="h-5 w-5" aria-hidden="true" />
                {{ t('common.previous') }}
            </BaseButton>

            <h2 class="text-2xl font-bold text-base-primary">
                {{ monthName }}
            </h2>

            <BaseButton variant="secondary" :loading="isNavigating" @click="goToNextMonth">
                {{ t('common.next') }}
                <ChevronRight class="h-5 w-5" aria-hidden="true" />
            </BaseButton>
        </div>

        <!-- Calendar View -->
        <div v-if="groupedTables.length > 0" class="space-y-8">
            <div v-for="group in groupedTables" :key="group.isoDate" class="space-y-4">
                <!-- Date Header -->
                <div
                    class="sticky top-0 z-10 border-l-4 border-primary-600 bg-surface py-2 pl-4 shadow-sm"
                >
                    <h3 class="text-lg font-semibold text-base-primary">
                        {{ group.weekday }}, {{ group.day }}
                    </h3>
                </div>

                <!-- Tables for this day -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <GameTableCard v-for="table in group.tables" :key="table.id" :table="table" />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="rounded-lg border border-dashed border-default p-12 text-center">
            <Calendar class="mx-auto h-16 w-16 text-base-muted" aria-hidden="true" />
            <h3 class="mt-4 text-lg font-medium text-base-primary">
                {{ t('gameTables.noTablesThisMonth') }}
            </h3>
            <p class="mt-2 text-base-secondary">
                {{ t('gameTables.tryDifferentMonth') }}
            </p>
        </div>
    </div>
</template>
