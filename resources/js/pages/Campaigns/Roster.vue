<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { FormCheckbox } from '@/components/form';
import { useFlashMessages } from '@/composables/useFlashMessages';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type {
    RosterAccessValue,
    RosterInvitation,
    RosterPage,
    RosterPlayer,
    RosterPlayerSession,
    RosterRequest,
    RosterSession,
    RosterSessionState,
} from '../../types/gametables';

const props = defineProps<RosterPage>();

const { t, locale } = useI18n();
const { success: flashSuccess, error: flashError } = useFlashMessages();

const baseUrl = computed(() => `/campanas/mis-campanas/${props.campaign.id}/plantilla`);

// A roster action is on its way: its buttons stay focusable (aria-disabled) and ignore clicks
// until the answer, so keyboard users keep their place
const processing = ref(false);

type VisitOptions = NonNullable<Parameters<typeof router.delete>[1]>;

function visitOptions(onFinish?: () => void): VisitOptions {
    processing.value = true;

    return {
        preserveScroll: true,
        // Same page: keep the instance, so the focused button survives the reload of the props
        preserveState: true,
        onFinish: () => {
            processing.value = false;
            onFinish?.();
        },
    };
}

// Header
const accessLabel = computed(
    () => props.roster_access_options[props.campaign.roster_access] ?? props.campaign.roster_access
);

const subtitle = computed(() => {
    const count = props.campaign.roster_count;

    return props.campaign.max_players !== null
        ? t('campaigns.roster.subtitle', {
              count,
              max: props.campaign.max_players,
              access: accessLabel.value,
          })
        : t('campaigns.roster.subtitleNoMax', { count, access: accessLabel.value }, count);
});

// Settings
const accessOptions = computed(
    () => Object.entries(props.roster_access_options) as Array<[RosterAccessValue, string]>
);

const settingsForm = useForm<{
    accepts_new_players: boolean;
    roster_access: RosterAccessValue;
    max_players: number | null;
}>({
    accepts_new_players: props.campaign.accepts_new_players,
    roster_access: props.campaign.roster_access,
    max_players: props.campaign.max_players,
});

const settingsIds = {
    access: useId(),
    max: useId(),
    maxHelp: useId(),
    maxError: useId(),
};

function onMaxPlayersInput(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    // Empty means no limit
    settingsForm.max_players = value === '' ? null : Number(value);
}

function saveSettings(): void {
    settingsForm.put(`${baseUrl.value}/ajustes`, { preserveScroll: true });
}

// Requests
function acceptRequest(request: RosterRequest): void {
    if (processing.value) return;

    router.post(`${baseUrl.value}/solicitudes/${request.id}/aceptar`, {}, visitOptions());
}

// The reject dialog asks for an optional reason, so it is ConfirmDialog's markup with a textarea
const rejecting = ref<RosterRequest | null>(null);
const rejectReason = ref('');

const rejectDialog = ref<HTMLDialogElement | null>(null);
const rejectTitleId = useId();
const rejectReasonId = useId();

watch(rejecting, async (request) => {
    if (request === null) {
        rejectDialog.value?.close();
        return;
    }

    rejectReason.value = '';
    await nextTick();
    rejectDialog.value?.showModal();
});

function openReject(request: RosterRequest): void {
    if (processing.value) return;

    rejecting.value = request;
}

function closeReject(): void {
    rejecting.value = null;
}

function onRejectBackdropClick(event: MouseEvent): void {
    if (event.target === rejectDialog.value) {
        closeReject();
    }
}

function confirmReject(): void {
    const request = rejecting.value;
    if (request === null) return;

    const reason = rejectReason.value.trim();
    closeReject();
    router.post(
        `${baseUrl.value}/solicitudes/${request.id}/rechazar`,
        { reason: reason === '' ? null : reason },
        visitOptions()
    );
}

// Invite a player by email; the consent is the direction's word that the person agreed
const inviteForm = useForm({ email: '', consent: false });
const inviteEmailId = useId();
const inviteErrorId = useId();
const inviteConsentId = useId();

function invitePlayer(): void {
    inviteForm.post(`${baseUrl.value}/invitaciones`, {
        preserveScroll: true,
        // A refusal (member, limits...) also comes back as a success with an error flash: keep
        // what was typed so it can be corrected
        onSuccess: () => {
            if (!flashError.value) inviteForm.reset();
        },
    });
}

