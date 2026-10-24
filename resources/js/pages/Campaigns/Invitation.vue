<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useFlashMessages } from '@/composables/useFlashMessages';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { CampaignInvitationState, CampaignInvitationView } from '../../types/gametables';

interface Props {
    state: CampaignInvitationState;
    invitation: CampaignInvitationView | null;
    token: string;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();
const { success: flashSuccess, error: flashError } = useFlashMessages();

const isAccepting = ref(false);
const isRejecting = ref(false);
const showRejectDialog = ref(false);

const baseUrl = computed(() => `/campanas/invitaciones/${props.token}`);

// Signs out and lands back on this invitation (the core's logout would go to the home page)
const logout = (): void => {
    router.post(`${baseUrl.value}/cerrar-sesion`);
};

const expiresAt = computed(() => {
    if (!props.invitation?.expires_at) {
        return '';
    }

    return new Date(props.invitation.expires_at).toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

// Links styled as buttons (a button inside a link is invalid interactive nesting)
const linkButtonBase = 'inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-md px-4 py-2 text-base font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-surface';
const primaryLinkClass = `${linkButtonBase} bg-primary text-white hover:bg-primary-700 focus:ring-primary-500`;
const secondaryLinkClass = `${linkButtonBase} bg-base-secondary/10 text-base-primary hover:bg-base-secondary/20 focus:ring-primary-500`;

// Only the buttons act: opening the link (as mail scanners do) changes nothing
function handleAccept(): void {
    router.post(`${baseUrl.value}/aceptar`, {}, {
        onStart: () => {
            isAccepting.value = true;
        },
        onFinish: () => {
            isAccepting.value = false;
        },
    });
}

function handleReject(): void {
    router.post(`${baseUrl.value}/rechazar`, {}, {
        onStart: () => {
            isRejecting.value = true;
        },
        onFinish: () => {
            isRejecting.value = false;
            showRejectDialog.value = false;
        },
    });
}
</script>

<template>
    <div class="mx-auto max-w-2xl px-4 py-12">
        <div class="rounded-xl bg-surface p-8 shadow-lg">
            <!-- The server's answer to the last action -->
            <!-- After rejecting, the rejected state already says it -->
            <div
                v-if="flashSuccess && state !== 'rejected'"
                role="status"
                class="mb-6 rounded-lg bg-success-light p-4 text-center text-sm text-base-primary"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                role="alert"
                class="mb-6 rounded-lg bg-error-light p-4 text-center text-sm text-base-primary"
            >
                {{ flashError }}
            </div>

            <!-- Just rejected here: no "invalid" wording for what the person has just done -->
            <template v-if="state === 'rejected'">
                <h1 class="mb-4 text-center text-2xl font-bold text-base-primary">
                    {{ t('campaigns.invitation.rejectedTitle') }}
                </h1>
                <p class="mb-6 text-center text-base-secondary">
                    {{ t('campaigns.invitation.rejected') }}
                </p>
                <Link
                    href="/campanas"
                    :class="primaryLinkClass"
                >
                    {{ t('campaigns.invitation.viewCampaigns') }}
                </Link>
            </template>

            <!-- No valid invitation: expired, used, cancelled, rejected or unknown, all alike -->
            <template v-else-if="state === 'invalid' || !invitation">
                <h1 class="mb-4 text-center text-2xl font-bold text-base-primary">
                    {{ t('campaigns.invitation.invalidTitle') }}
                </h1>
                <p class="mb-6 text-center text-base-secondary">
                    {{ t('campaigns.invitation.invalid') }}
                </p>
                <Link
                    href="/campanas"
                    :class="primaryLinkClass"
                >
                    {{ t('campaigns.invitation.viewCampaigns') }}
                </Link>
            </template>

            <template v-else>
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-light">
                        <svg
                            class="h-8 w-8 text-primary"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-base-primary">
                        {{ t('campaigns.invitation.title', { campaign: invitation.campaign_title }) }}
                    </h1>
                </div>

                <p class="mb-2 text-center text-base-secondary">
                    {{ t('campaigns.invitation.intro', { inviter: invitation.inviter_name }) }}
                </p>
                <p
                    v-if="expiresAt"
                    class="mb-6 text-center text-sm text-base-muted"
                >
                    {{ t('campaigns.invitation.expires', { date: expiresAt }) }}
                </p>

                <!-- Another account: no email, nothing to do but sign in with the right one -->
                <div
                    v-if="state === 'mismatch'"
                    class="rounded-lg bg-warning-light p-4 text-center"
                    role="alert"
                >
                    <p class="text-sm text-base-primary">
                        {{ t('campaigns.invitation.mismatch') }}
                    </p>
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        class="mt-3"
                        @click="logout"
                    >
                        {{ t('campaigns.invitation.logout') }}
                    </BaseButton>
                </div>

                <!-- No session: sign in or register with the invited email, then come back here -->
                <template v-else-if="state === 'guest'">
                    <p class="mb-6 text-center text-base-primary">
                        {{ t('campaigns.invitation.useEmail', { email: invitation.email }) }}
                    </p>
                    <div class="flex flex-col gap-4 sm:flex-row">
                        <a
                            :href="`${baseUrl}/iniciar-sesion`"
                            :class="[primaryLinkClass, 'flex-1']"
                        >
                            {{ t('campaigns.invitation.login') }}
                        </a>
                        <a
                            :href="`${baseUrl}/crear-cuenta`"
                            :class="[secondaryLinkClass, 'flex-1']"
                        >
                            {{ t('campaigns.invitation.register') }}
                        </a>
                    </div>
                </template>

                <!-- The right account -->
                <template v-else>
                    <div
                        v-if="state === 'must_verify'"
                        class="mb-6 rounded-lg bg-warning-light p-4 text-center"
                        role="status"
                    >
                        <p class="text-sm text-base-primary">
                            {{ t('campaigns.invitation.mustVerify') }}
                        </p>
                        <!-- Through the module, which remembers the invitation to come back to it -->
                        <a
                            :href="`${baseUrl}/verificar`"
                            class="mt-2 inline-block text-sm font-medium text-primary underline"
                        >
                            {{ t('campaigns.invitation.verifyLink') }}
                        </a>
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row">
                        <BaseButton
                            v-if="state === 'match'"
                            variant="primary"
                            class="flex-1"
                            :loading="isAccepting"
                            @click="handleAccept"
                        >
                            {{ t('campaigns.invitation.accept') }}
                        </BaseButton>
                        <BaseButton
                            variant="secondary"
                            class="flex-1"
                            @click="showRejectDialog = true"
                        >
                            {{ t('campaigns.invitation.reject') }}
                        </BaseButton>
                    </div>
                </template>

                <!-- Rejecting needs no account: whoever has the link can delete their email -->
                <div
                    v-if="state === 'guest'"
                    class="mt-4 text-center"
                >
                    <button
                        type="button"
                        class="min-h-[44px] px-2 text-sm text-base-muted underline hover:text-base-primary"
                        @click="showRejectDialog = true"
                    >
                        {{ t('campaigns.invitation.reject') }}
                    </button>
                </div>
            </template>
        </div>
    </div>

    <ConfirmDialog
        v-model="showRejectDialog"
        :title="t('campaigns.invitation.rejectTitle')"
        :message="t('campaigns.invitation.rejectMessage')"
        :confirm-label="t('campaigns.invitation.reject')"
        :cancel-label="t('common.cancel')"
        confirm-variant="danger"
        @confirm="handleReject"
    />
</template>
