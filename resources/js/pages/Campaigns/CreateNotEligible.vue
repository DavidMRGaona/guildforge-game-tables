<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    reason: string;
    canCreateAt?: string;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const reasonMessage = computed((): string => {
    const reasonMap: Record<string, string> = {
        authentication_required: t('campaigns.createNotEligible.reasonAuthenticationRequired'),
        role_required: t('campaigns.createNotEligible.reasonRoleRequired'),
        frontend_creation_disabled: t('campaigns.createNotEligible.reasonFrontendCreationDisabled'),
    };

    return reasonMap[props.reason] ?? t('campaigns.createNotEligible.reasonDefault');
});

const formattedCanCreateAt = computed((): string | null => {
    if (!props.canCreateAt) {
        return null;
    }

    const date = new Date(props.canCreateAt);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    // The day and time at the venue, wherever the visitor browses from
    return date.toLocaleString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-md text-center">
            <!-- Icon -->
            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-light"
            >
                <Lock class="h-8 w-8 text-primary" aria-hidden="true" />
            </div>

            <!-- Title -->
            <h1 class="mt-6 text-3xl font-bold tracking-tight text-base-primary">
                {{ t('campaigns.createNotEligible.title') }}
            </h1>

            <!-- Reason description -->
            <p class="mt-4 text-lg text-base-secondary">
                {{ reasonMessage }}
            </p>

            <!-- Can create at date -->
            <p v-if="formattedCanCreateAt" class="mt-2 text-sm text-base-muted">
                {{ t('campaigns.createNotEligible.canCreateAt', { date: formattedCanCreateAt }) }}
            </p>

            <!-- Back to campaigns button -->
            <div class="mt-8">
                <Link href="/campanas">
                    <BaseButton variant="primary">
                        {{ t('campaigns.createNotEligible.backToCampaigns') }}
                    </BaseButton>
                </Link>
            </div>
        </div>
    </div>
</template>
