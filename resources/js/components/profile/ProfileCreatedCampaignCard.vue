<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { ProfileCreatedCampaign } from '../../types/gametables';

interface Props {
    campaign: ProfileCreatedCampaign;
    showEditLink?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEditLink: false,
});

const { t } = useI18n();

const campaignUrl = computed(() =>
    props.campaign.slug ? `/campanas/${props.campaign.slug}` : null
);

const editUrl = computed(() => `/campanas/mis-campanas/${props.campaign.id}/editar`);

const statusColorClasses = computed(() => {
    const colorMap: Record<string, string> = {
        gray: 'bg-muted text-base-secondary',
        success: 'bg-success-light text-green-700 dark:text-green-400',
        warning: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        primary: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        danger: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
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
                        class="truncate text-base font-medium text-base-primary hover:text-primary-700"
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
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
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
                <Link
                    v-if="showEditLink && !campaign.isPublished"
                    :href="editUrl"
                    class="text-sm text-primary hover:text-primary-700"
                >
                    {{ t('campaigns.profile.edit') }}
                </Link>
            </div>
        </div>
    </div>
</template>
