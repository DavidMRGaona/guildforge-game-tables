<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import StatusBadge from '../StatusBadge.vue';

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
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <Link
        :href="tableUrl"
        class="group block rounded-lg border border-stone-200 bg-white p-4 transition-all hover:border-amber-300 hover:shadow-sm dark:border-stone-700 dark:bg-stone-800 dark:hover:border-amber-600"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <!-- Left: Title and details -->
            <div class="min-w-0 flex-1">
                <h4
                    class="truncate text-base font-medium text-stone-900 group-hover:text-amber-600 dark:text-stone-100 dark:group-hover:text-amber-500"
                >
                    {{ participation.gameTableTitle }}
                </h4>
                <p class="mt-0.5 text-sm text-amber-600 dark:text-amber-500">
                    {{ participation.gameSystemName }}
                </p>

                <!-- Date and time -->
                <div
                    v-if="formattedDate"
                    class="mt-2 flex items-center gap-1.5 text-sm text-stone-500 dark:text-stone-400"
                >
                    <svg
                        class="h-4 w-4 shrink-0"
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
                    <span>{{ formattedDate }}</span>
                    <span v-if="formattedTime" class="text-stone-400 dark:text-stone-500">&middot;</span>
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
                    class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                >
                    {{ t('gameTables.profile.waitingPosition') }}: #{{ participation.waitingListPosition }}
                </span>
            </div>
        </div>
    </Link>
</template>
