<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useVerificationGate } from '@/composables/useVerificationGate';
import type { ProfileCreatedCampaign } from '../../types/gametables';

interface Props {
    campaign: ProfileCreatedCampaign;
    showEditLink?: boolean;
    /** Id of the section's line saying why editing is disabled (shown once per drafts list) */
    editReasonId?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    showEditLink: false,
    editReasonId: null,
});

const { t } = useI18n();
// The edit form's routes are closed to accounts that must verify their email
const gate = useVerificationGate();

const campaignUrl = computed(() =>
    props.campaign.slug ? `/campanas/${props.campaign.slug}` : null
);

const editUrl = computed(() => `/campanas/mis-campanas/${props.campaign.id}/editar`);

const statusColorClasses = computed(() => {
    const colorMap: Record<string, string> = {
        gray: 'bg-muted text-base-secondary',
        success: 'bg-success-light text-base-primary',
        warning: 'bg-warning-light text-base-primary',
        primary: 'bg-primary-light text-primary',
        danger: 'bg-error-light text-base-primary',
    };
    return colorMap[props.campaign.statusColor] ?? colorMap.gray;
});

const playersText = computed(() => {
    if (props.campaign.maxPlayers === null) {
        return `${props.campaign.currentPlayers}`;
    }
    return `${props.campaign.currentPlayers}/${props.campaign.maxPlayers}`;
});
</script>

<template>
    <div class="rounded-lg border border-default bg-surface p-4 transition-shadow hover:shadow-md">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <!-- Title and game system -->
                <div class="flex items-center gap-2">
                    <Link
                        v-if="campaignUrl && campaign.isPublished"
                        :href="campaignUrl"
                        class="truncate text-base font-medium text-base-primary hover:underline"
                    >
                        {{ campaign.title }}
                    </Link>
                    <span
                        v-else
                        class="truncate text-base font-medium text-base-primary"
                    >
                        {{ campaign.title }}
                    </span>
                    <span class="shrink-0 text-xs text-base-muted">
                        {{ campaign.gameSystemName }}
                    </span>
                </div>

                <!-- Meta info -->
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-base-muted">
                    <!-- Frequency -->
                    <span v-if="campaign.frequencyLabel" class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ campaign.frequencyLabel }}
                    </span>

                    <!-- Players -->
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        {{ playersText }} {{ t('campaigns.profile.players') }}
                    </span>

                    <!-- Recruiting badge -->
                    <span
                        v-if="campaign.isRecruiting"
                        class="inline-flex items-center gap-1 rounded-full bg-success-light px-2 py-0.5 text-xs font-medium text-base-primary"
                    >
                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                        </svg>
                        {{ t('campaigns.profile.recruiting') }}
                    </span>
                </div>
            </div>

            <!-- Status badge and actions -->
            <div class="flex shrink-0 flex-col items-end gap-2">
                <span
                    :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusColorClasses]"
                >
                    {{ campaign.statusLabel }}
                </span>

                <!-- Edit link for drafts -->
                <template v-if="showEditLink && !campaign.isPublished">
                    <button
                        v-if="gate.blocked.value"
                        type="button"
                        disabled
                        :aria-describedby="editReasonId ?? undefined"
                        class="cursor-not-allowed text-sm text-primary opacity-60"
                    >
                        {{ t('campaigns.profile.edit') }}
                    </button>
                    <Link
                        v-else
                        :href="editUrl"
                        class="text-sm text-primary hover:underline"
                    >
                        {{ t('campaigns.profile.edit') }}
                    </Link>
                </template>
            </div>
        </div>
    </div>
</template>
