<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { FormCheckbox } from '@/components/form';
import { useFlashMessages } from '@/composables/useFlashMessages';
import type {
    RosterAccessValue,
    RosterPage,
    RosterPlayer,
    RosterPlayerSession,
    RosterRequest,
    RosterSession,
    RosterSessionState,
} from '../../types/gametables';

const props = defineProps<RosterPage>();

const { t } = useI18n();
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
    () => props.roster_access_options[props.campaign.roster_access] ?? props.campaign.roster_access,
);

const subtitle = computed(() => {
    const count = props.campaign.roster_count;

    return props.campaign.max_players !== null
        ? t('campaigns.roster.subtitle', { count, max: props.campaign.max_players, access: accessLabel.value })
        : t('campaigns.roster.subtitleNoMax', { count, access: accessLabel.value }, count);
});

// Settings
const accessOptions = computed(
    () => Object.entries(props.roster_access_options) as Array<[RosterAccessValue, string]>,
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
// eslint-disable-next-line no-undef
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
        visitOptions(),
    );
}

// Add a player by the email of their account
const addForm = useForm({ email: '' });
const addEmailId = useId();
const addErrorId = useId();

function addPlayer(): void {
    addForm.post(`${baseUrl.value}/jugadores`, {
        preserveScroll: true,
        // A refusal (unknown email, full roster...) also comes back as a success with an error
        // flash: keep what was typed so it can be corrected
        onSuccess: () => {
            if (!flashError.value) addForm.reset('email');
        },
    });
}

// Players and their upcoming sessions
const sessionsById = computed(
    () => new Map<string, RosterSession>(props.sessions.map((session) => [session.game_table_id, session])),
);

const chipKeys: Record<RosterSessionState, string> = {
    going: 'campaigns.roster.chips.going',
    absent: 'campaigns.roster.chips.absent',
    waiting: 'campaigns.roster.chips.waiting',
    none: 'campaigns.roster.chips.none',
};

const chipColors: Record<RosterSessionState, string> = {
    going: 'border-success/40 bg-success-light text-base-primary',
    absent: 'border-error/40 bg-error-light text-base-primary',
    waiting: 'border-warning/40 bg-warning-light text-base-primary',
    none: 'border-default bg-base-secondary/10 text-base-secondary',
};

function chipLabel(cell: RosterPlayerSession): string {
    return t(chipKeys[cell.state], { date: sessionsById.value.get(cell.game_table_id)?.date_short ?? '' });
}

function chipTitle(cell: RosterPlayerSession): string {
    return sessionsById.value.get(cell.game_table_id)?.date ?? '';
}

// «08/11 ✓ va — Jane Doe, 08/11/2026 18:00»: starts with the visible text (so voice control
// finds the chip by what it shows), then the player and the full date it leaves out
function chipAccessibleName(player: RosterPlayer, cell: RosterPlayerSession): string {
    return t('campaigns.roster.chips.accessibleName', {
        label: chipLabel(cell),
        name: player.name,
        date: chipTitle(cell),
    });
}

function toggleAbsence(player: RosterPlayer, cell: RosterPlayerSession): void {
    if (processing.value) return;

    router.post(
        `${baseUrl.value}/ausencias`,
        {
            game_table_id: cell.game_table_id,
            user_id: player.user_id,
            absent: cell.state !== 'absent',
        },
        visitOptions(),
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
        }),
    );
}

const inputClass = 'mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5 text-base-primary placeholder-base-muted hover:border-strong focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500';
const sectionClass = 'rounded-lg bg-surface p-4 shadow sm:p-6';
</script>

