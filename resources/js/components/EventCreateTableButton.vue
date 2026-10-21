<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { useVerificationGate } from '@/composables/useVerificationGate';
import VerificationBlockedButton from './VerificationBlockedButton.vue';

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
    has_early_access: boolean;
    public_open_date: string | null;
    requires_authentication: boolean;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const page = usePage();
// GET /mesas/crear is closed to accounts that must verify their email
const gate = useVerificationGate();
const isAuthenticated = computed(() => {
    const auth = page.props.auth as { user?: unknown } | undefined;
    return auth?.user != null;
});

const eligibility = computed((): EventCreationEligibility | null => {
    return page.props.eventCreationEligibility as EventCreationEligibility | null;
});

// State: Can create now
const canCreateNow = computed(() => {
    if (!eligibility.value) return false;
    return eligibility.value.eligible && eligibility.value.can_create_tables;
});

// State: Has a future date restriction
const hasFutureDateRestriction = computed(() => {
    if (!eligibility.value) return false;
    return !eligibility.value.eligible && eligibility.value.can_create_at !== null;
});

// State: Early access for members (not logged in, has early access tier)
const showEarlyAccessForMembers = computed(() => {
    if (!eligibility.value) return false;
    return !isAuthenticated.value
        && hasFutureDateRestriction.value
        && eligibility.value.has_early_access;
});

// State: General opening pending (not logged in, no early access)
const showGeneralOpeningPending = computed(() => {
    if (!eligibility.value) return false;
    return !isAuthenticated.value
        && hasFutureDateRestriction.value
        && !eligibility.value.has_early_access;
});

// State: Logged in, waiting (with or without early access privilege)
const showWaitingAuthenticated = computed(() => {
    if (!eligibility.value) return false;
    return isAuthenticated.value && hasFutureDateRestriction.value;
});

// State: Requires login (no date restriction, just needs authentication)
const showLoginRequired = computed(() => {
    if (!eligibility.value) return false;
    return !isAuthenticated.value
        && !hasFutureDateRestriction.value
        && eligibility.value.requires_authentication;
});

// Formatted dates
const userOpenDate = computed(() => {
    if (!eligibility.value?.can_create_at) return null;
    return new Date(eligibility.value.can_create_at);
});

const publicOpenDate = computed(() => {
    if (!eligibility.value?.public_open_date) return null;
    return new Date(eligibility.value.public_open_date);
});

const formattedUserDate = computed(() => {
    if (!userOpenDate.value) return '';
    return userOpenDate.value.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const formattedPublicDate = computed(() => {
    if (!publicOpenDate.value) return '';
    return publicOpenDate.value.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const createTableUrl = computed(() => {
    return `/mesas/crear?event=${props.event.slug}`;
});
</script>

<template>
    <!-- State 1b: Could create now, but the account must verify its email first -->
    <VerificationBlockedButton
        v-if="canCreateNow && gate.blocked.value"
        :reason="gate.reason('createTable')"
        button-class="inline-flex items-center gap-2 rounded-lg border border-primary-500/30 bg-primary-500/10 px-4 py-2.5 text-sm font-medium text-primary"
    >
        <!-- Plus icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        <span>{{ t('gameTables.eventActions.createTable') }}</span>
    </VerificationBlockedButton>

    <!-- State 1: Can create now -->
    <Link
        v-else-if="canCreateNow"
        :href="createTableUrl"
        class="inline-flex items-center gap-2 rounded-lg border border-primary-500/30 bg-primary-500/10 px-4 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-primary-500/20 hover:underline"
    >
        <!-- Plus icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        <span>{{ t('gameTables.eventActions.createTable') }}</span>
    </Link>

    <!-- State 2: Early access for members (not logged in) -->
    <Link
        v-else-if="showEarlyAccessForMembers"
        href="/iniciar-sesion"
        class="inline-flex flex-col gap-1 rounded-lg border border-info/40 bg-info/10 px-4 py-2.5 text-sm transition-colors hover:bg-info/20"
    >
        <span class="flex items-center gap-2 font-medium text-base-primary">
            <!-- Star icon -->
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
            </svg>
            <span>{{ t('gameTables.eventActions.earlyAccessForMembers') }}</span>
            <!-- Clock icon -->
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
        </span>
        <span class="text-info">
            {{ t('gameTables.eventActions.generalOpeningAt', { date: formattedPublicDate }) }}
        </span>
    </Link>

    <!-- State 3: General opening pending (not logged in, no early access) -->
    <div
        v-else-if="showGeneralOpeningPending"
        class="inline-flex items-center gap-2 rounded-lg border border-default bg-page px-4 py-2.5 text-sm text-base-secondary"
        role="status"
    >
        <!-- Clock icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
        <span>{{ t('gameTables.eventActions.publicOpeningAt', { date: formattedUserDate }) }}</span>
    </div>

    <!-- State 4: Logged in, waiting with early access privilege -->
    <div
        v-else-if="showWaitingAuthenticated && eligibility?.has_early_access"
        class="inline-flex items-center gap-2 rounded-lg border border-info/40 bg-info-light px-4 py-2.5 text-sm text-base-primary"
        role="status"
    >
        <!-- Star icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </svg>
        <!-- Clock icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
        <span>{{ t('gameTables.eventActions.earlyAccessActiveFrom', { date: formattedUserDate }) }}</span>
    </div>

    <!-- State 5: Logged in, waiting without early access -->
    <div
        v-else-if="showWaitingAuthenticated"
        class="inline-flex items-center gap-2 rounded-lg border border-default bg-page px-4 py-2.5 text-sm text-base-secondary"
        role="status"
    >
        <!-- Clock icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
        <span>{{ t('gameTables.eventActions.creationOpensAt', { date: formattedUserDate }) }}</span>
    </div>

    <!-- State 6: Requires login (no date restriction) -->
    <Link
        v-else-if="showLoginRequired"
        href="/iniciar-sesion"
        class="inline-flex items-center gap-2 rounded-lg border border-default bg-base-secondary/10 px-4 py-2.5 text-sm font-medium text-base-secondary transition-colors hover:bg-base-secondary/20 hover:underline"
    >
        <!-- User icon -->
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </svg>
        <span>{{ t('gameTables.eventActions.loginToCreate') }}</span>
    </Link>
</template>
