<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface Props {
    event: {
        id: string;
        slug: string;
        [key: string]: unknown;
    };
    gameTableCount?: number | null;
}

const props = defineProps<Props>();
const { t } = useI18n();
</script>

<template>
    <Link
        v-if="gameTableCount && gameTableCount > 0"
        :href="`/mesas?event=${props.event.slug}`"
        class="inline-flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-light px-4 py-2.5 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-200 hover:text-primary-800 dark:border-primary-800/40 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/30 dark:hover:text-primary-300"
    >
        <!-- Dice icon (SVG) -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="2" width="20" height="20" rx="3" />
            <circle cx="8" cy="8" r="1.5" fill="currentColor" stroke="none" />
            <circle cx="16" cy="8" r="1.5" fill="currentColor" stroke="none" />
            <circle cx="8" cy="16" r="1.5" fill="currentColor" stroke="none" />
            <circle cx="16" cy="16" r="1.5" fill="currentColor" stroke="none" />
            <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
        </svg>
        <span>{{ t('gameTables.viewEventTablesCount', { count: gameTableCount }) }}</span>
        <!-- Right arrow -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6" />
        </svg>
    </Link>
</template>
