<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import ProfileCreatedCampaignCard from './ProfileCreatedCampaignCard.vue';
import type { ProfileCreatedCampaignsData, ProfileCreatedCampaign } from '../../types/gametables';

interface Props {
    profileCreatedCampaigns: ProfileCreatedCampaignsData | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const showDraftsSection = ref(true);

const publishedCampaigns = computed<ProfileCreatedCampaign[]>(() => props.profileCreatedCampaigns?.campaigns ?? []);
const draftCampaigns = computed<ProfileCreatedCampaign[]>(() => props.profileCreatedCampaigns?.drafts ?? []);
const totalCampaigns = computed(() => props.profileCreatedCampaigns?.total ?? 0);
const canCreate = computed(() => props.profileCreatedCampaigns?.canCreate ?? false);

const createUrl = '/campanas/crear';

function toggleDraftsSection(): void {
    showDraftsSection.value = !showDraftsSection.value;
}
</script>

<template>
    <div v-if="profileCreatedCampaigns && totalCampaigns > 0" class="space-y-10">
        <!-- Top create button -->
        <div v-if="canCreate" class="flex justify-end">
            <Link
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('campaigns.profile.createNew') }}
            </Link>
        </div>

        <!-- Published campaigns section -->
        <section>
            <h2 class="mb-6 flex items-center gap-2 text-lg font-semibold text-base-primary">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-success-light text-xs font-bold text-green-700 dark:text-green-400">
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
                class="rounded-lg border border-dashed border-stone-300 bg-muted p-6 text-center text-sm text-base-muted dark:border-stone-600"
            >
                {{ t('campaigns.profile.noPublished') }}
            </p>
        </section>

        <!-- Drafts section (collapsible) -->
        <section v-if="draftCampaigns.length > 0" class="border-t border-default pt-8">
            <button
                type="button"
                class="mb-4 flex w-full items-center justify-between rounded-lg bg-muted px-4 py-3 text-left transition-colors hover:bg-stone-200 dark:hover:bg-stone-700"
                :aria-expanded="showDraftsSection"
                @click="toggleDraftsSection"
            >
                <span class="flex items-center gap-2 text-lg font-semibold text-base-primary">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-yellow-100 text-xs font-bold text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                        {{ draftCampaigns.length }}
                    </span>
                    {{ t('campaigns.profile.drafts') }}
                </span>
                <svg
                    :class="['h-5 w-5 text-base-muted transition-transform', showDraftsSection ? 'rotate-180' : '']"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-if="showDraftsSection" class="space-y-3">
                <ProfileCreatedCampaignCard
                    v-for="campaign in draftCampaigns"
                    :key="campaign.id"
                    :campaign="campaign"
                    :show-edit-link="true"
                />
            </div>
        </section>

        <!-- Create new campaign link (secondary) -->
        <div v-if="canCreate" class="flex justify-center border-t border-default pt-8">
            <Link
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg border-2 border-primary px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary-light dark:hover:bg-primary-950/20"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('campaigns.profile.createNew') }}
            </Link>
        </div>
    </div>

    <!-- Empty state when no campaigns created -->
    <div
        v-else
        class="rounded-lg border border-dashed border-stone-300 bg-muted p-12 text-center dark:border-stone-600"
    >
        <svg
            class="mx-auto h-12 w-12 text-stone-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
            />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-base-primary">
            {{ t('campaigns.profile.noCampaigns') }}
        </h3>
        <p class="mt-1 text-sm text-base-muted">
            {{ t('campaigns.profile.noCampaignsDescription') }}
        </p>
        <div v-if="canCreate" class="mt-6">
            <Link
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('campaigns.profile.createFirst') }}
            </Link>
        </div>
    </div>
</template>
