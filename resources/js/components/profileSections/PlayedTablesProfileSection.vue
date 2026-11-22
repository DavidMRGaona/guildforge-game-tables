<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { VENUE_TIMEZONE } from '@/utils/datetime';

/**
 * «Últimas partidas» (`game-tables.played-tables`) on a public profile: the latest tables the owner
 * played in, newest first. The server sends it only to who may see the players; the card and its
 * title are the core's.
 */
interface ProfileTableItem {
    id: string;
    title: string;
    url: string;
    startsAt: string | null;
    gameSystem: string;
}

defineProps<{
    tables: ProfileTableItem[];
}>();

const { locale } = useI18n();

function formatDay(startsAt: string): string {
    return new Date(startsAt).toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <ul class="divide-y divide-[var(--color-border)]" role="list">
        <li v-for="table in tables" :key="table.id" class="py-2 first:pt-0 last:pb-0">
            <Link
                :href="table.url"
                class="group block rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            >
                <span
                    class="block break-words font-medium text-base-primary group-hover:text-primary"
                >
                    {{ table.title }}
                </span>
                <span
                    class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-base-muted"
                >
                    <span v-if="table.startsAt" class="inline-flex items-center gap-1">
                        <Calendar class="h-4 w-4 shrink-0" aria-hidden="true" />
                        <time :datetime="table.startsAt">{{ formatDay(table.startsAt) }}</time>
                    </span>
                    <span v-if="table.gameSystem" class="text-primary">{{ table.gameSystem }}</span>
                </span>
            </Link>
        </li>
    </ul>
</template>
