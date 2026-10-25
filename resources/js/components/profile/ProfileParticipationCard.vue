<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import StatusBadge from '../StatusBadge.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface ProfileParticipation {
    id: string;
    gameTableId: string;
    gameTableTitle: string;
    gameTableSlug: string;
    gameTableStartsAt: string | null;
    gameSystemName: string;
    role: string;
    roleKey: string;
    roleColor: string;
    status: string;
    statusKey: string;
    statusColor: string;
    waitingListPosition: number | null;
    isUpcoming: boolean;
}

interface Props {
    participation: ProfileParticipation;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const tableUrl = computed(() => `/mesas/${props.participation.gameTableSlug}`);

const formattedDate = computed(() => {
    if (!props.participation.gameTableStartsAt) {
        return null;
    }
    const date = new Date(props.participation.gameTableStartsAt);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

const formattedTime = computed(() => {
    if (!props.participation.gameTableStartsAt) {
        return null;
    }
    const date = new Date(props.participation.gameTableStartsAt);
    return date.toLocaleTimeString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <Link
        :href="tableUrl"
        class="group block rounded-lg border border-default bg-surface p-4 transition-all hover:border-primary-300 hover:shadow-sm"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <!-- Left: Title and details -->
            <div class="min-w-0 flex-1">
                <h4
                    class="truncate text-base font-medium text-base-primary group-hover:text-primary"
                >
                    {{ participation.gameTableTitle }}
                </h4>
                <p class="mt-0.5 text-sm text-primary">
                    {{ participation.gameSystemName }}
                </p>

                <!-- Date and time -->
                <div
                    v-if="formattedDate"
                    class="mt-2 flex items-center gap-1.5 text-sm text-base-muted"
                >
                    <Calendar class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <span>{{ formattedDate }}</span>
                    <span v-if="formattedTime" class="text-base-secondary">&middot;</span>
                    <span v-if="formattedTime">{{ formattedTime }}</span>
                </div>
            </div>

            <!-- Right: Badges -->
            <div class="flex flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                <StatusBadge
                    :status="participation.roleKey"
                    :label="participation.role"
                    :color="participation.roleColor"
                />
                <StatusBadge
                    :status="participation.statusKey"
                    :label="participation.status"
                    :color="participation.statusColor"
                />
                <!-- Waiting list position -->
                <span
                    v-if="participation.waitingListPosition !== null"
                    class="inline-flex items-center rounded-full bg-info-light px-2 py-0.5 text-xs font-medium text-base-primary"
                >
                    {{ t('gameTables.profile.waitingPosition') }}: #{{ participation.waitingListPosition }}
                </span>
            </div>
        </div>
    </Link>
</template>