function formatExpiry(invitation: RosterInvitation): string {
    return new Date(invitation.expires_at).toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function resendInvitation(invitation: RosterInvitation): void {
    if (processing.value || !invitation.can_resend) return;

    router.post(`${baseUrl.value}/invitaciones/${invitation.id}/reenviar`, {}, visitOptions());
}

const cancelling = ref<RosterInvitation | null>(null);
const showCancelDialog = ref(false);

function askCancel(invitation: RosterInvitation): void {
    if (processing.value) return;

    cancelling.value = invitation;
    showCancelDialog.value = true;
}

function confirmCancel(): void {
    const invitation = cancelling.value;
    if (invitation === null) return;

    router.delete(
        `${baseUrl.value}/invitaciones/${invitation.id}`,
        visitOptions(() => {
            cancelling.value = null;
        })
    );
}

// Attendance: players × upcoming sessions. A window of sessions is shown at a time (a director may
// publish a whole season): 4 columns from sm up, 2 below, always starting at the nearest session
const smQuery = typeof window !== 'undefined' ? window.matchMedia('(min-width: 640px)') : null;
const isSmUp = ref(smQuery?.matches ?? true);

function onBreakpointChange(event: MediaQueryListEvent): void {
    isSmUp.value = event.matches;
}
smQuery?.addEventListener('change', onBreakpointChange);
onBeforeUnmount(() => smQuery?.removeEventListener('change', onBreakpointChange));

const pageSize = computed(() => (isSmUp.value ? 4 : 2));
const windowStart = ref(0);

// Keep the window inside the list when the page size or the sessions change
watch([pageSize, () => props.sessions.length], () => {
    const lastStart = Math.max(0, props.sessions.length - pageSize.value);
    windowStart.value = Math.min(windowStart.value, lastStart);
});

const visibleSessions = computed(() =>
    props.sessions.slice(windowStart.value, windowStart.value + pageSize.value)
);
const windowEnd = computed(() =>
    Math.min(windowStart.value + pageSize.value, props.sessions.length)
);
const hasPrevious = computed(() => windowStart.value > 0);
const hasNext = computed(() => windowEnd.value < props.sessions.length);

const rangeLabel = computed(() =>
    t('campaigns.roster.attendance.range', {
        from: windowStart.value + 1,
        to: windowEnd.value,
        total: props.sessions.length,
    })
);

// «Anteriores: sesiones 1–4 de 20»: the visible text first, then the range it leads to
const previousLabel = computed(() =>
    t('campaigns.roster.attendance.previousRange', {
        from: Math.max(1, windowStart.value - pageSize.value + 1),
        to: windowStart.value,
        total: props.sessions.length,
    })
);
const nextLabel = computed(() =>
    t('campaigns.roster.attendance.nextRange', {
        from: windowEnd.value + 1,
        to: Math.min(windowEnd.value + pageSize.value, props.sessions.length),
        total: props.sessions.length,
    })
);

function showPrevious(): void {
    windowStart.value = Math.max(0, windowStart.value - pageSize.value);
}

function showNext(): void {
    if (hasNext.value) windowStart.value += pageSize.value;
}

// «sáb 25/10» in the venue's time zone
function weekday(session: RosterSession): string {
    return new Date(session.starts_at).toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'short',
    });
}

function sessionLabel(session: RosterSession): string {
    return `${weekday(session)} ${session.date_short}`;
}

function sessionLongLabel(session: RosterSession): string {
    return `${weekday(session)} ${session.date}`;
}

function cellOf(player: RosterPlayer, session: RosterSession): RosterPlayerSession | null {
    return player.sessions.find((cell) => cell.game_table_id === session.game_table_id) ?? null;
}

// No cell for a session (none expected: the query sends one per session) reads as not registered
function stateOf(player: RosterPlayer, session: RosterSession): RosterSessionState {
    return cellOf(player, session)?.state ?? 'none';
}

// What a cell lets the direction do, from what the roster query returns: an absence is marked on a
// roster row (going or waiting, can_toggle) and cleared while the session admits roster enrolment
// (can_toggle) and has a free seat (as the server checks it)
type CellAction =
    | { kind: 'markAbsence' }
    | { kind: 'clearAbsence' }
    | { kind: 'blocked'; reason: 'notFromRoster' | 'closed' | 'full' }
    | null;

