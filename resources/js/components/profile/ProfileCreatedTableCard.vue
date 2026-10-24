<script setup lang="ts">
import { Calendar, Users } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DraftActions from '../DraftActions.vue';
import DraftRejectionReason from '../DraftRejectionReason.vue';
import type { ProfileCreatedTable } from '../../types/gametables';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    table: ProfileCreatedTable;
    /** A draft's actions and the moderator's reason (the drafts list) */
    showActions?: boolean;
    /** Id of the section's line saying why the actions are disabled (shown once per drafts list) */
    actionsReasonId?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    showActions: false,
    actionsReasonId: null,
});

const { t, locale } = useI18n();

const formattedDate = computed(() => {
    if (!props.table.startsAt) return null;
    const date = new Date(props.table.startsAt);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

const tableUrl = computed(() =>
    props.table.slug ? `/mesas/${props.table.slug}` : null
);

const statusColorClasses = computed(() => {
    const colorMap: Record<string, string> = {
        gray: 'bg-muted text-base-secondary',
        success: 'bg-success-light text-base-primary',
        warning: 'bg-warning-light text-base-primary',
        primary: 'bg-primary-light text-primary',
        danger: 'bg-error-light text-base-primary',
    };
    return colorMap[props.table.statusColor] ?? colorMap.gray;
});

const playersText = computed(() => {
    return `${props.table.currentPlayers}/${props.table.maxPlayers}`;
});
</script>

<template>
    <div class="rounded-lg border border-default bg-surface p-4 transition-shadow hover:shadow-md">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <!-- Title and game system -->
                <div class="flex items-center gap-2">
                    <Link
                        v-if="tableUrl && table.isPublished"
                        :href="tableUrl"
                        class="truncate text-base font-medium text-base-primary hover:underline"
                    >
                        {{ table.title }}
                    </Link>
                    <span
                        v-else
                        class="truncate text-base font-medium text-base-primary"
                    >
                        {{ table.title }}
                    </span>
                    <span class="shrink-0 text-xs text-base-muted">
                        {{ table.gameSystemName }}
                    </span>
                </div>

                <!-- Meta info -->
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-base-muted">
                    <!-- Date -->
                    <span v-if="formattedDate" class="flex items-center gap-1">
                        <Calendar class="h-4 w-4" aria-hidden="true" />
                        {{ formattedDate }}
                    </span>

                    <!-- Players -->
                    <span class="flex items-center gap-1">
                        <Users class="h-4 w-4" aria-hidden="true" />
                        {{ playersText }} {{ t('gameTables.profile.created.players') }}
                    </span>

                    <!-- Format -->
                    <span class="text-xs">
                        {{ table.tableFormatLabel }}
                    </span>

                    <!-- Event -->
                    <span v-if="table.eventTitle" class="truncate text-xs">
                        {{ table.eventTitle }}
                    </span>
                </div>
            </div>

            <!-- Status badge -->
            <span
                :class="['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium', statusColorClasses]"
            >
                {{ table.statusLabel }}
            </span>
        </div>

        <!-- Rejected in review: the reason, addressed to the author, while it is back in their hands -->
        <DraftRejectionReason
            v-if="showActions && table.canEdit && table.rejectionReason"
            kind="table"
            :reason="table.rejectionReason"
            class="mt-3"
        />

        <!-- The same actions as «Mis mesas», while the author may still change it (not once sent to review) -->
        <DraftActions
            v-if="showActions && table.canEdit && table.slug"
            kind="table"
            :route-key="table.slug"
            :title="table.title"
            return-to="profile"
            :disabled-reason-id="actionsReasonId"
            class="mt-3"
        />
    </div>
</template>
