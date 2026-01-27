<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { GameTableListItem } from '../types/gametables';
import FormatBadge from './FormatBadge.vue';
import StatusBadge from './StatusBadge.vue';

interface Props {
    table: GameTableListItem;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const dateBadge = computed(() => {
    const date = new Date(props.table.startsAt);
    return {
        day: date.getDate(),
        month: date.toLocaleDateString(locale.value, { month: 'short' }).toUpperCase(),
    };
});

const formattedDate = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleDateString(locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
});

const formattedTime = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleTimeString(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
    });
});

const formattedDuration = computed(() => {
    const hours = Math.floor(props.table.durationMinutes / 60);
    const minutes = props.table.durationMinutes % 60;

    if (hours > 0 && minutes > 0) {
        return `${hours}h ${minutes}min`;
    } else if (hours > 0) {
        return `${hours}h`;
    } else {
        return `${minutes}min`;
    }
});

const capacityPercentage = computed(() => {
    if (props.table.maxPlayers === 0) return 0;
    return (props.table.currentPlayers / props.table.maxPlayers) * 100;
});

const capacityColor = computed(() => {
    const percentage = capacityPercentage.value;
    if (percentage >= 100) return 'bg-red-500';
    if (percentage >= 75) return 'bg-amber-500';
    return 'bg-green-500';
});

const headerGradient = computed(() => {
    const format = props.table.tableFormat.value;
    switch (format) {
        case 'in_person':
            return 'from-amber-600 to-stone-700';
        case 'online':
            return 'from-blue-600 to-stone-700';
        case 'hybrid':
            return 'from-purple-600 to-stone-700';
        default:
            return 'from-stone-600 to-stone-700';
    }
});

const availabilityBadge = computed(() => {
    if (props.table.isFull) {
        return {
            text: t('gameTables.complete'),
            classes: 'bg-red-500/90 text-white',
        };
    }
    return {
        text: t('gameTables.spots', { count: props.table.spotsAvailable }),
        classes: 'bg-green-500/90 text-white',
    };
});
</script>

<template>
    <Link
        :href="`/mesas/${table.id}`"
        :aria-label="t('aria.viewGameTable', { title: table.title })"
        class="group block overflow-hidden rounded-lg bg-white shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-stone-800 dark:shadow-stone-900/50 dark:focus:ring-offset-stone-900"
    >
        <!-- Header band with gradient -->
        <div
            :class="['relative flex items-center justify-between bg-gradient-to-r px-4 py-3', headerGradient]"
        >
            <!-- Date badge (top-left) -->
            <div
                class="flex flex-col items-center rounded bg-white/95 px-2 py-1 shadow-sm dark:bg-stone-800/95"
            >
                <span class="text-lg font-bold leading-none text-amber-600 dark:text-amber-500">
                    {{ dateBadge.day }}
                </span>
                <span class="text-[10px] font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">
                    {{ dateBadge.month }}
                </span>
            </div>

            <!-- Status + Availability badges (top-right) -->
            <div class="flex items-center gap-2">
                <span
                    :class="['rounded-full px-2 py-0.5 text-xs font-semibold', availabilityBadge.classes]"
                >
                    {{ availabilityBadge.text }}
                </span>
                <StatusBadge
                    :status="table.status.value"
                    :label="table.status.label"
                    :color="table.status.color"
                />
            </div>
        </div>

        <!-- Card body -->
        <div class="p-4">
            <!-- Zone 1: Identity -->
            <div class="mb-3">
                <h3
                    class="line-clamp-2 text-lg font-semibold text-stone-900 transition-colors group-hover:text-amber-600 dark:text-stone-100 dark:group-hover:text-amber-500"
                >
                    {{ table.title }}
                </h3>
                <p class="mt-0.5 text-sm font-medium text-amber-600 dark:text-amber-500">
                    {{ table.gameSystemName }}
                </p>
            </div>

            <!-- Zone 2: Key details -->
            <div class="mb-3 rounded-lg bg-stone-50 p-3 dark:bg-stone-700/50">
                <!-- Date / Time / Duration condensed -->
                <div class="mb-2 flex items-center text-sm text-stone-600 dark:text-stone-300">
                    <svg
                        class="mr-1.5 h-4 w-4 flex-shrink-0 text-stone-400 dark:text-stone-500"
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
                    <span>{{ formattedDate }} &middot; {{ formattedTime }} &middot; {{ formattedDuration }}</span>
                </div>

                <!-- Capacity bar -->
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs text-stone-600 dark:text-stone-400">
                        <span>{{ t('gameTables.capacity') }}</span>
                        <span class="font-medium">{{ table.currentPlayers }} / {{ table.maxPlayers }}</span>
                    </div>
                    <div
                        class="h-2.5 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-600"
                        role="progressbar"
                        :aria-valuenow="table.currentPlayers"
                        :aria-valuemin="0"
                        :aria-valuemax="table.maxPlayers"
                        :aria-label="t('gameTables.capacity')"
                    >
                        <div
                            :class="capacityColor"
                            :style="{ width: `${Math.min(capacityPercentage, 100)}%` }"
                            class="h-full rounded-full transition-all"
                        />
                    </div>
                </div>
            </div>

            <!-- Zone 3: Meta -->
            <div class="flex items-center justify-between">
                <!-- GM with avatar pill -->
                <div class="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-400">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 dark:bg-stone-700">
                        <svg
                            class="h-3.5 w-3.5"
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
                        <span class="max-w-[120px] truncate text-xs font-medium">{{ table.mainGameMasterName }}</span>
                    </span>
                </div>

                <!-- Format badge + Event -->
                <div class="flex items-center gap-2">
                    <FormatBadge
                        :format="table.tableFormat.value"
                        :label="table.tableFormat.label"
                        :color="table.tableFormat.color"
                    />
                    <span
                        v-if="table.eventTitle"
                        class="max-w-[100px] truncate text-xs text-stone-500 dark:text-stone-400"
                        :title="table.eventTitle"
                    >
                        {{ table.eventTitle }}
                    </span>
                </div>
            </div>
        </div>
    </Link>
</template>