function cellAction(cell: RosterPlayerSession | null, session: RosterSession): CellAction {
    if (cell === null) return null;

    switch (cell.state) {
        case 'going':
        case 'waiting':
            return cell.can_toggle
                ? { kind: 'markAbsence' }
                : { kind: 'blocked', reason: 'notFromRoster' };
        case 'absent':
            if (!cell.can_toggle) return { kind: 'blocked', reason: 'closed' };
            return session.has_room
                ? { kind: 'clearAbsence' }
                : { kind: 'blocked', reason: 'full' };
        default:
            return null;
    }
}

function isActionable(cell: RosterPlayerSession | null, session: RosterSession): boolean {
    const action = cellAction(cell, session);

    return action !== null && action.kind !== 'blocked';
}

// Why a cell offers no change, in a word or two under its state
function blockedShort(cell: RosterPlayerSession | null, session: RosterSession): string | null {
    const action = cellAction(cell, session);

    return action?.kind === 'blocked'
        ? t(`campaigns.roster.attendance.blockedShort.${action.reason}`)
        : null;
}

const stateKeys: Record<RosterSessionState, string> = {
    going: 'campaigns.roster.attendance.states.going',
    absent: 'campaigns.roster.attendance.states.absent',
    waiting: 'campaigns.roster.attendance.states.waiting',
    none: 'campaigns.roster.attendance.states.none',
};

const stateIcons: Record<RosterSessionState, string> = {
    going: '✓',
    absent: '✕',
    waiting: '…',
    none: '—',
};

// Theme tokens: their value flips with the theme (module styles load below the core's)
const cellTones: Record<RosterSessionState, string> = {
    going: 'border-success/40 bg-success-light text-base-primary',
    absent: 'border-error/40 bg-error-light text-base-primary',
    waiting: 'border-warning/40 bg-warning-light text-base-primary',
    none: 'border-default bg-base-secondary/10 text-base-secondary',
};

function stateLabel(state: RosterSessionState): string {
    return t(stateKeys[state]);
}

// «✓ Va — Jane Doe, sáb 09/11/2026 18:00. Marcar ausencia»: starts with the visible text (so voice
// control finds the cell by what it shows), then the player, the full date and the action
function cellAccessibleName(
    player: RosterPlayer,
    session: RosterSession,
    cell: RosterPlayerSession | null
): string {
    const state: RosterSessionState = cell?.state ?? 'none';
    const action = cellAction(cell, session);
    const after =
        action === null
            ? ''
            : action.kind === 'blocked'
              ? t(`campaigns.roster.attendance.blocked.${action.reason}`)
              : t(`campaigns.roster.attendance.actions.${action.kind}`);

    return t('campaigns.roster.attendance.accessibleName', {
        label: `${stateIcons[state]} ${stateLabel(state)}`,
        name: player.name,
        date: sessionLongLabel(session),
        action: after,
    }).trim();
}

const cellKey = (player: RosterPlayer, session: RosterSession): string =>
    `${player.user_id}:${session.game_table_id}`;

// The cell whose change is on its way: it shows a spinner until the answer
const busyCell = ref<string | null>(null);

const pendingChange = ref<{
    player: RosterPlayer;
    session: RosterSession;
    kind: 'markAbsence' | 'clearAbsence';
} | null>(null);
const showChangeDialog = ref(false);

function askChange(player: RosterPlayer, session: RosterSession): void {
    if (processing.value) return;

    const action = cellAction(cellOf(player, session), session);
    if (action === null || action.kind === 'blocked') return;

    pendingChange.value = { player, session, kind: action.kind };
    showChangeDialog.value = true;
}

const changeTitle = computed(() =>
    pendingChange.value ? t(`campaigns.roster.attendance.actions.${pendingChange.value.kind}`) : ''
);

const changeMessage = computed(() => {
    const change = pendingChange.value;
    if (change === null) return '';

    return t(`campaigns.roster.attendance.confirm.${change.kind}`, {
        name: change.player.name,
        date: sessionLongLabel(change.session),
    });
});

function confirmChange(): void {
    const change = pendingChange.value;
    if (change === null || processing.value) return;

    busyCell.value = cellKey(change.player, change.session);
    router.post(
        `${baseUrl.value}/ausencias`,
        {
            game_table_id: change.session.game_table_id,
            user_id: change.player.user_id,
            absent: change.kind === 'markAbsence',
        },
        visitOptions(() => {
            busyCell.value = null;
        })
    );
}

