<script setup lang="ts">
import HelpLink from '@/components/knowledge/HelpLink.vue';
import { Calendar, ChevronLeft, ChevronRight } from '@lucide/vue';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type {
    CampaignFilter,
    EventFilter,
    FilterOption,
    GameTableFilters as GameTableFiltersType,
    GameTableListItem,
    GameSystem,
} from '../../types/gametables';
import BaseButton from '@/components/ui/BaseButton.vue';
import GameTableCard from '../../components/GameTableCard.vue';
import GameTableFilters from '../../components/GameTableFilters.vue';
import TablesViewToggle from '../../components/TablesViewToggle.vue';
import { venueDayKey } from '@/utils/datetime';
import { tableFilterQuery } from '../../utils/tableFilterQuery';

interface Props {
    tables: GameTableListItem[];
    gameSystems: GameSystem[];
    // The same filters as the list, within the month shown (#314)
    events?: EventFilter[];
    genres?: FilterOption[];
    campaign?: CampaignFilter | null;
    currentFilters?: GameTableFiltersType;
    currentMonth: string;
}

const props = withDefaults(defineProps<Props>(), {
    events: () => [],
    genres: () => [],
    campaign: null,
    currentFilters: () => ({}),
});

const { t, locale } = useI18n();

const isNavigating = ref(false);

const currentMonthDate = computed(() => new Date(props.currentMonth));

const monthName = computed(() => {
    // 'YYYY-MM' parses as midnight UTC of the 1st: a calendar month, so read it in UTC
    const formatted = currentMonthDate.value.toLocaleDateString(locale.value, {
        timeZone: 'UTC',
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
            // The key is a calendar date, not an instant: read it in UTC, since any
            // other zone could move the noon it is anchored at onto the next day.
            const date = new Date(`${isoDate}T12:00:00Z`);
            return {
                isoDate,
                displayDate: date.toLocaleDateString(locale.value, {
                    timeZone: 'UTC',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                }),
                weekday: date.toLocaleDateString(locale.value, {
                    timeZone: 'UTC',
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

/** Moves to another month keeping the filters chosen. */
function goToMonth(delta: number): void {
    isNavigating.value = true;
    router.visit('/mesas/calendario', {
        data: {
            ...tableFilterQuery(props.currentFilters),
            month: shiftMonth(props.currentMonth, delta),
        },
    });
}

function goToPreviousMonth(): void {
    goToMonth(-1);
}

function goToNextMonth(): void {
    goToMonth(1);
}
</script>

<template>
    <div class="bg-surface shadow">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                        {{ t('gameTables.calendar') }}
                        <HelpLink guide="game-tables.tables-overview" class="ml-1 align-middle" />
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
                <TablesViewToggle
                    current="calendar"
                    :filters="props.currentFilters"
                    :month="props.currentMonth"
                />
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <GameTableFilters
            :game-systems="props.gameSystems"
            :events="props.events"
            :genres="props.genres"
            :current-filters="props.currentFilters"
            :campaign="props.campaign"
            :keep-params="{ month: props.currentMonth }"
            base-path="/mesas/calendario"
            class="mb-6"
        />

        <!-- Month navigation: on phones the arrows go without their words, so the month fits -->
        <div class="mb-6 flex items-center justify-between gap-2">
            <BaseButton variant="secondary" :loading="isNavigating" @click="goToPreviousMonth">
                <ChevronLeft class="h-5 w-5" aria-hidden="true" />
                <span class="sr-only sm:not-sr-only">{{ t('common.previous') }}</span>
            </BaseButton>

            <h2 class="min-w-0 text-center text-xl font-bold text-base-primary sm:text-2xl">
                {{ monthName }}
            </h2>

            <BaseButton variant="secondary" :loading="isNavigating" @click="goToNextMonth">
                <span class="sr-only sm:not-sr-only">{{ t('common.next') }}</span>
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
