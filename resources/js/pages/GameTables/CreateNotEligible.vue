<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';

interface Props {
    reason: string;
    canCreateAt?: string;
}

const props = defineProps<Props>();

const { t, d } = useI18n();

const reasonMessage = computed((): string => {
    const reasonMap: Record<string, string> = {
        authentication_required: t('gameTables.createNotEligible.reasonAuthenticationRequired'),
        role_required: t('gameTables.createNotEligible.reasonRoleRequired'),
        frontend_creation_disabled: t('gameTables.createNotEligible.reasonFrontendCreationDisabled'),
        tables_not_enabled_for_event: t('gameTables.createNotEligible.reasonTablesNotEnabledForEvent'),
        event_not_open: t('gameTables.createNotEligible.reasonEventNotOpen'),
    };

    return reasonMap[props.reason] ?? t('gameTables.createNotEligible.reasonDefault');
});

const formattedCanCreateAt = computed((): string | null => {
    if (!props.canCreateAt) {
        return null;
    }

    try {
        const date = new Date(props.canCreateAt);
        return d(date, 'long');
    } catch {
        return null;
    }
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-md text-center">
            <!-- Icon -->
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-light">
                <Lock class="h-8 w-8 text-primary" aria-hidden="true" />
            </div>

            <!-- Title -->
            <h1 class="mt-6 text-3xl font-bold tracking-tight text-base-primary">
                {{ t('gameTables.createNotEligible.title') }}
            </h1>

            <!-- Reason description -->
            <p class="mt-4 text-lg text-base-secondary">
                {{ reasonMessage }}
            </p>

            <!-- Can create at date -->
            <p
                v-if="formattedCanCreateAt"
                class="mt-2 text-sm text-base-muted"
            >
                {{ t('gameTables.createNotEligible.canCreateAt', { date: formattedCanCreateAt }) }}
            </p>

            <!-- Back to tables button -->
            <div class="mt-8">
                <Link href="/mesas">
                    <BaseButton variant="primary">
                        {{ t('gameTables.createNotEligible.backToTables') }}
                    </BaseButton>
                </Link>
            </div>
        </div>
    </div>
</template>
