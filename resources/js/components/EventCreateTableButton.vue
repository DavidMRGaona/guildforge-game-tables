<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

interface Props {
    event: {
        id: string;
        slug: string;
        [key: string]: unknown;
    };
}

interface EventCreationEligibility {
    eligible: boolean;
    reason: string | null;
    can_create_at: string | null;
    user_tier: number | null;
    can_create_tables: boolean;
    can_create_campaigns: boolean;
}

const props = defineProps<Props>();
const { t, d } = useI18n();
const { isAuthenticated } = useAuth();

const page = usePage();

const eligibility = computed((): EventCreationEligibility | null => {
    return page.props.eventCreationEligibility as EventCreationEligibility | null;
});

const showButton = computed(() => {
    if (!eligibility.value) return false;
    return eligibility.value.eligible && eligibility.value.can_create_tables;
});

const showEarlyAccessMessage = computed(() => {
    if (!eligibility.value) return false;
    return !eligibility.value.eligible && eligibility.value.can_create_at !== null;
});

const earlyAccessDate = computed(() => {
    if (!eligibility.value?.can_create_at) return null;
    return new Date(eligibility.value.can_create_at);
});

const formattedDate = computed(() => {
    if (!earlyAccessDate.value) return '';
    return d(earlyAccessDate.value, 'long');
});

const createTableUrl = computed(() => {
    return `/mesas/crear?event=${props.event.slug}`;
});
</script>

<template>
    <!-- Create table button when eligible -->
    <Link
        v-if="showButton"
        :href="createTableUrl"
        class="inline-flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-100 hover:text-primary-800 dark:border-primary-800/40 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/30 dark:hover:text-primary-300"
    >
        <!-- Plus icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        <span>{{ t('gameTables.eventActions.createTable') }}</span>
    </Link>

    <!-- Early access message when user needs to wait -->
    <div
        v-else-if="showEarlyAccessMessage && isAuthenticated"
        class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2.5 text-sm text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800/50 dark:text-neutral-400"
    >
        <!-- Clock icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
        <span>{{ t('gameTables.eventActions.creationOpensAt', { date: formattedDate }) }}</span>
    </div>

    <!-- Login prompt for unauthenticated users when tables are enabled -->
    <Link
        v-else-if="!isAuthenticated && eligibility"
        href="/iniciar-sesion"
        class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2.5 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800/50 dark:text-neutral-400 dark:hover:bg-neutral-700/50 dark:hover:text-neutral-300"
    >
        <!-- User icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </svg>
        <span>{{ t('gameTables.eventActions.loginToCreate') }}</span>
    </Link>
</template>
