<script setup lang="ts">
import { Calendar, User } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { GameTableListItem } from '../types/gametables';
import CapacityBar from './CapacityBar.vue';
import FormatBadge from './FormatBadge.vue';
import StatusBadge from './StatusBadge.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';
import { VENUE_TIMEZONE, venueDayKey } from '@/utils/datetime';

interface Props {
    table: GameTableListItem;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const imageUrl = computed(() => buildCardImageUrl(props.table.imagePublicId));

const dateBadge = computed(() => {
    const date = new Date(props.table.startsAt);
    return {
        day: Number(venueDayKey(date).slice(8)),
        month: date.toLocaleDateString(locale.value, { timeZone: VENUE_TIMEZONE, month: 'short' }).toUpperCase(),
    };
});

const formattedDate = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
});

const formattedTime = computed(() => {
    const date = new Date(props.table.startsAt);
    return date.toLocaleTimeString(locale.value, {
        timeZone: VENUE_TIMEZONE,
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

const availabilityBadge = computed(() => {
    if (props.table.isFull) {
        return {
            text: t('gameTables.complete'),
            classes: 'bg-error-light text-base-primary',
        };
    }
    return {
        text: t('gameTables.spots', { count: props.table.spotsAvailable }),
        classes: 'bg-success-light text-base-primary',
    };
});
</script>

<template>
    <Link
        :href="`/mesas/${table.slug}`"
        :aria-label="t('aria.viewGameTable', { title: table.title })"
        class="group flex h-full flex-col overflow-hidden rounded-lg bg-surface shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
    >
        <!-- Image with overlay badges (if image exists) -->
        <div v-if="imageUrl" class="relative">
            <img
                :src="imageUrl"
                :alt="table.title"
                class="h-36 w-full object-cover"
            />
            <!-- Overlay gradient for better badge visibility -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/30" />

            <!-- Date badge (top-left, overlaid on image) -->
            <div
                class="absolute left-3 top-3 flex flex-col items-center rounded bg-surface/95 px-2 py-1 shadow-sm"
            >
                <span class="text-lg font-bold leading-none text-primary">
                    {{ dateBadge.day }}
                </span>
                <span class="text-[10px] font-medium uppercase tracking-wide text-base-secondary">
                    {{ dateBadge.month }}
                </span>
            </div>

            <!-- Status + Availability badges (top-right, overlaid on image) -->
            <div class="absolute right-3 top-3 flex items-center gap-2">
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

        <!-- Neutral header band (only if no image); the format badge below names the format -->
        <div
            v-else
            class="flex min-h-16 items-center justify-between bg-muted px-4 py-3"
        >
            <!-- Date badge (top-left) -->
            <div
                class="flex flex-col items-center rounded bg-surface/95 px-2 py-1 shadow-sm"
            >
                <span class="text-lg font-bold leading-none text-primary">
                    {{ dateBadge.day }}
                </span>
                <span class="text-[10px] font-medium uppercase tracking-wide text-base-secondary">
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
        <div class="flex flex-1 flex-col p-4">
            <!-- Zone 1: Identity -->
            <div class="mb-3">
                <h3
                    class="line-clamp-2 text-lg font-semibold text-base-primary transition-colors group-hover:text-primary"
                >
                    {{ table.title }}
                </h3>
                <p class="mt-0.5 text-sm font-medium text-primary">
                    {{ table.gameSystemName }}
                </p>
            </div>

            <!-- Zone 2: Key details -->
            <div class="mb-3 rounded-lg bg-muted p-3">
                <!-- Date / Time / Duration condensed -->
                <div class="mb-2 flex items-center text-sm text-base-secondary">
                    <Calendar
                        class="mr-1.5 h-4 w-4 flex-shrink-0 text-base-muted"
                        aria-hidden="true"
                    />
                    <span>{{ formattedDate }} &middot; {{ formattedTime }} &middot; {{ formattedDuration }}</span>
                </div>

                <!-- Capacity bar -->
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs text-base-secondary">
                        <span>{{ t('gameTables.capacity') }}</span>
                        <span class="font-medium">{{ table.currentPlayers }} / {{ table.maxPlayers }}</span>
                    </div>
                    <CapacityBar
                        :current="table.currentPlayers"
                        :max="table.maxPlayers"
                        :label="t('gameTables.capacity')"
                        size="md"
                    />
                </div>
            </div>

            <!-- Zone 3: Meta (pinned to the bottom so footers line up across a grid row) -->
            <div class="mt-auto flex items-center justify-between">
                <!-- GM with avatar pill -->
                <div class="flex items-center gap-2 text-sm text-base-secondary">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1">
                        <User class="h-3.5 w-3.5" aria-hidden="true" />
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
                        class="max-w-[100px] truncate text-xs text-base-muted"
                        :title="table.eventTitle"
                    >
                        {{ table.eventTitle }}
                    </span>
                </div>
            </div>
        </div>
    </Link>
</template>