const removing = ref<RosterPlayer | null>(null);
const showRemoveDialog = ref(false);

function askRemove(player: RosterPlayer): void {
    if (processing.value) return;

    removing.value = player;
    showRemoveDialog.value = true;
}

// The name stays in the dialog's message until it has closed: it is cleared after the visit
function confirmRemove(): void {
    const player = removing.value;
    if (player === null) return;

    router.delete(
        `${baseUrl.value}/jugadores/${player.user_id}`,
        visitOptions(() => {
            removing.value = null;
        })
    );
}

// What the server does on removal (CampaignRosterService::removeMember): out of the roster, out of
// the sessions that have not started, and an email only while the roster_removed notice is on
const removeMessage = computed(() =>
    t(
        props.removal_notice
            ? 'campaigns.roster.players.removeMessage'
            : 'campaigns.roster.players.removeMessageNoEmail',
        {
            name: removing.value?.name ?? '',
        }
    )
);

// A danger action that does not shout: BaseButton's size sm, outlined in the error tone
const dangerOutlineClass =
    'inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center gap-2 rounded-md border border-error/60 bg-transparent px-4 py-2 text-sm font-medium text-error transition-colors hover:bg-error/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-error focus-visible:ring-offset-2 focus-visible:ring-offset-surface aria-disabled:cursor-not-allowed aria-disabled:opacity-50';

const inputClass =
    'mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5 text-base-primary placeholder-base-muted hover:border-strong focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500';
const sectionClass = 'rounded-lg bg-surface p-4 shadow sm:p-6';
</script>

