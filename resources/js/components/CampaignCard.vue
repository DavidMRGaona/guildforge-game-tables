<script setup lang="ts">
import { Calendar, User } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { CampaignListItem } from '../types/gametables';
import CapacityBar from './CapacityBar.vue';
import StatusBadge from './StatusBadge.vue';
import { buildCardImageUrl } from '@/utils/cloudinary';

interface Props {
    campaign: CampaignListItem;
}

const props = defineProps<Props>();

const { t } = useI18n();

// CampaignStatus::Recruiting; its badge already says the campaign wants players
const RECRUITING_STATUS = 'recruiting';

const imageUrl = computed(() => buildCardImageUrl(props.campaign.imagePublicId));

const showLookingForPlayers = computed(
    () => props.campaign.acceptsNewPlayers && props.campaign.status !== RECRUITING_STATUS
);

const sessionLabel = computed(() => {
    const { sessionCount, currentSession } = props.campaign;
    if (sessionCount !== null && sessionCount > 0) {
        return t('campaigns.details.totalSessions', {
            current: currentSession,
            total: sessionCount,
        });
    }
    return t('campaigns.sessionNumber', { number: currentSession });
});

const scheduleLine = computed(() =>
    [props.campaign.frequencyLabel, sessionLabel.value]
        .filter((part): part is string => Boolean(part))
        .join(' · ')
);
</script>

<template>
    <Link
        :href="`/campanas/${campaign.id}`"
        :aria-label="t('aria.viewCampaign', { title: campaign.title })"
        class="group flex h-full flex-col overflow-hidden rounded-lg bg-surface shadow-sm transition-all duration-200 hover:scale-[1.02] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
    >
        <!-- Image with overlay badges (if image exists) -->
        <div v-if="imageUrl" class="relative">
            <img :src="imageUrl" :alt="campaign.title" class="h-36 w-full object-cover" />
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/30"
            />

            <div class="absolute right-3 top-3 flex items-center gap-2">
                <span
                    v-if="showLookingForPlayers"
                    class="rounded-full bg-success-light px-2 py-0.5 text-xs font-semibold text-base-primary"
                >
                    {{ t('campaigns.lookingForPlayers') }}
                </span>
                <StatusBadge
                    :status="campaign.status"
                    :label="campaign.statusLabel"
                    :color="campaign.statusColor"
                />
            </div>
        </div>

        <!-- Neutral header band (only if no image) -->
        <div v-else class="flex min-h-16 items-center justify-end bg-muted px-4 py-3">
            <div class="flex items-center gap-2">
                <span
                    v-if="showLookingForPlayers"
                    class="rounded-full bg-success-light px-2 py-0.5 text-xs font-semibold text-base-primary"
                >
                    {{ t('campaigns.lookingForPlayers') }}
                </span>
                <StatusBadge
                    :status="campaign.status"
                    :label="campaign.statusLabel"
                    :color="campaign.statusColor"
                />
            </div>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <!-- Zone 1: Identity -->
            <div class="mb-3">
                <h3
                    class="line-clamp-2 text-lg font-semibold text-base-primary transition-colors group-hover:text-primary"
                >
                    {{ campaign.title }}
                </h3>
                <p class="mt-0.5 text-sm font-medium text-primary">
                    {{ campaign.gameSystemName }}
                </p>
            </div>

            <!-- Zone 2: Key details -->
            <div class="mb-3 rounded-lg bg-muted p-3">
                <div
                    class="flex items-center text-sm text-base-secondary"
                    :class="{ 'mb-2': campaign.maxPlayers !== null }"
                >
                    <Calendar
                        class="mr-1.5 h-4 w-4 flex-shrink-0 text-base-muted"
                        aria-hidden="true"
                    />
                    <span>{{ scheduleLine }}</span>
                </div>

                <div v-if="campaign.maxPlayers !== null">
                    <div class="mb-1 flex items-center justify-between text-xs text-base-secondary">
                        <span>{{ t('campaigns.players') }}</span>
                        <span class="font-medium"
                            >{{ campaign.currentPlayers }} / {{ campaign.maxPlayers }}</span
                        >
                    </div>
                    <CapacityBar
                        :current="campaign.currentPlayers"
                        :max="campaign.maxPlayers"
                        :label="t('campaigns.players')"
                        size="md"
                    />
                </div>
            </div>

            <!-- Zone 3: Meta (pinned to the bottom so footers line up across a grid row) -->
            <div class="mt-auto flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-base-secondary">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1"
                    >
                        <User class="h-3.5 w-3.5" aria-hidden="true" />
                        <span class="max-w-[160px] truncate text-xs font-medium">{{
                            campaign.mainGameMasterName
                        }}</span>
                    </span>
                </div>
            </div>
        </div>
    </Link>
</template>