<template>
    <DefaultLayout>
        <div class="bg-surface shadow">
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    href="/campanas/mis-campanas"
                    class="inline-flex items-center gap-1 text-sm text-base-secondary hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
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

        <main class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
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
                        <p v-if="settingsForm.errors.accepts_new_players" class="mt-1 text-sm text-error">
                            {{ settingsForm.errors.accepts_new_players }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label :for="settingsIds.access" class="block text-sm font-medium text-base-secondary">
                                {{ t('campaigns.roster.settings.rosterAccess') }}
                            </label>
                            <select
                                :id="settingsIds.access"
                                v-model="settingsForm.roster_access"
                                name="roster_access"
                                :class="inputClass"
                            >
                                <option v-for="[value, label] in accessOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="settingsForm.errors.roster_access" class="mt-1 text-sm text-error">
                                {{ settingsForm.errors.roster_access }}
                            </p>
                        </div>

                        <div>
                            <label :for="settingsIds.max" class="block text-sm font-medium text-base-secondary">
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
                                :aria-describedby="settingsForm.errors.max_players ? `${settingsIds.maxHelp} ${settingsIds.maxError}` : settingsIds.maxHelp"
                                :class="inputClass"
                                @input="onMaxPlayersInput"
                            />
                            <p :id="settingsIds.maxHelp" class="mt-1 text-sm text-base-muted">
                                {{ t('campaigns.roster.settings.maxPlayersHelp', { count: campaign.roster_count }) }}
                            </p>
                            <p v-if="settingsForm.errors.max_players" :id="settingsIds.maxError" class="mt-1 text-sm text-error">
                                {{ settingsForm.errors.max_players }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <BaseButton type="submit" variant="primary" size="sm" :loading="settingsForm.processing">
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
                            <p v-if="request.message" class="mt-1 whitespace-pre-line break-words text-sm text-base-secondary">
                                «{{ request.message }}»
                            </p>
                            <p v-else class="mt-1 text-sm italic text-base-muted">
                                {{ t('campaigns.roster.requests.noMessage') }}
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 gap-2">
                            <BaseButton
                                variant="primary"
                                size="sm"
                                :aria-disabled="processing ? 'true' : undefined"
                                class="aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                                @click="acceptRequest(request)"
                            >
                                {{ t('campaigns.roster.requests.accept') }}
                            </BaseButton>
                            <BaseButton
                                variant="secondary"
                                size="sm"
                                :aria-disabled="processing ? 'true' : undefined"
                                class="aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                                @click="openReject(request)"
                            >
                                {{ t('campaigns.roster.requests.reject') }}
                            </BaseButton>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Add a player -->
            <section v-if="can_add" :class="sectionClass" aria-labelledby="roster-add-title">
                <h2 id="roster-add-title" class="text-lg font-semibold text-base-primary">
                    {{ t('campaigns.roster.add.title') }}
                </h2>
                <form class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start" @submit.prevent="addPlayer">
                    <div class="min-w-0 flex-1">
                        <label :for="addEmailId" class="sr-only">{{ t('campaigns.roster.add.email') }}</label>
                        <input
                            :id="addEmailId"
                            v-model="addForm.email"
                            type="email"
                            name="email"
                            required
                            maxlength="255"
                            autocomplete="off"
                            :placeholder="t('campaigns.roster.add.email')"
                            :aria-invalid="!!addForm.errors.email"
                            :aria-describedby="addForm.errors.email ? addErrorId : undefined"
                            :class="[inputClass, 'mt-0']"
                        />
                        <p v-if="addForm.errors.email" :id="addErrorId" class="mt-1 text-sm text-error">
                            {{ addForm.errors.email }}
                        </p>
                    </div>
                    <BaseButton type="submit" variant="primary" :loading="addForm.processing">
                        {{ t('campaigns.roster.add.submit') }}
                    </BaseButton>
                </form>
            </section>

            <!-- Players -->
            <section :class="sectionClass" aria-labelledby="roster-players-title">
                <h2 id="roster-players-title" class="text-lg font-semibold text-base-primary">
                    {{ t('campaigns.roster.players.title', { count: players.length }) }}
                </h2>

                <p v-if="players.length === 0" class="mt-3 text-sm text-base-secondary">
                    {{ t('campaigns.roster.players.empty') }}
                </p>

                <template v-else>
                    <p v-if="sessions.length === 0" class="mt-3 text-sm text-base-secondary">
                        {{ t('campaigns.roster.players.noSessions') }}
                    </p>

                    <!-- A table from sm up; on phones each player stacks its cells -->
                    <table class="mt-4 block w-full text-left text-sm sm:table">
                        <thead class="sr-only sm:not-sr-only">
                            <tr class="border-b border-default">
                                <th scope="col" class="py-2 pr-4 font-medium text-base-secondary">
                                    {{ t('campaigns.roster.players.name') }}
                                </th>
                                <th scope="col" class="py-2 pr-4 font-medium text-base-secondary">
                                    {{ t('campaigns.roster.players.sessions') }}
                                </th>
                                <th scope="col" class="py-2">
                                    <span class="sr-only">{{ t('campaigns.roster.players.remove') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="block sm:table-row-group">
                            <tr
                                v-for="player in players"
                                :key="player.user_id"
                                class="block border-b border-default py-3 last:border-b-0 sm:table-row sm:py-0"
                            >
                                <th
                                    scope="row"
                                    class="block break-words pb-2 font-medium text-base-primary sm:table-cell sm:py-3 sm:pr-4 sm:align-top"
                                >
                                    {{ player.name }}
                                </th>
                                <td class="block pb-2 sm:table-cell sm:py-3 sm:pr-4 sm:align-top">
                                    <div class="flex flex-wrap gap-1.5">
                                        <template v-for="cell in player.sessions" :key="cell.game_table_id">
                                            <button
                                                v-if="cell.can_toggle"
                                                type="button"
                                                :aria-pressed="cell.state !== 'absent'"
                                                :title="chipTitle(cell)"
                                                :aria-label="chipAccessibleName(player, cell)"
                                                :aria-disabled="processing ? 'true' : undefined"
                                                :class="[
                                                    'inline-flex min-h-[32px] items-center whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium transition-colors',
                                                    'hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface',
                                                    'aria-disabled:cursor-not-allowed aria-disabled:opacity-50',
                                                    chipColors[cell.state],
                                                ]"
                                                @click="toggleAbsence(player, cell)"
                                            >
                                                {{ chipLabel(cell) }}
                                            </button>
                                            <span
                                                v-else
                                                :title="chipTitle(cell)"
                                                :class="[
                                                    'inline-flex min-h-[32px] items-center whitespace-nowrap rounded-full border border-dashed px-2.5 py-1 text-xs font-medium',
                                                    chipColors[cell.state],
                                                ]"
                                            >
                                                <span aria-hidden="true">{{ chipLabel(cell) }}</span>
                                                <span class="sr-only">{{ chipAccessibleName(player, cell) }}</span>
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="block sm:table-cell sm:py-3 sm:text-right sm:align-top">
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        :aria-disabled="processing ? 'true' : undefined"
                                        class="aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                                        @click="askRemove(player)"
                                    >
                                        {{ t('campaigns.roster.players.remove') }}
                                    </BaseButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="sessions.length > 0" class="mt-4 text-sm text-base-muted">
                        {{ t('campaigns.roster.footnote') }}
                    </p>
                </template>
            </section>
        </main>

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
                    <label :for="rejectReasonId" class="mt-4 block text-sm font-medium text-base-secondary">
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

        <!-- Remove a player -->
        <ConfirmDialog
            v-model="showRemoveDialog"
            :title="t('campaigns.roster.players.removeTitle')"
            :message="t('campaigns.roster.players.removeMessage', { name: removing?.name ?? '' })"
            :confirm-label="t('campaigns.roster.players.remove')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmRemove"
        />
    </DefaultLayout>
</template>
