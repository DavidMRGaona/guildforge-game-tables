<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { VENUE_TIMEZONE } from '@/utils/datetime';

/**
 * «Mesas» (`game-tables.tables`) on a public profile: the next tables the owner runs and, when the
 * viewer may see the players, those they play in. The card and its title are the core's. The owner
 * alone also learns how many tables they run stay out because their game master name is not public.
 */
interface ProfileTableItem {
    id: string;
    title: string;
    url: string;
    startsAt: string | null;
    gameSystem: string;
}

withDefaults(
    defineProps<{
        directing: ProfileTableItem[];
        playing: ProfileTableItem[];
        hiddenDirecting?: number;
    }>(),
    { hiddenDirecting: 0 }
);

const { t, locale } = useI18n();

function formatStart(startsAt: string): string {
    return new Date(startsAt).toLocaleString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="space-y-5">
        <template
            v-for="group in [
                { key: 'directing', tables: directing },
                { key: 'playing', tables: playing },
            ]"
            :key="group.key"
        >
            <div v-if="group.tables.length > 0" :data-group="group.key">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-base-muted">
                    {{ t(`gameTables.publicProfile.${group.key}`) }}
                </h3>
                <ul class="mt-2 divide-y divide-[var(--color-border)]" role="list">
                    <li
                        v-for="table in group.tables"
                        :key="table.id"
                        class="py-2 first:pt-0 last:pb-0"
                    >
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
                                    <time :datetime="table.startsAt">{{
                                        formatStart(table.startsAt)
                                    }}</time>
                                </span>
                                <span v-if="table.gameSystem" class="text-primary">{{
                                    table.gameSystem
                                }}</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </template>
        <p v-if="hiddenDirecting > 0" class="text-sm text-base-muted" data-note="hidden-directing">
            {{
                t(
                    'gameTables.publicProfile.hiddenDirecting',
                    { count: hiddenDirecting },
                    hiddenDirecting
                )
            }}
        </p>
    </div>
</template>
