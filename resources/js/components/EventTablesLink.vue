<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface Props {
    event: {
        id: string;
        [key: string]: unknown;
    };
}

const props = defineProps<Props>();
const { t } = useI18n();

const count = ref(0);
const loaded = ref(false);

onMounted(async () => {
    try {
        const response = await fetch(`/api/mesas/count?event=${encodeURIComponent(props.event.id)}`);
        if (response.ok) {
            const data = await response.json() as { count: number };
            count.value = data.count;
        }
    } catch {
        // Silently fail - don't show the link
    } finally {
        loaded.value = true;
    }
});
</script>

<template>
    <Link
        v-if="loaded && count > 0"
        :href="`/mesas?event=${event.id}`"
        class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-700 transition-colors hover:bg-amber-100 hover:text-amber-800 dark:border-amber-800/40 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30 dark:hover:text-amber-300"
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
        <span>{{ t('gameTables.viewEventTablesCount', count) }}</span>
        <!-- Right arrow -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6" />
        </svg>
    </Link>
</template>
