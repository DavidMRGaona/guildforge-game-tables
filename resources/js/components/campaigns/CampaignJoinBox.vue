<script setup lang="ts">
// What the viewer of a campaign page can do with its roster: ask to join, withdraw the request,
// leave, or go to the roster page when they direct it. The server decides the state.
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useRoutes } from '@/composables/useRoutes';
import { useFlashMessages } from '@/composables/useFlashMessages';
import { useVerificationGate } from '@/composables/useVerificationGate';
import VerificationBlockedButton from '../VerificationBlockedButton.vue';
import JoinRequestDialog from './JoinRequestDialog.vue';
import type { JoinBox } from '../../types/gametables';

interface Props {
    joinBox: JoinBox;
    campaignTitle: string;
    campaignSlug: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const routes = useRoutes();
const gate = useVerificationGate();
const { success: flashSuccess, error: flashError } = useFlashMessages();

const error = ref<string | null>(null);
const processing = ref(false);
const showLeaveDialog = ref(false);
const showWithdrawDialog = ref(false);
// The request is written in a dialog; its errors show there, not in the box
const showRequestDialog = ref(false);
const requestError = ref<string | null>(null);

const baseUrl = computed(() => `/campanas/${props.campaignSlug}`);

// The look of BaseButton (size sm), for the roster link and the disabled stand-in of an account
// that must verify its email
const primaryButtonClass = 'inline-flex min-h-[44px] items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white';
const secondaryButtonClass = 'inline-flex min-h-[44px] items-center justify-center rounded-md bg-muted px-4 py-2 text-sm font-medium text-base-primary';

type VisitOptions = NonNullable<Parameters<typeof router.delete>[1]>;

function visitOptions(onSuccess?: () => void): VisitOptions {
    processing.value = true;
    error.value = null;

    return {
        preserveScroll: true,
        onSuccess: (page) => {
            // Must verify after all (e.g. changed in another tab): the server sent us to the notice
            if (gate.isVerificationRequired(page)) return;
            onSuccess?.();
        },
        onError: (errors) => {
            error.value = Object.values(errors)[0] ?? null;
        },
        onFinish: () => {
            processing.value = false;
        },
    };
}

function openRequest(): void {
    if (gate.blocked.value) return;

    requestError.value = null;
    showRequestDialog.value = true;
}

function requestToJoin(message: string | null): void {
    if (gate.blocked.value) return;

    processing.value = true;
    requestError.value = null;
    router.post(
        `${baseUrl.value}/solicitud`,
        { message },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                if (gate.isVerificationRequired(page)) return;
                // A refusal (e.g. the roster filled up meanwhile) comes back as an error flash
                if (flashError.value) {
                    requestError.value = flashError.value;
                    return;
                }
                showRequestDialog.value = false;
            },
            onError: (errors) => {
                requestError.value = Object.values(errors)[0] ?? null;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function askWithdraw(): void {
    if (gate.blocked.value) return;

    showWithdrawDialog.value = true;
}

function confirmWithdraw(): void {
    if (gate.blocked.value) return;

    router.delete(`${baseUrl.value}/solicitud`, visitOptions());
}

function askLeave(): void {
    if (gate.blocked.value) return;

    showLeaveDialog.value = true;
}

function confirmLeave(): void {
    if (gate.blocked.value) return;

    router.delete(`${baseUrl.value}/plantilla`, visitOptions());
}

const closedMessage = computed<string | null>(() => {
    switch (props.joinBox.state) {
        case 'full':
            return t('campaigns.join.full');
        case 'not_accepting':
            return t('campaigns.join.notAccepting');
        case 'finished':
            return t('campaigns.join.finished');
        default:
            return null;
    }
});
</script>

<template>
    <div class="rounded-lg border border-default bg-surface p-4 text-sm">
        <!-- The server's answer to the last action -->
        <div
            v-if="flashSuccess"
            role="status"
            class="mb-3 rounded-lg bg-success-light p-3 text-base-primary"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError || error"
            role="alert"
            class="mb-3 rounded-lg bg-error-light p-3 text-base-primary"
        >
            {{ error ?? flashError }}
        </div>

        <!-- Direction -->
        <template v-if="joinBox.state === 'director'">
            <p class="font-semibold text-base-primary">{{ t('campaigns.join.director') }}</p>
            <p
                v-if="joinBox.pending_requests > 0"
                class="mt-2 inline-flex rounded-full bg-warning-light px-2 py-0.5 text-xs font-medium text-base-primary"
            >
                {{ t('campaigns.myCampaigns.pendingRequests', { count: joinBox.pending_requests }, joinBox.pending_requests) }}
            </p>
            <div v-if="joinBox.roster_url" class="mt-3">
                <VerificationBlockedButton
                    v-if="gate.blocked.value"
                    :reason="gate.reason('form')"
                    :button-class="`${primaryButtonClass} w-full`"
                >
                    {{ t('campaigns.join.manageRoster') }}
                </VerificationBlockedButton>
                <Link
                    v-else
                    :href="joinBox.roster_url"
                    :class="primaryButtonClass"
                    class="w-full transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
                >
                    {{ t('campaigns.join.manageRoster') }}
                </Link>
            </div>
        </template>

        <!-- In the roster -->
        <template v-else-if="joinBox.state === 'member'">
            <p class="font-semibold text-base-primary">{{ t('campaigns.join.memberTitle') }}</p>
            <p class="mt-1 text-base-secondary">{{ t('campaigns.join.memberHint') }}</p>
            <div class="mt-3">
                <VerificationBlockedButton
                    v-if="gate.blocked.value"
                    :reason="gate.reason('withdraw')"
                    :button-class="secondaryButtonClass"
                >
                    {{ t('campaigns.join.leave') }}
                </VerificationBlockedButton>
                <BaseButton
                    v-else
                    variant="secondary"
                    size="sm"
                    class="w-full sm:w-auto"
                    :loading="processing"
                    @click="askLeave"
                >
                    {{ t('campaigns.join.leave') }}
                </BaseButton>
            </div>
        </template>

        <!-- Request sent -->
        <template v-else-if="joinBox.state === 'pending'">
            <p v-if="joinBox.requested_at" class="font-semibold text-base-primary">
                {{ t('campaigns.join.pendingSince', { date: joinBox.requested_at }) }}
            </p>
            <p class="mt-1 text-base-secondary">{{ t('campaigns.join.pendingHint') }}</p>
            <div class="mt-3">
                <VerificationBlockedButton
                    v-if="gate.blocked.value"
                    :reason="gate.reason('withdraw')"
                    :button-class="secondaryButtonClass"
                >
                    {{ t('campaigns.join.withdraw') }}
                </VerificationBlockedButton>
                <BaseButton
                    v-else
                    variant="secondary"
                    size="sm"
                    class="w-full sm:w-auto"
                    :loading="processing"
                    @click="askWithdraw"
                >
                    {{ t('campaigns.join.withdraw') }}
                </BaseButton>
            </div>
        </template>

        <!-- No session: log in first -->
        <template v-else-if="joinBox.state === 'guest'">
            <p class="text-base-secondary">{{ t('campaigns.join.guest') }}</p>
            <Link
                :href="routes.auth.login"
                class="mt-2 inline-flex font-medium text-primary underline underline-offset-2 hover:no-underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            >
                {{ t('campaigns.join.loginToRequest') }}
            </Link>
        </template>

        <!-- May ask to join: the message is written in a dialog -->
        <template v-else-if="joinBox.state === 'can_request'">
            <VerificationBlockedButton
                v-if="gate.blocked.value"
                :reason="gate.reason('form')"
                :button-class="`${primaryButtonClass} w-full sm:w-auto`"
            >
                {{ t('campaigns.join.request') }}
            </VerificationBlockedButton>
            <BaseButton
                v-else
                variant="primary"
                size="sm"
                class="w-full sm:w-auto"
                aria-haspopup="dialog"
                @click="openRequest"
            >
                {{ t('campaigns.join.request') }}
            </BaseButton>
            <p class="mt-2 text-base-muted">{{ t('campaigns.join.requestHint') }}</p>
        </template>

        <!-- Members only, and the viewer is not one -->
        <template v-else-if="joinBox.state === 'members_only'">
            <p class="font-semibold text-base-primary">{{ t('campaigns.join.membersOnly') }}</p>
            <p class="mt-1 text-base-secondary">{{ t('campaigns.join.membersOnlyHint') }}</p>
        </template>

        <!-- Full, closed or finished -->
        <p v-else-if="closedMessage" class="font-semibold text-base-primary">
            {{ closedMessage }}
        </p>

        <JoinRequestDialog
            v-model="showRequestDialog"
            :campaign-title="campaignTitle"
            :error="requestError"
            :processing="processing"
            @submit="requestToJoin"
        />

        <ConfirmDialog
            v-model="showWithdrawDialog"
            :title="t('campaigns.join.withdrawTitle')"
            :message="t('campaigns.join.withdrawMessage', { title: campaignTitle })"
            :confirm-label="t('campaigns.join.withdrawConfirm')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmWithdraw"
        />

        <ConfirmDialog
            v-model="showLeaveDialog"
            :title="t('campaigns.join.leaveTitle')"
            :message="t('campaigns.join.leaveMessage', { title: campaignTitle })"
            :confirm-label="t('campaigns.join.leaveConfirm')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmLeave"
        />
    </div>
</template>
