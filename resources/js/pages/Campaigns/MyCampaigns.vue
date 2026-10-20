<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import StatusBadge from '../../components/StatusBadge.vue';
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

// Confirmation dialog state
const showSubmitDialog = ref(false);
const showDeleteDialog = ref(false);
const pendingCampaignId = ref<string | null>(null);
const pendingCampaignTitle = ref<string>('');

function canEdit(campaign: CampaignResponseDTO): boolean {
    return campaign.can_edit && !campaign.is_published;
}

// 'rejected' only on rows rejected before 2.10.0: rejecting now returns the campaign to a draft
function canSubmitForReview(campaign: CampaignResponseDTO): boolean {
    const status = campaign.frontend_creation_status;
    return !campaign.is_published && (status === 'draft' || status === 'rejected');
}

function canDelete(campaign: CampaignResponseDTO): boolean {
    const status = campaign.frontend_creation_status;
    return !campaign.is_published && (status === 'draft' || status === 'rejected');
}

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

function handleEdit(campaignId: string): void {
    router.visit(`/campanas/mis-campanas/${campaignId}/editar`);
}

function handleSubmitForReview(campaignId: string): void {
    pendingCampaignId.value = campaignId;
    showSubmitDialog.value = true;
}

function confirmSubmitForReview(): void {
    if (pendingCampaignId.value) {
        router.post(`/campanas/mis-campanas/${pendingCampaignId.value}/enviar-revision`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                pendingCampaignId.value = null;
            },
        });
    } else {
        pendingCampaignId.value = null;
    }
}

function handleDelete(campaignId: string, campaignTitle: string): void {
    pendingCampaignId.value = campaignId;
    pendingCampaignTitle.value = campaignTitle;
    showDeleteDialog.value = true;
}

function confirmDelete(): void {
    if (pendingCampaignId.value) {
        router.delete(`/campanas/mis-campanas/${pendingCampaignId.value}`);
    }
    pendingCampaignId.value = null;
    pendingCampaignTitle.value = '';
}

function handleCreate(): void {
    router.visit('/campanas/crear');
}

// Links styled as BaseButton (primary / secondary, size sm)
const primaryLinkClass = 'inline-flex min-h-[44px] items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page';
const secondaryLinkClass = 'inline-flex min-h-[44px] items-center justify-center rounded-md bg-muted px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-neutral-200 focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2 dark:hover:bg-neutral-700 dark:focus:ring-offset-page';

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
    <DefaultLayout>
        <div class="bg-surface shadow dark:shadow-neutral-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
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
                    class="overflow-hidden rounded-lg bg-surface shadow transition-shadow hover:shadow-md dark:shadow-neutral-900/50"
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
                                        class="mt-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                                    >
                                        {{ t('campaigns.myCampaigns.pendingRequests', { count: campaign.pending_requests }, campaign.pending_requests) }}
                                    </span>
                                </template>

                                <div v-else class="mt-3 flex flex-wrap items-center gap-4 text-sm text-base-secondary">
                                    <!-- Frequency -->
                                    <div v-if="campaign.frequency_label" class="flex items-center gap-1.5">
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                        <span>{{ campaign.frequency_label }}</span>
                                    </div>

                                    <!-- Players -->
                                    <div class="flex items-center gap-1.5">
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                            />
                                        </svg>
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
                                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                                        </svg>
                                        {{ t('campaigns.myCampaigns.recruiting') }}
                                    </span>
                                </div>

                                <!-- Rejected in review: back to a draft until it is sent again -->
                                <div v-if="campaign.rejection_reason" class="mt-3 rounded-md bg-danger-bg p-3">
                                    <p class="text-sm text-danger">
                                        <strong>{{ t('campaigns.myCampaigns.rejectionReason') }}:</strong>
                                        {{ campaign.rejection_reason }}
                                    </p>
                                    <p class="mt-1 text-sm text-danger">
                                        {{ t('campaigns.myCampaigns.rejectionHint') }}
                                    </p>
                                </div>
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

                                <template v-else>
                                    <BaseButton
                                        v-if="canEdit(campaign)"
                                        variant="secondary"
                                        size="sm"
                                        @click="handleEdit(campaign.id)"
                                    >
                                        {{ t('common.edit') }}
                                    </BaseButton>

                                    <BaseButton
                                        v-if="canSubmitForReview(campaign)"
                                        variant="primary"
                                        size="sm"
                                        @click="handleSubmitForReview(campaign.id)"
                                    >
                                        {{ t('campaigns.myCampaigns.submitForReview') }}
                                    </BaseButton>

                                    <BaseButton
                                        v-if="canDelete(campaign)"
                                        variant="danger"
                                        size="sm"
                                        @click="handleDelete(campaign.id, campaign.title)"
                                    >
                                        {{ t('common.delete') }}
                                    </BaseButton>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Submit for review confirmation dialog -->
        <ConfirmDialog
            v-model="showSubmitDialog"
            :title="t('campaigns.myCampaigns.submitForReviewTitle')"
            :message="t('campaigns.myCampaigns.confirmSubmit')"
            :confirm-label="t('campaigns.myCampaigns.submitForReview')"
            :cancel-label="t('common.cancel')"
            confirm-variant="primary"
            @confirm="confirmSubmitForReview"
        />

        <!-- Delete confirmation dialog -->
        <ConfirmDialog
            v-model="showDeleteDialog"
            :title="t('campaigns.myCampaigns.deleteTitle')"
            :message="t('campaigns.myCampaigns.confirmDelete', { title: pendingCampaignTitle })"
            :confirm-label="t('common.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmDelete"
        />
    </DefaultLayout>
</template>
