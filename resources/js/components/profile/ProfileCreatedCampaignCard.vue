<script setup lang="ts">
import { CircleArrowRight, Clock, Users } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useVerificationGate } from '@/composables/useVerificationGate';
import VerificationBlockedButton from '../VerificationBlockedButton.vue';
import DraftActions from '../DraftActions.vue';
import DraftRejectionReason from '../DraftRejectionReason.vue';
import type { ProfileCreatedCampaign } from '../../types/gametables';

interface Props {
    campaign: ProfileCreatedCampaign;
    /** A draft's actions and the moderator's reason (the drafts list) */
    showActions?: boolean;
    /** Id of the section's line saying why the actions are disabled (shown once per drafts list) */
    actionsReasonId?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    showActions: false,
    actionsReasonId: null,
});

const { t } = useI18n();
// The roster page is closed to accounts that must verify their email
const gate = useVerificationGate();

// The look of BaseButton (secondary, size sm) on a link
const rosterLinkClass =
    'inline-flex min-h-[44px] w-full items-center justify-center rounded-md bg-muted px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-base-secondary/20 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface sm:w-auto';

const campaignUrl = computed(() =>
    props.campaign.slug ? `/campanas/${props.campaign.slug}` : null
);

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
                    <span v-else class="truncate text-base font-medium text-base-primary">
                        {{ campaign.title }}
                    </span>
                    <span class="shrink-0 text-xs text-base-muted">
                        {{ campaign.gameSystemName }}
                    </span>
                </div>

                <!-- Meta info -->
                <div
                    class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-base-muted"
                >
                    <!-- Frequency -->
                    <span v-if="campaign.frequencyLabel" class="flex items-center gap-1">
                        <Clock class="h-4 w-4" aria-hidden="true" />
                        {{ campaign.frequencyLabel }}
                    </span>

                    <!-- Players -->
                    <span class="flex items-center gap-1">
                        <Users class="h-4 w-4" aria-hidden="true" />
                        {{ playersText }} {{ t('campaigns.profile.players') }}
                    </span>

                    <!-- Recruiting badge -->
                    <span
                        v-if="campaign.isRecruiting"
                        class="inline-flex items-center gap-1 rounded-full bg-success-light px-2 py-0.5 text-xs font-medium text-base-primary"
                    >
                        <CircleArrowRight class="h-3 w-3" aria-hidden="true" />
                        {{ t('campaigns.profile.recruiting') }}
                    </span>
                </div>
            </div>

            <!-- Status badge -->
            <span
                :class="[
                    'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium',
                    statusColorClasses,
                ]"
            >
                {{ campaign.statusLabel }}
            </span>
        </div>

        <!-- A published campaign the user directs: its roster, with the requests awaiting an answer -->
        <div
            v-if="!showActions && campaign.rosterUrl"
            class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end"
        >
            <span
                v-if="campaign.pendingRequests > 0"
                class="inline-flex self-start rounded-full bg-warning-light px-2 py-0.5 text-xs font-medium text-base-primary sm:self-auto"
            >
                {{
                    t(
                        'campaigns.myCampaigns.pendingRequests',
                        { count: campaign.pendingRequests },
                        campaign.pendingRequests
                    )
                }}
            </span>
            <VerificationBlockedButton
                v-if="gate.blocked.value"
                :reason="gate.reason('form')"
                align="end"
                :button-class="rosterLinkClass"
            >
                {{ t('campaigns.myCampaigns.roster') }}
            </VerificationBlockedButton>
            <Link v-else :href="campaign.rosterUrl" :class="rosterLinkClass">
                {{ t('campaigns.myCampaigns.roster') }}
            </Link>
        </div>

        <!-- Rejected in review: the reason, addressed to the author, while it is back in their hands -->
        <DraftRejectionReason
            v-if="showActions && campaign.canEdit && campaign.rejectionReason"
            kind="campaign"
            :reason="campaign.rejectionReason"
            class="mt-3"
        />

        <!-- The same actions as «Mis campañas», while the author may still change it (not once sent to review) -->
        <DraftActions
            v-if="showActions && campaign.canEdit"
            kind="campaign"
            :route-key="campaign.id"
            :title="campaign.title"
            return-to="profile"
            :disabled-reason-id="actionsReasonId"
            class="mt-3"
        />
    </div>
</template>
