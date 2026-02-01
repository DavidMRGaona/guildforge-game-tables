<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { ProfileCreatedTable } from '../../types/gametables';

interface Props {
    table: ProfileCreatedTable;
    showEditLink?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEditLink: false,
});

const { t, locale } = useI18n();

const formattedDate = computed(() => {
    if (!props.table.startsAt) return null;
    const date = new Date(props.table.startsAt);
    return date.toLocaleDateString(locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

const tableUrl = computed(() =>
    props.table.slug ? `/mesas/${props.table.slug}` : null
);

const editUrl = computed(() => `/mesas/mis-mesas/${props.table.id}/editar`);

const statusColorClasses = computed(() => {
    const colorMap: Record<string, string> = {
        gray: 'bg-stone-100 text-stone-700 dark:bg-stone-700 dark:text-stone-300',
        success: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        warning: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        primary: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        danger: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };
    return colorMap[props.table.statusColor] ?? colorMap.gray;
});

const playersText = computed(() => {
    return `${props.table.currentPlayers}/${props.table.maxPlayers}`;
});
</script>

<template>
    <div class="rounded-lg border border-stone-200 bg-white p-4 transition-shadow hover:shadow-md dark:border-stone-700 dark:bg-stone-800">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <!-- Title and game system -->
                <div class="flex items-center gap-2">
                    <Link
                        v-if="tableUrl && table.isPublished"
                        :href="tableUrl"
                        class="truncate text-base font-medium text-stone-900 hover:text-amber-600 dark:text-stone-100 dark:hover:text-amber-400"
                    >
                        {{ table.title }}
                    </Link>
                    <span
                        v-else
                        class="truncate text-base font-medium text-stone-900 dark:text-stone-100"
                    >
                        {{ table.title }}
                    </span>
                    <span class="shrink-0 text-xs text-stone-500 dark:text-stone-400">
                        {{ table.gameSystemName }}
                    </span>
                </div>

                <!-- Meta info -->
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-stone-500 dark:text-stone-400">
                    <!-- Date -->
                    <span v-if="formattedDate" class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ formattedDate }}
                    </span>

                    <!-- Players -->
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        {{ playersText }} {{ t('gameTables.profile.created.players') }}
                    </span>

                    <!-- Format -->
                    <span class="text-xs">
                        {{ table.tableFormatLabel }}
                    </span>

                    <!-- Event -->
                    <span v-if="table.eventTitle" class="truncate text-xs">
                        {{ table.eventTitle }}
                    </span>
                </div>
            </div>

            <!-- Status badge and actions -->
            <div class="flex shrink-0 flex-col items-end gap-2">
                <span
                    :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusColorClasses]"
                >
                    {{ table.statusLabel }}
                </span>

                <!-- Edit link for drafts -->
                <Link
                    v-if="showEditLink && !table.isPublished"
                    :href="editUrl"
                    class="text-sm text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                >
                    {{ t('gameTables.profile.created.edit') }}
                </Link>
            </div>
        </div>
    </div>
</template>
