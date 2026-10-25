<script setup lang="ts">
import { BookOpen, ChevronDown, Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { ref, computed, useId } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useVerificationGate } from '@/composables/useVerificationGate';
import VerificationBlockedButton from '../VerificationBlockedButton.vue';
import ProfileCreatedCampaignCard from './ProfileCreatedCampaignCard.vue';
import type { ProfileCreatedCampaignsData, ProfileCreatedCampaign } from '../../types/gametables';

interface Props {
    profileCreatedCampaigns: ProfileCreatedCampaignsData | null;
}

const props = defineProps<Props>();
const { t } = useI18n();
// The creation form's routes are closed to accounts that must verify their email
const gate = useVerificationGate();
// One reason for every draft's disabled actions (aria-describedby), not one per card
const actionsReasonId = useId();

const showDraftsSection = ref(true);

const publishedCampaigns = computed<ProfileCreatedCampaign[]>(() => props.profileCreatedCampaigns?.campaigns ?? []);
const draftCampaigns = computed<ProfileCreatedCampaign[]>(() => props.profileCreatedCampaigns?.drafts ?? []);
const totalCampaigns = computed(() => props.profileCreatedCampaigns?.total ?? 0);
const canCreate = computed(() => props.profileCreatedCampaigns?.canCreate ?? false);

const createUrl = '/campanas/crear';
// «Mis campañas»: every campaign the user created, with its actions
const manageUrl = '/campanas/mis-campanas';

// The look of the manage link; its disabled stand-in keeps it
const manageLinkClass = 'inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-primary underline-offset-2 hover:underline';

function toggleDraftsSection(): void {
    showDraftsSection.value = !showDraftsSection.value;
}
</script>

<template>
    <div v-if="profileCreatedCampaigns && totalCampaigns > 0" class="space-y-10">
        <!-- Manage link and top create button -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <VerificationBlockedButton
                v-if="gate.blocked.value"
                :reason="gate.reason('form')"
                :button-class="manageLinkClass"
            >
                {{ t('campaigns.profile.manage') }}
            </VerificationBlockedButton>
            <Link
                v-else
                :href="manageUrl"
                :class="manageLinkClass"
            >
                {{ t('campaigns.profile.manage') }}
                <span aria-hidden="true">→</span>
            </Link>

            <VerificationBlockedButton
                v-if="canCreate && gate.blocked.value"
                :reason="gate.reason('createCampaign')"
                align="end"
                button-class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
                {{ t('campaigns.profile.createNew') }}
            </VerificationBlockedButton>
            <Link
                v-else-if="canCreate"
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
                {{ t('campaigns.profile.createNew') }}
            </Link>
        </div>

        <!-- Published campaigns section -->
        <section>
            <h2 class="mb-6 flex items-center gap-2 text-lg font-semibold text-base-primary">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-success-light text-xs font-bold text-base-primary">
                    {{ publishedCampaigns.length }}
                </span>
                {{ t('campaigns.profile.published') }}
            </h2>

            <div v-if="publishedCampaigns.length > 0" class="space-y-3">
                <ProfileCreatedCampaignCard
                    v-for="campaign in publishedCampaigns"
                    :key="campaign.id"
                    :campaign="campaign"
                />
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed border-default bg-muted p-6 text-center text-sm text-base-secondary"
            >
                {{ t('campaigns.profile.noPublished') }}
            </p>
        </section>

        <!-- Drafts section (collapsible) -->
        <section v-if="draftCampaigns.length > 0" class="border-t border-default pt-8">
            <button
                type="button"
                class="mb-4 flex w-full items-center justify-between rounded-lg bg-base-secondary/10 px-4 py-3 text-left transition-colors hover:bg-base-secondary/20"
                :aria-expanded="showDraftsSection"
                @click="toggleDraftsSection"
            >
                <span class="flex items-center gap-2 text-lg font-semibold text-base-primary">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-warning-light text-xs font-bold text-base-primary">
                        {{ draftCampaigns.length }}
                    </span>
                    {{ t('campaigns.profile.drafts') }}
                </span>
                <ChevronDown
                    :class="['h-5 w-5 text-base-muted transition-transform', showDraftsSection ? 'rotate-180' : '']"
                    aria-hidden="true"
                />
            </button>

            <div v-if="showDraftsSection" class="space-y-3">
                <p
                    v-if="gate.blocked.value"
                    :id="actionsReasonId"
                    class="rounded-md bg-warning-bg px-3 py-2 text-sm text-base-primary"
                >
                    {{ t('gameTables.verificationGate.edit') }}
                    <Link
                        :href="gate.noticeUrl"
                        class="font-semibold underline underline-offset-2 hover:no-underline"
                    >
                        {{ gate.linkLabel.value }}
                        <span aria-hidden="true">→</span>
                    </Link>
                </p>
                <ProfileCreatedCampaignCard
                    v-for="campaign in draftCampaigns"
                    :key="campaign.id"
                    :campaign="campaign"
                    :show-actions="true"
                    :actions-reason-id="actionsReasonId"
                />
            </div>
        </section>

        <!-- Create new campaign link (secondary) -->
        <!-- Only once on the page while blocked: the top one says why -->
        <div v-if="canCreate && !gate.blocked.value" class="flex justify-center border-t border-default pt-8">
            <Link
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg border-2 border-primary px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary-light"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
                {{ t('campaigns.profile.createNew') }}
            </Link>
        </div>
    </div>

    <!-- Empty state when no campaigns created -->
    <div
        v-else
        class="rounded-lg border border-dashed border-default bg-muted p-12 text-center"
    >
        <BookOpen class="mx-auto h-12 w-12 text-base-secondary" aria-hidden="true" />
        <h3 class="mt-4 text-lg font-medium text-base-primary">
            {{ t('campaigns.profile.noCampaigns') }}
        </h3>
        <p class="mt-1 text-sm text-base-muted">
            {{ t('campaigns.profile.noCampaignsDescription') }}
        </p>
        <div v-if="canCreate" class="mt-6">
            <VerificationBlockedButton
                v-if="gate.blocked.value"
                :reason="gate.reason('createCampaign')"
                align="center"
                button-class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
                {{ t('campaigns.profile.createFirst') }}
            </VerificationBlockedButton>
            <Link
                v-else
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
                {{ t('campaigns.profile.createFirst') }}
            </Link>
            <!-- While blocked the create button above already says why -->
            <p v-if="!gate.blocked.value" class="mt-4">
                <Link
                    :href="manageUrl"
                    :class="manageLinkClass"
                >
                    {{ t('campaigns.profile.manage') }}
                    <span aria-hidden="true">→</span>
                </Link>
            </p>
        </div>
    </div>
</template>
