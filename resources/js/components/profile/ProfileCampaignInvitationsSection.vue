<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { ProfileCampaignInvitation, ProfileCampaignInvitationsData } from '../../types/gametables';

interface Props {
    profileCampaignInvitations: ProfileCampaignInvitationsData | null;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const invitations = computed<ProfileCampaignInvitation[]>(() => props.profileCampaignInvitations?.invitations ?? []);

// The invitation whose action is running, and the one the reject dialog asks about
const busyId = ref<string | null>(null);
const rejectingId = ref<string | null>(null);
const showRejectDialog = computed({
    get: () => rejectingId.value !== null,
    set: (open: boolean) => {
        if (!open) {
            rejectingId.value = null;
        }
    },
});

const baseUrl = '/campanas/invitaciones/perfil';

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function send(id: string, action: 'aceptar' | 'rechazar'): void {
    router.post(`${baseUrl}/${id}/${action}`, {}, {
        preserveScroll: true,
        onStart: () => {
            busyId.value = id;
        },
        onFinish: () => {
            busyId.value = null;
            rejectingId.value = null;
        },
    });
}

function confirmReject(): void {
    if (rejectingId.value !== null) {
        send(rejectingId.value, 'rechazar');
    }
}
</script>

<template>
    <div v-if="invitations.length > 0" class="space-y-4">
        <h2 class="text-lg font-semibold text-base-primary">
            {{ t('gameTables.profile.invitations.title') }}
        </h2>

        <ul class="space-y-3">
            <li
                v-for="invitation in invitations"
                :key="invitation.id"
                class="flex flex-col gap-4 rounded-lg border border-default bg-surface p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <Link
                        :href="`/campanas/${invitation.campaign_slug}`"
                        class="block truncate text-base font-semibold text-base-primary hover:text-primary hover:underline"
                    >
                        {{ invitation.campaign_title }}
                    </Link>
                    <p class="mt-1 text-sm text-base-secondary">
                        {{ t('gameTables.profile.invitations.invitedBy', { inviter: invitation.inviter_name }) }}
                    </p>
                    <p class="text-sm text-base-muted">
                        {{ t('gameTables.profile.invitations.expires', { date: formatDate(invitation.expires_at) }) }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                    <BaseButton
                        variant="primary"
                        size="sm"
                        class="w-full sm:w-auto"
                        :loading="busyId === invitation.id"
                        :disabled="busyId !== null"
                        @click="send(invitation.id, 'aceptar')"
                    >
                        {{ t('gameTables.profile.invitations.accept') }}
                    </BaseButton>
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        class="w-full sm:w-auto"
                        :disabled="busyId !== null"
                        @click="rejectingId = invitation.id"
                    >
                        {{ t('gameTables.profile.invitations.reject') }}
                    </BaseButton>
                </div>
            </li>
        </ul>

        <ConfirmDialog
            v-model="showRejectDialog"
            :title="t('gameTables.profile.invitations.rejectTitle')"
            :message="t('gameTables.profile.invitations.rejectMessage')"
            :confirm-label="t('gameTables.profile.invitations.reject')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmReject"
        />
    </div>
</template>