<template>
    <div class="bg-surface shadow">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <Link
                href="/campanas/mis-campanas"
                class="-my-3 inline-flex min-h-[44px] items-center gap-1 text-sm text-base-secondary hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            >
                <span aria-hidden="true">←</span>
                {{ t('campaigns.roster.back') }}
            </Link>
            <h1 class="mt-3 break-words text-3xl font-bold tracking-tight text-base-primary">
                {{ t('campaigns.roster.title', { title: campaign.title }) }}
            </h1>
            <p class="mt-2 text-base text-base-secondary">
                {{ subtitle }}
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <!-- The server's answer to the last action -->
        <div
            v-if="flashSuccess"
            role="status"
            class="rounded-lg bg-success-light p-4 text-sm text-base-primary"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            role="alert"
            class="rounded-lg bg-error-light p-4 text-sm text-base-primary"
        >
            {{ flashError }}
        </div>

        <!-- Settings -->
        <section :class="sectionClass" aria-labelledby="roster-settings-title">
            <h2 id="roster-settings-title" class="text-lg font-semibold text-base-primary">
                {{ t('campaigns.roster.settings.title') }}
            </h2>

            <p v-if="campaign.is_finished" class="mt-3 text-sm text-base-secondary">
                {{ t('campaigns.roster.settings.finished') }}
            </p>

            <form v-else class="mt-4 space-y-4" @submit.prevent="saveSettings">
                <div>
                    <FormCheckbox
                        v-model="settingsForm.accepts_new_players"
                        :label="t('campaigns.roster.settings.acceptsNewPlayers')"
                        name="accepts_new_players"
                    />
                    <p
                        v-if="settingsForm.errors.accepts_new_players"
                        class="mt-1 text-sm text-error"
                    >
                        {{ settingsForm.errors.accepts_new_players }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            :for="settingsIds.access"
                            class="block text-sm font-medium text-base-secondary"
                        >
                            {{ t('campaigns.roster.settings.rosterAccess') }}
                        </label>
                        <select
                            :id="settingsIds.access"
                            v-model="settingsForm.roster_access"
                            name="roster_access"
                            :class="inputClass"
                        >
                            <option
                                v-for="[value, label] in accessOptions"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <p v-if="settingsForm.errors.roster_access" class="mt-1 text-sm text-error">
                            {{ settingsForm.errors.roster_access }}
                        </p>
                    </div>

                    <div>
                        <label
                            :for="settingsIds.max"
                            class="block text-sm font-medium text-base-secondary"
                        >
                            {{ t('campaigns.roster.settings.maxPlayers') }}
                        </label>
                        <input
                            :id="settingsIds.max"
                            type="number"
                            name="max_players"
                            inputmode="numeric"
                            :value="settingsForm.max_players ?? ''"
                            :min="Math.max(1, campaign.roster_count)"
                            :max="max_players_limit"
                            step="1"
                            :aria-invalid="!!settingsForm.errors.max_players"
                            :aria-describedby="
                                settingsForm.errors.max_players
                                    ? `${settingsIds.maxHelp} ${settingsIds.maxError}`
                                    : settingsIds.maxHelp
                            "
                            :class="inputClass"
                            @input="onMaxPlayersInput"
                        />
                        <p :id="settingsIds.maxHelp" class="mt-1 text-sm text-base-muted">
                            {{
                                t('campaigns.roster.settings.maxPlayersHelp', {
                                    count: campaign.roster_count,
                                })
                            }}
                        </p>
                        <p
                            v-if="settingsForm.errors.max_players"
                            :id="settingsIds.maxError"
                            class="mt-1 text-sm text-error"
                        >
                            {{ settingsForm.errors.max_players }}
                        </p>
                    </div>
                </div>

                <div>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="sm"
                        :loading="settingsForm.processing"
                    >
                        {{ t('campaigns.roster.settings.save') }}
                    </BaseButton>
                </div>
            </form>
        </section>

        <!-- Pending requests -->
        <section :class="sectionClass" aria-labelledby="roster-requests-title">
            <h2 id="roster-requests-title" class="text-lg font-semibold text-base-primary">
                {{ t('campaigns.roster.requests.title', { count: requests.length }) }}
            </h2>

            <p v-if="requests.length === 0" class="mt-3 text-sm text-base-secondary">
                {{ t('campaigns.roster.requests.empty') }}
            </p>

            <ul v-else class="mt-4 divide-y divide-default" role="list">
                <li
                    v-for="request in requests"
                    :key="request.id"
                    class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="text-sm text-base-primary">
                            <span class="font-medium">{{ request.user_name }}</span>
                            <template v-if="request.created_at">
                                <span class="text-base-muted"> · {{ request.created_at }}</span>
                            </template>
                        </p>
                        <p
                            v-if="request.message"
                            class="mt-1 whitespace-pre-line break-words text-sm text-base-secondary"
                        >
                            «{{ request.message }}»
                        </p>
                        <p v-else class="mt-1 text-sm italic text-base-muted">
                            {{ t('campaigns.roster.requests.noMessage') }}
                        </p>
                    </div>
                    <!-- Below sm a full-width row of equal buttons -->
                    <div class="grid flex-shrink-0 grid-cols-2 gap-2 sm:flex">
                        <BaseButton
                            variant="primary"
                            size="sm"
                            :aria-disabled="processing ? 'true' : undefined"
                            class="w-full aria-disabled:cursor-not-allowed aria-disabled:opacity-50 sm:w-auto"
                            @click="acceptRequest(request)"
                        >
                            {{ t('campaigns.roster.requests.accept') }}
                        </BaseButton>
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            :aria-disabled="processing ? 'true' : undefined"
                            class="w-full aria-disabled:cursor-not-allowed aria-disabled:opacity-50 sm:w-auto"
                            @click="openReject(request)"
                        >
                            {{ t('campaigns.roster.requests.reject') }}
                        </BaseButton>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Invite a player -->
        <section v-if="can_add" :class="sectionClass" aria-labelledby="roster-invite-title">
            <h2 id="roster-invite-title" class="text-lg font-semibold text-base-primary">
                {{ t('campaigns.roster.invite.title') }}
            </h2>
            <form class="mt-4 flex flex-col gap-3" @submit.prevent="invitePlayer">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                    <div class="min-w-0 flex-1">
                        <label :for="inviteEmailId" class="sr-only">{{
                            t('campaigns.roster.invite.email')
                        }}</label>
                        <input
                            :id="inviteEmailId"
                            v-model="inviteForm.email"
                            type="email"
                            name="email"
                            required
                            maxlength="255"
                            autocomplete="off"
                            :placeholder="t('campaigns.roster.invite.email')"
                            :aria-invalid="!!inviteForm.errors.email"
                            :aria-describedby="inviteForm.errors.email ? inviteErrorId : undefined"
                            :class="[inputClass, 'mt-0']"
                        />
                        <p
                            v-if="inviteForm.errors.email"
                            :id="inviteErrorId"
                            class="mt-1 text-sm text-error"
                        >
                            {{ inviteForm.errors.email }}
                        </p>
                    </div>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        class="w-full sm:w-auto"
                        :loading="inviteForm.processing"
                    >
                        {{ t('campaigns.roster.invite.submit') }}
                    </BaseButton>
                </div>
                <div>
                    <FormCheckbox
                        :id="inviteConsentId"
                        v-model="inviteForm.consent"
                        name="consent"
                        :label="t('campaigns.roster.invite.consent')"
                    />
                    <p v-if="inviteForm.errors.consent" class="mt-1 text-sm text-error">
                        {{ inviteForm.errors.consent }}
                    </p>
                </div>
                <p class="text-sm text-base-secondary">{{ t('campaigns.roster.invite.note') }}</p>
            </form>
        </section>

        <!-- Pending invitations: listed even when the roster is full or the campaign has finished -->
        <section
            v-if="invitations.length > 0"
            :class="sectionClass"
            aria-labelledby="roster-invitations-title"
        >
            <div>
                <h2 id="roster-invitations-title" class="text-lg font-semibold text-base-primary">
                    {{ t('campaigns.roster.invitations.title', { count: invitations.length }) }}
                </h2>
                <p
                    v-if="more_invitations_than_seats"
                    class="mt-2 text-sm text-warning"
                    role="status"
                >
                    {{ t('campaigns.roster.invitations.tooManyPending') }}
                </p>
                <ul class="mt-3 divide-y divide-default">
                    <li
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="break-all font-medium text-base-primary">
                                {{ invitation.email }}
                            </p>
                            <p class="text-sm text-base-secondary">
                                {{
                                    t('campaigns.roster.invitations.invitedBy', {
                                        name: invitation.inviter_name,
                                    })
                                }}
                                ·
                                {{
                                    t('campaigns.roster.invitations.expires', {
                                        date: formatExpiry(invitation),
                                    })
                                }}
                                ·
                                {{
                                    t('campaigns.roster.invitations.sends', {
                                        count: invitation.send_count,
                                    })
                                }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <BaseButton
                                variant="secondary"
                                size="sm"
                                :disabled="!invitation.can_resend"
                                :aria-disabled="processing ? 'true' : undefined"
                                class="w-full aria-disabled:cursor-not-allowed aria-disabled:opacity-50 sm:w-auto"
                                @click="resendInvitation(invitation)"
                            >
                                {{ t('campaigns.roster.invitations.resend') }}
                            </BaseButton>
                            <BaseButton
                                variant="secondary"
                                size="sm"
                                :aria-disabled="processing ? 'true' : undefined"
                                class="w-full aria-disabled:cursor-not-allowed aria-disabled:opacity-50 sm:w-auto"
                                @click="askCancel(invitation)"
                            >
                                {{ t('campaigns.roster.invitations.cancel') }}
                            </BaseButton>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Players and their attendance to the upcoming sessions -->
        <section :class="sectionClass" aria-labelledby="roster-players-title">
            <h2 id="roster-players-title" class="text-lg font-semibold text-base-primary">
                {{ t('campaigns.roster.players.title', { count: players.length }) }}
            </h2>
            <p
                v-if="players.length > 0 && sessions.length > 0"
                class="mt-1 text-sm text-base-secondary"
            >
                {{ t('campaigns.roster.footnote') }}
            </p>

            <p v-if="players.length === 0" class="mt-3 text-sm text-base-secondary">
                {{ t('campaigns.roster.players.empty') }}
            </p>

            <template v-else>
                <p v-if="sessions.length === 0" class="mt-3 text-sm text-base-secondary">
                    {{ t('campaigns.roster.players.noSessions') }}
                </p>

                <!-- A window of sessions; the players and «Quitar» stay while paging -->
                <div
                    v-if="sessions.length > pageSize"
                    class="mt-4 flex items-center justify-between gap-2"
                >
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        :disabled="!hasPrevious"
                        :aria-label="hasPrevious ? previousLabel : undefined"
                        @click="showPrevious"
                    >
                        <span aria-hidden="true">‹</span>
                        {{ t('campaigns.roster.attendance.previous') }}
                    </BaseButton>
                    <p aria-live="polite" class="text-center text-sm text-base-secondary">
                        {{ rangeLabel }}
                    </p>
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        :disabled="!hasNext"
                        :aria-label="hasNext ? nextLabel : undefined"
                        @click="showNext"
                    >
                        {{ t('campaigns.roster.attendance.next') }}
                        <span aria-hidden="true">›</span>
                    </BaseButton>
                </div>

                <!-- Scrolls inside the card on narrow screens; the player column stays -->
                <div class="-mx-4 mt-4 overflow-x-auto sm:mx-0">
                    <table class="w-full border-separate border-spacing-0 text-left text-sm">
                        <caption class="sr-only">
                            {{
                                t('campaigns.roster.attendance.caption')
                            }}
                        </caption>
                        <thead>
                            <tr>
                                <th
                                    scope="col"
                                    class="sticky left-0 z-10 w-full border-b border-default bg-surface py-2 pl-4 pr-3 font-medium text-base-secondary sm:pl-0"
                                >
                                    {{ t('campaigns.roster.players.name') }}
                                </th>
                                <th
                                    v-for="session in visibleSessions"
                                    :key="session.game_table_id"
                                    scope="col"
                                    class="border-b border-default px-1 py-2 text-center align-bottom font-medium text-base-primary sm:min-w-[8rem]"
                                >
                                    <span class="block whitespace-nowrap">{{
                                        sessionLabel(session)
                                    }}</span>
                                    <span
                                        class="block whitespace-nowrap text-xs font-normal text-base-muted"
                                    >
                                        {{
                                            t('campaigns.roster.attendance.seats', {
                                                taken: session.seats_taken,
                                                max: session.max_players,
                                            })
                                        }}
                                    </span>
                                </th>
                                <th
                                    scope="col"
                                    class="border-b border-default py-2 pl-2 pr-4 max-sm:hidden sm:pr-0"
                                >
                                    <span class="sr-only">{{
                                        t('campaigns.roster.attendance.actionsColumn')
                                    }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="player in players" :key="player.user_id">
                                <th
                                    scope="row"
                                    class="sticky left-0 z-10 w-full border-b border-default bg-surface py-2 pl-4 pr-3 align-middle font-medium text-base-primary sm:pl-0"
                                >
                                    <span class="block max-w-[10rem] break-words sm:max-w-none">{{
                                        player.name
                                    }}</span>
                                    <span
                                        v-if="player.joined_at"
                                        class="block text-xs font-normal text-base-muted"
                                    >
                                        {{
                                            t('campaigns.roster.players.since', {
                                                date: player.joined_at,
                                            })
                                        }}
                                    </span>
                                    <!-- On phones «Quitar» sits under the name, so two sessions fit without scrolling.
                                         The wrapper hides it: the core's display utilities outrank the module's variants -->
                                    <div class="mt-2 sm:hidden">
                                        <button
                                            type="button"
                                            :aria-disabled="processing ? 'true' : undefined"
                                            :aria-label="
                                                t('campaigns.roster.players.removeNamed', {
                                                    name: player.name,
                                                })
                                            "
                                            :class="dangerOutlineClass"
                                            @click="askRemove(player)"
                                        >
                                            {{ t('campaigns.roster.players.remove') }}
                                        </button>
                                    </div>
                                </th>
                                <td
                                    v-for="session in visibleSessions"
                                    :key="session.game_table_id"
                                    class="border-b border-default px-1 py-2 text-center align-middle"
                                >
                                    <button
                                        v-if="isActionable(cellOf(player, session), session)"
                                        type="button"
                                        aria-haspopup="dialog"
                                        :aria-label="
                                            cellAccessibleName(
                                                player,
                                                session,
                                                cellOf(player, session)
                                            )
                                        "
                                        :aria-busy="
                                            busyCell === cellKey(player, session)
                                                ? 'true'
                                                : undefined
                                        "
                                        :aria-disabled="processing ? 'true' : undefined"
                                        :class="[
                                            'inline-flex min-h-[44px] w-full min-w-[4.5rem] cursor-pointer items-center justify-center gap-1 rounded-md border px-2 py-1 text-xs font-medium transition-colors',
                                            'hover:border-strong focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                                            'aria-disabled:cursor-not-allowed aria-disabled:opacity-60',
                                            cellTones[stateOf(player, session)],
                                        ]"
                                        @click="askChange(player, session)"
                                    >
                                        <LoadingSpinner
                                            v-if="busyCell === cellKey(player, session)"
                                            size="sm"
                                        />
                                        <span v-else aria-hidden="true">{{
                                            stateIcons[stateOf(player, session)]
                                        }}</span>
                                        {{ stateLabel(stateOf(player, session)) }}
                                    </button>
                                    <span
                                        v-else
                                        :class="[
                                            'inline-flex min-h-[44px] w-full min-w-[4.5rem] flex-col items-center justify-center rounded-md border border-dashed px-2 py-1 text-xs font-medium',
                                            cellTones[stateOf(player, session)],
                                        ]"
                                    >
                                        <span aria-hidden="true">
                                            {{ stateIcons[stateOf(player, session)] }}
                                            {{ stateLabel(stateOf(player, session)) }}
                                        </span>
                                        <span
                                            v-if="blockedShort(cellOf(player, session), session)"
                                            aria-hidden="true"
                                            class="text-[11px] font-normal text-base-secondary"
                                        >
                                            {{ blockedShort(cellOf(player, session), session) }}
                                        </span>
                                        <span class="sr-only">{{
                                            cellAccessibleName(
                                                player,
                                                session,
                                                cellOf(player, session)
                                            )
                                        }}</span>
                                    </span>
                                </td>
                                <td
                                    class="border-b border-default py-2 pl-2 pr-4 text-right align-middle max-sm:hidden sm:pr-0"
                                >
                                    <button
                                        type="button"
                                        :aria-disabled="processing ? 'true' : undefined"
                                        :aria-label="
                                            t('campaigns.roster.players.removeNamed', {
                                                name: player.name,
                                            })
                                        "
                                        :class="dangerOutlineClass"
                                        @click="askRemove(player)"
                                    >
                                        {{ t('campaigns.roster.players.remove') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>
    </div>

    <!-- Reject a request, with an optional reason -->
    <Teleport to="body">
        <dialog
            ref="rejectDialog"
            :aria-labelledby="rejectTitleId"
            class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-lg border-0 bg-transparent p-0 backdrop:bg-black/60"
            @click="onRejectBackdropClick"
            @cancel.prevent="closeReject"
            @close="closeReject"
        >
            <form
                class="w-full rounded-lg bg-surface p-6 shadow-xl"
                @submit.prevent="confirmReject"
            >
                <h3 :id="rejectTitleId" class="text-lg font-semibold text-base-primary">
                    {{ t('campaigns.roster.requests.rejectTitle') }}
                </h3>
                <p v-if="rejecting" class="mt-2 text-base-secondary">
                    {{ rejecting.user_name }}
                </p>
                <label
                    :for="rejectReasonId"
                    class="mt-4 block text-sm font-medium text-base-secondary"
                >
                    {{ t('campaigns.roster.requests.rejectReason') }}
                </label>
                <textarea
                    :id="rejectReasonId"
                    v-model="rejectReason"
                    name="reason"
                    rows="3"
                    maxlength="500"
                    :class="inputClass"
                />
                <div class="mt-6 flex justify-end gap-3">
                    <BaseButton variant="secondary" size="sm" @click="closeReject">
                        {{ t('common.cancel') }}
                    </BaseButton>
                    <BaseButton type="submit" variant="danger" size="sm">
                        {{ t('campaigns.roster.requests.rejectConfirm') }}
                    </BaseButton>
                </div>
            </form>
        </dialog>
    </Teleport>

    <!-- Mark an absence or enrol again: the player gets an email -->
    <ConfirmDialog
        v-model="showChangeDialog"
        :title="changeTitle"
        :message="changeMessage"
        :confirm-label="changeTitle"
        :cancel-label="t('common.cancel')"
        :confirm-variant="pendingChange?.kind === 'markAbsence' ? 'danger' : 'primary'"
        @confirm="confirmChange"
    />

    <!-- Cancel an invitation -->
    <ConfirmDialog
        v-model="showCancelDialog"
        :title="t('campaigns.roster.invitations.cancelTitle')"
        :message="
            t('campaigns.roster.invitations.cancelMessage', { email: cancelling?.email ?? '' })
        "
        :confirm-label="t('campaigns.roster.invitations.cancel')"
        :cancel-label="t('common.cancel')"
        confirm-variant="danger"
        @confirm="confirmCancel"
    />

    <!-- Remove a player -->
    <ConfirmDialog
        v-model="showRemoveDialog"
        :title="t('campaigns.roster.players.removeTitle')"
        :message="removeMessage"
        :confirm-label="t('campaigns.roster.players.remove')"
        :cancel-label="t('common.cancel')"
        confirm-variant="danger"
        @confirm="confirmRemove"
    />
</template>
