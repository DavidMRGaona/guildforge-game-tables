<script setup lang="ts">
import { CircleArrowRight, Clock, Users } from '@lucide/vue';
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import DraftActions from '../../components/DraftActions.vue';
import DraftRejectionReason from '../../components/DraftRejectionReason.vue';
import { useFlashMessages } from '@/composables/useFlashMessages';
import type { CampaignResponseDTO } from '../../types/gametables';

interface Props {
    campaigns: CampaignResponseDTO[];
    canCreate: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    campaigns: () => [],
    canCreate: false,
});

// What the last action did (created, saved, sent, deleted) or why it was refused (e.g. a campaign
// already sent to review or published)
const { success: flashSuccess, error: flashError } = useFlashMessages();

const { t } = useI18n();

const hasCampaigns = computed(() => props.campaigns.length > 0);

function isApproved(campaign: CampaignResponseDTO): boolean {
    return campaign.is_published;
}

function isPendingReview(campaign: CampaignResponseDTO): boolean {
    return !campaign.is_published && campaign.frontend_creation_status === 'pending_review';
}

function getStatusColor(campaign: CampaignResponseDTO): string {
    // Use frontend creation status color if available, otherwise use status color
    if (campaign.frontend_creation_status) {
        const statusColors: Record<string, string> = {
            draft: 'gray',
            pending_review: 'warning',
            approved: 'success',
            rejected: 'danger',
        };
        return statusColors[campaign.frontend_creation_status] ?? campaign.status_color;
    }
    return campaign.status_color;
}

function getStatusLabel(campaign: CampaignResponseDTO): string {
    return campaign.frontend_creation_status_label ?? campaign.status_label;
}

function handleCreate(): void {
    router.visit('/campanas/crear');
}

// Links styled as BaseButton (primary / secondary, size sm)
const primaryLinkClass = 'inline-flex min-h-[44px] items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface';
const secondaryLinkClass = 'inline-flex min-h-[44px] items-center justify-center rounded-md bg-base-secondary/10 px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-base-secondary/20 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface';

// «Semanal · 4/5 jugadores · Aceptando nuevos jugadores» on the campaigns the viewer directs
function directedSummary(campaign: CampaignResponseDTO): string {
    const players = campaign.max_players !== null
        ? t('campaigns.playersCount', { current: campaign.current_players, max: campaign.max_players })
        : t('campaigns.myCampaigns.playersNoMax', { count: campaign.current_players }, campaign.current_players);
    const recruiting = campaign.accepts_new_players
        ? t('campaigns.recruiting')
        : t('campaigns.notRecruiting');

    return campaign.frequency_label
        ? t('campaigns.myCampaigns.summary', { frequency: campaign.frequency_label, players, recruiting })
        : t('campaigns.myCampaigns.summaryNoFrequency', { players, recruiting });
}

function rosterLink(campaign: CampaignResponseDTO): string {
    return `/campanas/mis-campanas/${campaign.id}/plantilla`;
}

function addSessionLink(campaign: CampaignResponseDTO): string {
    return `/mesas/crear?campaign=${campaign.id}`;
}

function getCampaignLink(campaign: CampaignResponseDTO): string {
    if (campaign.slug && campaign.is_published) {
        return `/campanas/${campaign.slug}`;
    }
    return '#';
}
</script>

