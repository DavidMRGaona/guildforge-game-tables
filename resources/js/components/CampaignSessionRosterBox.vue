<script lang="ts">
// Shared by every instance. The table page renders the registration slot anew on each Inertia
// visit, so a successful action may re-create this box: the focus owed to its new action is
// handed to the instance on the page (as RegistrationButton does with its button).
const focusOwed = new Map<string, number>(); // table id → until (ms)
const focusers = new Map<string, () => boolean>(); // table id → the live instance's focus
const FOCUS_HANDOFF_MS = 5000;
</script>

<script setup lang="ts">
// A roster member's place in a campaign session: enrolled by the campaign (or waiting with
// priority), or not going. They drop this session only, or take their seat back while there is one.
// The server decides the state and whether the seat can be taken back.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useVerificationGate } from '@/composables/useVerificationGate';
import VerificationBlockedButton from './VerificationBlockedButton.vue';
import type { GameTable } from '../types/gametables';
import type { CampaignSessionInfo, ParticipantData } from '../types/registration';

interface Props {
    table: GameTable;
    campaignSession: CampaignSessionInfo;
    /** The member's row in this session (null when there is none) */
    registration: ParticipantData | null;
    /** The server lets the member drop the seat (the session has not started) */
    canCancel: boolean;
}

const props = defineProps<Props>();

const { t } = useI18n();
const gate = useVerificationGate();

const processing = ref(false);
const error = ref<string | null>(null);
const showCancelDialog = ref(false);
const actionButton = ref<HTMLButtonElement | null>(null);
const statusLine = ref<HTMLElement | null>(null);
const hintId = useId();
const refusalId = useId();

const state = computed(() => props.campaignSession.roster_state ?? 'none');
const hasSeat = computed(() => state.value === 'enrolled' || state.value === 'waiting');
const campaignUrl = computed(() =>
    props.campaignSession.campaign_slug !== null ? `/campanas/${props.campaignSession.campaign_slug}` : null
);
const waitingPosition = computed(
    () => props.campaignSession.waiting_list_position ?? props.registration?.waitingListPosition ?? null
);
// The seat can be taken back now, or the server says why not. A rejected or no-show row gets
// neither: nothing to offer, so no dead button and no «vuelve a apuntarte»
const rejoinOffered = computed(
    () => props.campaignSession.can_rejoin || props.campaignSession.rejoin_refusal !== null
);

const buttonClass = 'inline-flex min-h-[44px] items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 aria-disabled:cursor-not-allowed aria-disabled:opacity-50 disabled:cursor-not-allowed disabled:opacity-50';
const primaryButtonClass = `${buttonClass} bg-primary text-white hover:bg-primary-700 focus:ring-primary-500`;
const secondaryButtonClass = `${buttonClass} bg-muted text-base-primary hover:bg-neutral-200 focus:ring-neutral-400 dark:hover:bg-neutral-700`;

// The action on the page, or the status line when the action cannot take the focus
function focusAction(): boolean {
    const button = actionButton.value;
    const target = button !== null && button.isConnected && !button.disabled ? button : statusLine.value;
    if (target === null || !target.isConnected) return false;

    target.focus();
    focusOwed.delete(props.table.id);
    return true;
}

// After a successful action the button that was used goes away: whichever instance shows the new one takes the focus
function oweFocus(): void {
    focusOwed.set(props.table.id, Date.now() + FOCUS_HANDOFF_MS);
    void nextTick(() => focusers.get(props.table.id)?.());
}

onMounted(async () => {
    focusers.set(props.table.id, focusAction);

    const until = focusOwed.get(props.table.id);
    if (until === undefined) return;
    if (Date.now() > until) {
        focusOwed.delete(props.table.id);
        return;
    }
    await nextTick();
    focusAction();
});

onBeforeUnmount(() => {
    if (focusers.get(props.table.id) === focusAction) {
        focusers.delete(props.table.id);
    }
});

type VisitOptions = NonNullable<Parameters<typeof router.delete>[1]>;

function visitOptions(): VisitOptions {
    processing.value = true;
    error.value = null;

    return {
        preserveScroll: true,
        onSuccess: (page) => {
            // Must verify after all (e.g. changed in another tab): the server sent us to the notice
            if (gate.isVerificationRequired(page)) return;
            oweFocus();
        },
        onError: (errors) => {
            error.value = Object.values(errors)[0] ?? null;
        },
        onFinish: () => {
            processing.value = false;
        },
    };
}

function askCancel(): void {
    if (processing.value || gate.blocked.value) return;

    showCancelDialog.value = true;
}

