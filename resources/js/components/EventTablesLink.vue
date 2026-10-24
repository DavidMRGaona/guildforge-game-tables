<script setup lang="ts">
import { ChevronRight, Dice5 } from '@lucide/vue';
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
        class="inline-flex items-center gap-2 rounded-lg border border-primary-500/30 bg-primary-500/10 px-4 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-primary-500/20 hover:underline"
    >
        <!-- Dice icon (SVG) -->
        <Dice5 class="h-4 w-4" aria-hidden="true" />
        <span>{{ t('gameTables.viewEventTablesCount', { count: gameTableCount }) }}</span>
        <!-- Right arrow -->
        <ChevronRight class="h-4 w-4" aria-hidden="true" />
    </Link>
</template>
