<script setup lang="ts">
// What the viewer of a campaign page can do with its roster: ask to join, withdraw the request,
// leave, or go to the roster page when they direct it. The server decides the state.
import { computed, ref, useId } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useRoutes } from '@/composables/useRoutes';
import { useFlashMessages } from '@/composables/useFlashMessages';
import { useVerificationGate } from '@/composables/useVerificationGate';
import VerificationBlockedButton from '../VerificationBlockedButton.vue';
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

const message = ref('');
const messageId = useId();
const error = ref<string | null>(null);
const processing = ref(false);
const showLeaveDialog = ref(false);

const baseUrl = computed(() => `/campanas/${props.campaignSlug}`);

// The look of BaseButton (size sm), for the disabled stand-in of an account that must verify its email
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

function requestToJoin(): void {
    if (gate.blocked.value) return;

    const text = message.value.trim();
    router.post(
        `${baseUrl.value}/solicitud`,
        { message: text === '' ? null : text },
        visitOptions(() => {
            message.value = '';
        }),
    );
}

function withdrawRequest(): void {
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
            class="mb-3 rounded-lg bg-success-light p-3 text-success"
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
            <Link
                v-if="joinBox.roster_url"
                :href="joinBox.roster_url"
                class="mt-2 inline-flex font-medium text-primary underline underline-offset-2 hover:no-underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            >
                {{ t('campaigns.join.manageRoster') }}
            </Link>
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
                <BaseButton v-else variant="secondary" size="sm" :loading="processing" @click="askLeave">
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
                <BaseButton v-else variant="secondary" size="sm" :loading="processing" @click="withdrawRequest">
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

        <!-- May ask to join -->
        <form v-else-if="joinBox.state === 'can_request'" @submit.prevent="requestToJoin">
            <label :for="messageId" class="sr-only">{{ t('campaigns.join.message') }}</label>
            <textarea
                :id="messageId"
                v-model="message"
                name="message"
                rows="3"
                maxlength="500"
                :disabled="gate.blocked.value"
                :placeholder="t('campaigns.join.message')"
                class="block w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary placeholder-base-muted hover:border-strong focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
            />
            <div class="mt-3">
                <VerificationBlockedButton
                    v-if="gate.blocked.value"
                    :reason="gate.reason('form')"
                    :button-class="primaryButtonClass"
                >
                    {{ t('campaigns.join.request') }}
                </VerificationBlockedButton>
                <BaseButton v-else type="submit" variant="primary" size="sm" :loading="processing">
                    {{ t('campaigns.join.request') }}
                </BaseButton>
            </div>
            <p class="mt-2 text-base-muted">{{ t('campaigns.join.requestHint') }}</p>
        </form>

        <!-- Members only, and the viewer is not one -->
        <template v-else-if="joinBox.state === 'members_only'">
            <p class="font-semibold text-base-primary">{{ t('campaigns.join.membersOnly') }}</p>
            <p class="mt-1 text-base-secondary">{{ t('campaigns.join.membersOnlyHint') }}</p>
        </template>

        <!-- Full, closed or finished -->
        <p v-else-if="closedMessage" class="font-semibold text-base-primary">
            {{ closedMessage }}
        </p>

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
