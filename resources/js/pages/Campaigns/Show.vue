<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { Campaign } from '../../types/gametables';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import SafetyToolBadge from '../../components/SafetyToolBadge.vue';
import ContentWarningBadge from '../../components/ContentWarningBadge.vue';
import { useSeo } from '@/composables/useSeo';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';

interface Props {
    campaign: Campaign;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

useSeo({
    title: props.campaign.title,
    description: props.campaign.description || t('campaigns.description'),
    type: 'article',
});

const formattedStartDate = computed(() => {
    if (!props.campaign.startDate) return null;
    const date = new Date(props.campaign.startDate);
    return date.toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const formattedEndDate = computed(() => {
    if (!props.campaign.endDate) return null;
    const date = new Date(props.campaign.endDate);
    return date.toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const spotsAvailable = computed(() => {
    return props.campaign.maxPlayers - props.campaign.currentPlayers;
});
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link
                    href="/campanas"
                    class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:text-stone-400 dark:hover:text-stone-300 dark:focus:ring-offset-stone-900"
                >
                    <svg
                        class="mr-1 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    {{ t('common.back') }}
                </Link>
            </div>

            <article
                class="overflow-hidden rounded-lg bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50"
            >
                <div class="p-6 sm:p-8">
                    <!-- Header with badges -->
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            :status="campaign.status"
                            :label="campaign.statusLabel"
                            :color="campaign.statusColor"
                            size="md"
                        />
                        <span
                            v-if="campaign.isRecruiting"
                            class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
                        >
                            {{ t('campaigns.recruiting') }}
                        </span>
                    </div>

                    <h1
                        class="mb-4 text-3xl font-bold text-gray-900 sm:text-4xl dark:text-stone-100"
                    >
                        {{ campaign.title }}
                    </h1>

                    <!-- Game System -->
                    <p class="mb-6 text-xl font-medium text-amber-600 dark:text-amber-500">
                        {{ campaign.gameSystemName }}
                    </p>

                    <!-- Description -->
                    <div v-if="campaign.description" class="mb-6">
                        <p class="whitespace-pre-line text-stone-700 dark:text-stone-300">
                            {{ campaign.description }}
                        </p>
                    </div>

                    <!-- Key Information Grid -->
                    <div
                        class="mb-6 grid grid-cols-1 gap-4 rounded-lg bg-stone-50 p-4 sm:grid-cols-2 dark:bg-stone-900/30"
                    >
                        <!-- Frequency -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.frequency') }}
                            </h3>
                            <div class="flex items-center text-stone-700 dark:text-stone-300">
                                <svg
                                    class="mr-2 h-5 w-5 text-amber-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                <span>{{ campaign.frequencyLabel }}</span>
                            </div>
                        </div>

                        <!-- Duration -->
                        <div v-if="campaign.expectedDurationMonths">
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.expectedDuration') }}
                            </h3>
                            <div class="flex items-center text-stone-700 dark:text-stone-300">
                                <svg
                                    class="mr-2 h-5 w-5 text-amber-600"
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
                                <span>
                                    {{ t('campaigns.monthsCount', { count: campaign.expectedDurationMonths }) }}
                                </span>
                            </div>
                        </div>

                        <!-- Start Date -->
                        <div v-if="formattedStartDate">
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.startDate') }}
                            </h3>
                            <p class="text-stone-700 dark:text-stone-300">{{ formattedStartDate }}</p>
                        </div>

                        <!-- End Date -->
                        <div v-if="formattedEndDate">
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.endDate') }}
                            </h3>
                            <p class="text-stone-700 dark:text-stone-300">{{ formattedEndDate }}</p>
                        </div>

                        <!-- Game Master -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.gameMaster') }}
                            </h3>
                            <div class="flex items-center text-stone-700 dark:text-stone-300">
                                <svg
                                    class="mr-2 h-5 w-5 text-amber-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                                <span>{{ campaign.creatorName }}</span>
                            </div>
                        </div>

                        <!-- Capacity -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.capacity') }}
                            </h3>
                            <div class="text-stone-700 dark:text-stone-300">
                                <p>
                                    {{ t('campaigns.playersCount', {
                                        current: campaign.currentPlayers,
                                        max: campaign.maxPlayers,
                                    }) }}
                                </p>
                                <p class="text-sm">
                                    {{ spotsAvailable }} {{ t('campaigns.spotsAvailable') }}
                                </p>
                            </div>
                        </div>

                        <!-- Total Sessions -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.totalSessions') }}
                            </h3>
                            <p class="text-stone-700 dark:text-stone-300">
                                {{ campaign.totalSessions }}
                            </p>
                        </div>

                        <!-- Experience Level -->
                        <div>
                            <h3 class="mb-1 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.experienceLevel') }}
                            </h3>
                            <p class="text-stone-700 dark:text-stone-300">
                                {{ campaign.experienceLevelLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- Additional Details -->
                    <div class="mb-6 space-y-4">
                        <!-- Language -->
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.language') }}
                            </h3>
                            <p class="text-stone-700 dark:text-stone-300">{{ campaign.language }}</p>
                        </div>

                        <!-- Settings -->
                        <div v-if="campaign.settings">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.settings') }}
                            </h3>
                            <p class="whitespace-pre-line text-stone-700 dark:text-stone-300">
                                {{ campaign.settings }}
                            </p>
                        </div>

                        <!-- Themes -->
                        <div v-if="campaign.themes.length > 0">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.themes') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(theme, index) in campaign.themes"
                                    :key="index"
                                    class="rounded-full bg-stone-200 px-3 py-1 text-sm text-stone-700 dark:bg-stone-700 dark:text-stone-300"
                                >
                                    {{ theme }}
                                </span>
                            </div>
                        </div>

                        <!-- Safety Tools -->
                        <div v-if="campaign.safetyTools.length > 0">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.safetyTools') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <SafetyToolBadge
                                    v-for="(tool, index) in campaign.safetyTools"
                                    :key="index"
                                    :tool="tool"
                                />
                            </div>
                        </div>

                        <!-- Content Warnings -->
                        <div v-if="campaign.contentWarnings.length > 0">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.contentWarnings') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <ContentWarningBadge
                                    v-for="(warning, index) in campaign.contentWarnings"
                                    :key="index"
                                    :warning="warning"
                                />
                            </div>
                        </div>

                        <!-- Minimum Age -->
                        <div v-if="campaign.minimumAge">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.minimumAge') }}
                            </h3>
                            <p class="text-stone-700 dark:text-stone-300">
                                {{ t('campaigns.ageRequirement', { age: campaign.minimumAge }) }}
                            </p>
                        </div>

                        <!-- Recruitment Message -->
                        <div v-if="campaign.isRecruiting && campaign.recruitmentMessage">
                            <h3 class="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">
                                {{ t('campaigns.recruitmentMessage') }}
                            </h3>
                            <div
                                class="rounded-lg bg-green-50 p-4 text-green-800 dark:bg-green-900/20 dark:text-green-400"
                            >
                                <p class="whitespace-pre-line">{{ campaign.recruitmentMessage }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Module slot for campaign actions -->
                    <div class="mb-6">
                        <ModuleSlot name="campaign-detail-actions" />
                    </div>

                    <div class="border-t border-gray-200 pt-6 dark:border-stone-700">
                        <Link href="/campanas">
                            <BaseButton variant="primary">
                                {{ t('common.viewAll') }} {{ t('campaigns.campaigns').toLowerCase() }}
                            </BaseButton>
                        </Link>
                    </div>
                </div>
            </article>
        </div>
    </DefaultLayout>
</template>
