<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * «Campañas» (`game-tables.campaigns`) on a public profile: the campaigns going on that the owner
 * runs and, when the viewer may see the roster, those they play in. The card and its title are the
 * core's.
 */
interface ProfileCampaignItem {
    id: string;
    title: string;
    url: string;
    gameSystem: string;
    status: string;
    role: 'director' | 'player';
}

defineProps<{
    campaigns: ProfileCampaignItem[];
}>();

const { t } = useI18n();
</script>

<template>
    <ul class="divide-y divide-[var(--color-border)]" role="list">
        <li v-for="campaign in campaigns" :key="campaign.id" class="py-2 first:pt-0 last:pb-0">
            <Link
                :href="campaign.url"
                class="group block rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            >
                <span class="flex items-start justify-between gap-2">
                    <span
                        class="min-w-0 break-words font-medium text-base-primary group-hover:text-primary"
                    >
                        {{ campaign.title }}
                    </span>
                    <span
                        class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-base-secondary"
                    >
                        {{ t(`gameTables.publicProfile.roles.${campaign.role}`) }}
                    </span>
                </span>
                <span class="mt-0.5 block text-sm text-base-muted">
                    <span v-if="campaign.gameSystem" class="text-primary">{{
                        campaign.gameSystem
                    }}</span>
                    <span v-if="campaign.gameSystem" aria-hidden="true"> · </span>
                    {{ campaign.status }}
                </span>
            </Link>
        </li>
    </ul>
</template>