function confirmCancel(): void {
    if (processing.value || gate.blocked.value) return;

    router.delete(`/mesas/${props.table.slug}/inscripcion`, visitOptions());
}

function rejoin(): void {
    if (processing.value || gate.blocked.value || !props.campaignSession.can_rejoin) return;

    router.post(
        `/mesas/${props.table.slug}/inscripcion`,
        { role: 'player' },
        visitOptions(),
    );
}
</script>

<template>
    <div class="rounded-lg border border-default bg-surface p-4 text-sm">
        <div
            v-if="error"
            role="alert"
            class="mb-3 rounded-lg bg-red-50 p-3 text-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            {{ error }}
        </div>

        <!-- Enrolled by the campaign, or waiting with the roster's priority -->
        <template v-if="hasSeat">
            <p ref="statusLine" tabindex="-1" class="focus:outline-none">
                <span
                    v-if="state === 'enrolled'"
                    class="inline-flex rounded-full bg-success-light px-2.5 py-0.5 text-sm font-medium text-success"
                >
                    {{ t('gameTables.campaignSession.enrolled') }}
                </span>
                <span v-else class="font-semibold text-base-primary">
                    {{ waitingPosition !== null
                        ? t('gameTables.campaignSession.waiting', { position: waitingPosition })
                        : t('gameTables.campaignSession.waitingNoPosition') }}
                </span>
            </p>
            <p v-if="state === 'enrolled'" class="mt-2 text-base-secondary">
                {{ t('gameTables.campaignSession.sessionOf') }}
                <Link
                    v-if="campaignUrl"
                    :href="campaignUrl"
                    class="font-medium text-primary underline underline-offset-2 hover:no-underline focus:outline-none focus:ring-2 focus:ring-primary-500"
                >{{ campaignSession.campaign_title }}</Link><template v-else>{{ campaignSession.campaign_title }}</template>.
            </p>

            <div class="mt-3">
                <VerificationBlockedButton
                    v-if="gate.blocked.value"
                    :reason="gate.reason('cancelRegistration')"
                    :button-class="secondaryButtonClass"
                >
                    {{ t('gameTables.campaignSession.cannotGo') }}
                </VerificationBlockedButton>
                <button
                    v-else-if="canCancel"
                    ref="actionButton"
                    type="button"
                    :aria-disabled="processing ? 'true' : undefined"
                    :aria-describedby="hintId"
                    :class="secondaryButtonClass"
                    @click="askCancel"
                >
                    {{ t('gameTables.campaignSession.cannotGo') }}
                </button>
                <p v-else class="text-base-secondary">
                    {{ t('gameTables.registration.withdrawalClosed') }}
                </p>
            </div>
            <p :id="hintId" class="mt-1 text-xs text-base-muted">
                {{ t('gameTables.campaignSession.stillInCampaign') }}
            </p>
        </template>

        <!-- Not going: the seat can be taken back while there is one -->
        <template v-else>
            <p ref="statusLine" tabindex="-1" class="focus:outline-none">
                <span class="inline-flex rounded-full bg-warning-bg px-2.5 py-0.5 text-sm font-medium text-warning">
                    {{ t('gameTables.campaignSession.absent') }}
                </span>
            </p>
            <p v-if="rejoinOffered" class="mt-2 text-base-secondary">
                {{ t('gameTables.campaignSession.absentHint') }}
            </p>

            <div v-if="rejoinOffered" class="mt-3">
                <VerificationBlockedButton
                    v-if="gate.blocked.value"
                    :reason="gate.reason('register')"
                    :button-class="primaryButtonClass"
                >
                    {{ t('gameTables.campaignSession.rejoin') }}
                </VerificationBlockedButton>
                <template v-else>
                    <button
                        ref="actionButton"
                        type="button"
                        :disabled="!campaignSession.can_rejoin"
                        :aria-disabled="processing ? 'true' : undefined"
                        :aria-describedby="campaignSession.rejoin_refusal ? refusalId : undefined"
                        :class="primaryButtonClass"
                        @click="rejoin"
                    >
                        {{ t('gameTables.campaignSession.rejoin') }}
                    </button>
                    <p
                        v-if="!campaignSession.can_rejoin && campaignSession.rejoin_refusal"
                        :id="refusalId"
                        class="mt-2 text-base-secondary"
                    >
                        {{ campaignSession.rejoin_refusal }}
                    </p>
                </template>
            </div>
        </template>

        <ConfirmDialog
            v-model="showCancelDialog"
            :title="t('gameTables.campaignSession.cannotGoTitle')"
            :message="t('gameTables.campaignSession.cannotGoMessage')"
            :confirm-label="t('gameTables.campaignSession.cannotGoConfirm')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmCancel"
        />
    </div>
</template>