<template>
    <div class="bg-surface shadow">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <!-- Back to the profile tab that links here -->
                    <Link
                        href="/perfil?tab=gametables-campaigns"
                        class="mb-2 inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-primary underline-offset-2 hover:underline"
                    >
                        <span aria-hidden="true">←</span>
                        {{ t('gameTables.backToProfile') }}
                    </Link>
                    <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                        {{ t('campaigns.myCampaigns.title') }}
                    </h1>
                    <p class="mt-2 text-lg text-base-secondary">
                        {{ t('campaigns.myCampaigns.subtitle') }}
                    </p>
                </div>

                <BaseButton
                    v-if="props.canCreate"
                    variant="primary"
                    class="shrink-0 self-start sm:self-auto"
                    @click="handleCreate"
                >
                    {{ t('campaigns.myCampaigns.createCampaign') }}
                </BaseButton>
            </div>
        </div>
    </div>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div
            v-if="flashSuccess"
            role="status"
            class="mb-6 rounded-lg border-l-4 border-success bg-success/10 p-4 text-sm text-base-primary"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            role="alert"
            class="mb-6 rounded-lg border-l-4 border-error bg-error/10 p-4 text-sm text-base-primary"
        >
            {{ flashError }}
        </div>

        <EmptyState
            v-if="!hasCampaigns"
            icon="book"
            :title="t('campaigns.myCampaigns.noCampaigns')"
            :description="t('campaigns.myCampaigns.noCampaignsDescription')"
        >
            <BaseButton
                v-if="props.canCreate"
                variant="primary"
                @click="handleCreate"
            >
                {{ t('campaigns.myCampaigns.createCampaign') }}
            </BaseButton>
        </EmptyState>

        <div v-else class="space-y-4">
            <div
                v-for="campaign in props.campaigns"
                :key="campaign.id"
                class="overflow-hidden rounded-lg bg-surface shadow transition-shadow hover:shadow-md"
            >
                <div class="p-4 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <h2 class="min-w-0 truncate text-xl font-semibold text-base-primary">
                                    <Link
                                        v-if="isApproved(campaign)"
                                        :href="getCampaignLink(campaign)"
                                        class="hover:text-primary"
                                    >
                                        {{ campaign.title }}
                                    </Link>
                                    <span v-else>{{ campaign.title }}</span>
                                </h2>
                                <StatusBadge
                                    :status="campaign.frontend_creation_status ?? campaign.status"
                                    :label="getStatusLabel(campaign)"
                                    :color="getStatusColor(campaign)"
                                />
                            </div>

                            <p class="text-sm font-medium text-primary">
                                {{ campaign.game_system_name }}
                            </p>

                            <!-- Campaigns the viewer directs: roster summary -->
                            <template v-if="campaign.directed_by_viewer">
                                <p class="mt-3 text-sm text-base-secondary">
                                    {{ directedSummary(campaign) }}
                                </p>
                                <span
                                    v-if="campaign.pending_requests > 0"
                                    class="mt-2 inline-flex items-center rounded-full bg-warning-light px-2 py-0.5 text-xs font-medium text-base-primary"
                                >
                                    {{ t('campaigns.myCampaigns.pendingRequests', { count: campaign.pending_requests }, campaign.pending_requests) }}
                                </span>
                            </template>

                            <div v-else class="mt-3 flex flex-wrap items-center gap-4 text-sm text-base-secondary">
                                <!-- Frequency -->
                                <div v-if="campaign.frequency_label" class="flex items-center gap-1.5">
                                    <Clock class="h-4 w-4" aria-hidden="true" />
                                    <span>{{ campaign.frequency_label }}</span>
                                </div>

                                <!-- Players -->
                                <div class="flex items-center gap-1.5">
                                    <Users class="h-4 w-4" aria-hidden="true" />
                                    <span>
                                        <template v-if="campaign.max_players">
                                            {{ campaign.current_players }}/{{ campaign.max_players }}
                                        </template>
                                        <template v-else>
                                            {{ campaign.current_players }}
                                        </template>
                                        {{ t('campaigns.myCampaigns.players') }}
                                    </span>
                                </div>

                                <!-- Recruiting badge -->
                                <span
                                    v-if="campaign.is_recruiting"
                                    class="inline-flex items-center gap-1 rounded-full bg-success-light px-2 py-0.5 text-xs font-medium text-base-primary"
                                >
                                    <CircleArrowRight class="h-3 w-3" aria-hidden="true" />
                                    {{ t('campaigns.myCampaigns.recruiting') }}
                                </span>
                            </div>

                            <!-- Rejected in review: back to a draft until it is sent again -->
                            <DraftRejectionReason
                                v-if="campaign.rejection_reason"
                                kind="campaign"
                                :reason="campaign.rejection_reason"
                                class="mt-3"
                            />
                        </div>

                        <div class="flex flex-shrink-0 flex-wrap gap-2">
                            <template v-if="campaign.directed_by_viewer">
                                <Link
                                    :href="rosterLink(campaign)"
                                    :class="primaryLinkClass"
                                >
                                    {{ t('campaigns.myCampaigns.roster') }}
                                </Link>
                                <Link
                                    v-if="campaign.can_add_session"
                                    :href="addSessionLink(campaign)"
                                    :class="secondaryLinkClass"
                                >
                                    {{ t('campaigns.myCampaigns.addSession') }}
                                </Link>
                            </template>

                            <template v-if="isPendingReview(campaign)">
                                <span class="text-sm text-base-muted">
                                    {{ t('campaigns.myCampaigns.pendingReviewMessage') }}
                                </span>
                            </template>

                            <template v-else-if="isApproved(campaign)">
                                <Link
                                    :href="getCampaignLink(campaign)"
                                    :class="campaign.directed_by_viewer ? secondaryLinkClass : primaryLinkClass"
                                >
                                    {{ t('campaigns.myCampaigns.viewPublic') }}
                                </Link>
                            </template>

                            <!-- While the author may still change it: can_edit, the rule the server enforces -->
                            <DraftActions
                                v-else-if="campaign.can_edit"
                                kind="campaign"
                                :route-key="campaign.id"
                                :title="campaign.title"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
