<script lang="ts">
// Shared by every instance. The table page renders this component through a module slot, and the
// slot resolves a new async component on each Inertia visit, so a successful registration
// re-creates it: the focus owed to the registration button is handed to the next instance.
const triggerFocusOwed = new Map<string, number>(); // table id → until (ms)
const triggerFocusers = new Map<string, () => boolean>(); // table id → the live instance's focus
const TRIGGER_FOCUS_HANDOFF_MS = 5000;
</script>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link, usePage, router } from '@inertiajs/vue3';
import type { GameTable } from '../types/gametables';
import type {
    ParticipantData,
    ParticipantRole,
    EligibilityResponse,
    GuestEligibility,
} from '../types/registration';
import { isFinalStatus } from '../types/registration';
import RegistrationStatus from './RegistrationStatus.vue';
import GuestRegistrationModal from './GuestRegistrationModal.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { useVerificationGate, type VerificationGateAction } from '@/composables/useVerificationGate';

// The table page always sends these props (slot game-table-registration); the module has no API to fetch them
interface Props {
    table: GameTable;
    /** What the signed-in user can do (null for visitors) */
    eligibility: EligibilityResponse | null;
    /** What a visitor without an account can do (null for signed-in users) */
    guestEligibility: GuestEligibility | null;
    /** The signed-in user's registration (null when there is none, or for visitors) */
    userRegistration: ParticipantData | null;
}

const props = defineProps<Props>();
const page = usePage();
const { t, locale } = useI18n();
// Registering and cancelling are closed to accounts that must verify their email (guests are not)
const gate = useVerificationGate();
const verificationReasonId = useId();

const resolvedTableId = computed(() => props.table.id);
// Use slug for Inertia routes (user-facing URLs)
const resolvedTableSlug = computed(() => props.table.slug);

// State, initialized from the page props
const eligibility = ref<EligibilityResponse | null>(props.eligibility);
const registration = ref<ParticipantData | null>(props.userRegistration);
const loading = ref(false);
const error = ref<string | null>(null);
const showRoleSelect = ref(false);
const showCancelConfirm = ref(false);
const showNotesInput = ref(false);
const showGuestModal = ref(false);
const selectedRole = ref<ParticipantRole>('player');
const notes = ref('');

// Focus for the role dialog: into it on open, back to the button that opened it on close
const triggerButton = ref<HTMLButtonElement | null>(null);
const roleDialogHeading = ref<HTMLElement | null>(null);
const playerOptionButton = ref<HTMLButtonElement | null>(null);
const spectatorOptionButton = ref<HTMLButtonElement | null>(null);
const notesInput = ref<HTMLElement | null>(null);

// The chosen role's option when it can still be chosen, else the first enabled one, else the heading
function focusRoleOption(preferred: ParticipantRole | null): void {
    const options = { player: playerOptionButton.value, spectator: spectatorOptionButton.value };
    const isEnabled = (button: HTMLButtonElement | null): button is HTMLButtonElement => button !== null && !button.disabled;
    const preferredOption = preferred !== null ? options[preferred] : null;
    const target = isEnabled(preferredOption)
        ? preferredOption
        : [options.player, options.spectator].find(isEnabled);
    (target ?? roleDialogHeading.value)?.focus();
}

// Focuses this instance's registration button when it is on the page and enabled
function focusTrigger(): boolean {
    const button = triggerButton.value;
    if (button === null || !button.isConnected || button.disabled) return false;

    button.focus();
    triggerFocusOwed.delete(resolvedTableId.value);
    return true;
}

// After a successful registration: whichever instance shows the button takes the focus
function oweTriggerFocus(): void {
    triggerFocusOwed.set(resolvedTableId.value, Date.now() + TRIGGER_FOCUS_HANDOFF_MS);
    void nextTick(() => triggerFocusers.get(resolvedTableId.value)?.());
}

onMounted(async () => {
    triggerFocusers.set(resolvedTableId.value, focusTrigger);

    const until = triggerFocusOwed.get(resolvedTableId.value);
    if (until === undefined) return;
    if (Date.now() > until) {
        triggerFocusOwed.delete(resolvedTableId.value);
        return;
    }
    await nextTick();
    focusTrigger();
});

onBeforeUnmount(() => {
    if (triggerFocusers.get(resolvedTableId.value) === focusTrigger) {
        triggerFocusers.delete(resolvedTableId.value);
    }
});

// Closed while a request is running (a successful registration): the trigger comes back disabled
let restoreFocusWhenIdle = false;

watch(showRoleSelect, async (open) => {
    await nextTick();

    if (open) {
        restoreFocusWhenIdle = false;
        focusRoleOption(null);
        return;
    }

    if (loading.value) {
        restoreFocusWhenIdle = true;
        return;
    }

    // The trigger is re-rendered once the dialog closes; if another state hides it, leave focus alone
    focusTrigger();
});

// Switching steps removes the focused button: into the notes on the way in, back to the chosen
// role on «Volver»
watch(showNotesInput, async (notesStep) => {
    await nextTick();
    if (!showRoleSelect.value) return;

    if (notesStep) {
        notesInput.value?.focus();
        return;
    }

    focusRoleOption(selectedRole.value);
});

watch(loading, async (isLoading) => {
    if (isLoading || !restoreFocusWhenIdle) return;

    restoreFocusWhenIdle = false;
    await nextTick();
    if (!showRoleSelect.value) {
        focusTrigger();
    }
});

// Watch for prop changes (when Inertia reloads data after mutations)
watch(
    () => props.eligibility,
    (newEligibility) => {
        eligibility.value = newEligibility;
    }
);

watch(
    () => props.userRegistration,
    (newRegistration) => {
        registration.value = newRegistration;
    }
);

// Use Inertia's shared auth data to check authentication
const isAuthenticated = computed(() => {
    const auth = page.props.auth as { user: unknown } | undefined;
    return auth?.user !== null && auth?.user !== undefined;
});

// Computed properties for table data - use pre-calculated values from server
const tableData = computed(() => props.table);

const spotsAvailable = computed(() => tableData.value?.spotsAvailable ?? 0);

const spectatorSpotsAvailable = computed(() => {
    if (!tableData.value || !tableData.value.maxSpectators) return null;
    return tableData.value.spectatorSpotsAvailable ?? 0;
});

const waitingListCount = computed(() => {
    return tableData.value?.waitingListCount ?? 0;
});

// Registration flow conditions: the server computes each role with the rules it applies on submit
const playerEligible = computed(() => eligibility.value?.eligible === true);
const spectatorEligible = computed(() => eligibility.value?.spectator?.eligible === true);

const canRegister = computed(() => {
    if (!playerEligible.value && !spectatorEligible.value) return false;
    if (registration.value && !isFinalStatus(registration.value.status)) return false;
    return true;
});

// An active registration decides the button: closed reasons only affect who is not registered
const hasActiveRegistration = computed(() => !!registration.value && !isFinalStatus(registration.value.status));

// The server decides: an active registration on a table that has not started
const canCancel = computed(() => {
    if (!hasActiveRegistration.value) return false;
    return eligibility.value?.canCancel === true;
});

const isTableFull = computed(() => spotsAvailable.value <= 0);

// A player registration goes to the waiting list (only when the module setting is on)
const canJoinWaitingList = computed(() => {
    if (!canRegister.value) return false;
    return playerEligible.value && eligibility.value?.waitingList === true;
});

const guestJoinsWaitingList = computed(() => props.guestEligibility?.eligible === true && props.guestEligibility.waitingList);

// Player seats taken (and no waiting list) but spectator seats free: only spectators can register
const spectatorOnly = computed(() => !playerEligible.value && spectatorEligible.value);
const guestSpectatorOnly = computed(
    () => props.guestEligibility?.eligible === false && props.guestEligibility.spectator.eligible
);

// Ties the main button to the line explaining the promotion (aria-describedby)
const waitingListHelpId = computed(() => `waiting-list-help-${resolvedTableId.value}`);
const showsWaitingListHelp = computed(
    () => !canCancel.value && (canJoinWaitingList.value || (canRegisterAsGuest.value && guestJoinsWaitingList.value))
);

// The role options keep their explanation outside the button (a disabled button gets no focus)
// and tie it to the button with aria-describedby
const roleDialogTitleId = computed(() => `role-dialog-title-${resolvedTableId.value}`);
const playerOptionHelpId = computed(() => `role-player-help-${resolvedTableId.value}`);
const spectatorOptionHelpId = computed(() => `role-spectator-help-${resolvedTableId.value}`);

const waitingListHelp = computed(() => {
    return tableData.value?.autoConfirm === false
        ? t('gameTables.registration.waitingListHelpManual')
        : t('gameTables.registration.waitingListHelp');
});

// Reasons that close the registrations for everyone, whatever the seats
const CLOSED_REASONS: readonly string[] = ['registration_closed', 'table_started', 'table_ended', 'invitation_only'];

const isRegistrationClosed = computed(() => {
    if (!eligibility.value) return true;
    const reason = eligibility.value.reason;
    return !eligibility.value.eligible && reason !== null && CLOSED_REASONS.includes(reason);
});

const registrationOpensAt = computed(() => {
    return eligibility.value?.canRegisterAt ?? null;
});

// Check if the table is open to everyone (allows guest registration)
const isOpenToEveryone = computed(() => {
    return tableData.value?.registrationType === 'everyone';
});

// Guest registration is offered when the server's guest rules admit a player (seat or waiting
// list) or a spectator; the email is checked when the form is sent
const canRegisterAsGuest = computed(() => {
    if (isAuthenticated.value) return false;
    if (!isOpenToEveryone.value) return false;
    const guest = props.guestEligibility;
    if (!guest) return false;
    return guest.eligible || guest.spectator.eligible;
});

// A visitor on a table closed for everyone (not full, not members only): the server's message
// replaces the invitation to log in, since signing in would not open anything
const guestRegistrationClosed = computed(() => {
    if (isAuthenticated.value) return false;
    const guest = props.guestEligibility;
    if (!guest || guest.eligible || guest.spectator.eligible) return false;
    return guest.reason !== null && CLOSED_REASONS.includes(guest.reason);
});

// Button text and styling
const buttonText = computed(() => {
    // For unauthenticated users on open tables, show guest registration option
    if (!isAuthenticated.value) {
        if (canRegisterAsGuest.value) {
            if (guestJoinsWaitingList.value) return t('gameTables.registration.joinWaitingList');
            if (guestSpectatorOnly.value) return t('gameTables.registration.registerAsSpectator');
            return t('guestRegistration.registerAsGuest');
        }
        if (guestRegistrationClosed.value) {
            return props.guestEligibility?.message ?? t('gameTables.registration.closed');
        }
        return t('gameTables.registration.loginRequired');
    }

    if (hasActiveRegistration.value) {
        return canCancel.value
            ? t('gameTables.registration.cancelRegistration')
            : t('gameTables.registration.withdrawalClosed');
    }

    if (isRegistrationClosed.value) {
        // Say why: the time of the table or the invitation
        return eligibility.value?.reason === 'registration_closed'
            ? t('gameTables.registration.closed')
            : (eligibility.value?.message ?? t('gameTables.registration.closed'));
    }

    if (registrationOpensAt.value) {
        const date = new Date(registrationOpensAt.value).toLocaleDateString(locale.value, {
            timeZone: VENUE_TIMEZONE,
        });
        return t('gameTables.registration.opensAt', { date });
    }

    if (!canRegister.value) {
        return eligibility.value?.message ?? t('gameTables.registration.closed');
    }

    if (canJoinWaitingList.value) {
        return t('gameTables.registration.joinWaitingList');
    }

    if (spectatorOnly.value) {
        return t('gameTables.registration.registerAsSpectator');
    }

    return t('gameTables.registration.register');
});

// What the button would do for a signed-in account that must verify its email first; null when
// the gate does not apply or the button is disabled for its own reasons anyway
const verificationBlockedAction = computed<VerificationGateAction | null>(() => {
    if (!gate.blocked.value || !isAuthenticated.value) return null;
    if (hasActiveRegistration.value) return canCancel.value ? 'cancelRegistration' : null;
    if (isRegistrationClosed.value || registrationOpensAt.value) return null;
    return canRegister.value ? 'register' : null;
});
const verificationBlocked = computed(() => verificationBlockedAction.value !== null);

const triggerDescribedBy = computed(() => {
    const ids = [
        showsWaitingListHelp.value ? waitingListHelpId.value : null,
        verificationBlocked.value ? verificationReasonId : null,
    ].filter((id): id is string => id !== null);
    return ids.length > 0 ? ids.join(' ') : undefined;
});

const buttonDisabled = computed(() => {
    if (loading.value) return true;
    if (!isAuthenticated.value) {
        // For guest registration, button is enabled if open to everyone
        if (canRegisterAsGuest.value) return false;
        // Nothing to log in for when the table is closed for everyone
        return guestRegistrationClosed.value; // Otherwise the login redirect still works
    }
    if (verificationBlocked.value) return true;
    if (hasActiveRegistration.value) return !canCancel.value;
    if (isRegistrationClosed.value) return true;
    if (registrationOpensAt.value) return true;
    if (!canRegister.value && !canCancel.value) return true;
    return false;
});

function handleRegister(role: ParticipantRole): void {
    if (gate.blocked.value) return;

    loading.value = true;
    error.value = null;

    router.post(
        `/mesas/${resolvedTableSlug.value}/inscripcion`,
        {
            role,
            notes: notes.value.trim() || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: (visitedPage) => {
                // Must verify after all (e.g. changed in another tab): the server sent us to the notice
                if (gate.isVerificationRequired(visitedPage)) return;

                oweTriggerFocus();
                showRoleSelect.value = false;
                showNotesInput.value = false;
                notes.value = '';
            },
            onError: (errors) => {
                error.value = Object.values(errors)[0] as string || t('gameTables.errors.registrationClosed');
            },
            onFinish: () => {
                loading.value = false;
            },
        }
    );
}

function handleCancel(): void {
    showCancelConfirm.value = false;
    if (gate.blocked.value) return;

    loading.value = true;
    error.value = null;

    router.delete(`/mesas/${resolvedTableSlug.value}/inscripcion`, {
        preserveScroll: true,
        onError: (errors) => {
            error.value = Object.values(errors)[0] as string || t('gameTables.errors.cannotCancel');
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

function handleClick(): void {
    if (!isAuthenticated.value) {
        // If open to everyone, show guest registration modal
        if (canRegisterAsGuest.value) {
            showGuestModal.value = true;
            return;
        }
        // Otherwise redirect to login
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        return;
    }

    // The button is disabled and says why; the role dialog never opens
    if (verificationBlocked.value) return;

    // An active registration can only be cancelled: it never falls through to registering
    if (hasActiveRegistration.value) {
        if (canCancel.value) showCancelConfirm.value = true;
        return;
    }

    // Show role selection if spectators are allowed
    if (tableData.value?.maxSpectators && tableData.value.maxSpectators > 0) {
        showRoleSelect.value = true;
        return;
    }

    // Direct registration as player (a seat or the waiting list)
    if (playerEligible.value) {
        handleRegister('player');
    }
}

function closeGuestModal(): void {
    showGuestModal.value = false;
}

function onGuestSuccess(): void {
    // The page will reload with flash message via Inertia
}

function selectRole(role: ParticipantRole): void {
    selectedRole.value = role;
    showNotesInput.value = true;
}

function submitWithNotes(): void {
    handleRegister(selectedRole.value);
}

function cancelRoleSelect(): void {
    showRoleSelect.value = false;
    showNotesInput.value = false;
    notes.value = '';
}
</script>

<template>
    <div class="registration-button-container">
        <!-- Current registration status -->
        <RegistrationStatus
            v-if="registration && !isFinalStatus(registration.status)"
            :registration="registration"
            class="mb-4"
        />

        <!-- Error message -->
        <div
            v-if="error"
            class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            {{ error }}
        </div>

        <!-- Role selection modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showRoleSelect"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    @click.self="cancelRoleSelect"
                >
                    <Transition
                        enter-active-class="transition-all duration-200"
                        enter-from-class="scale-95 opacity-0"
                        enter-to-class="scale-100 opacity-100"
                        leave-active-class="transition-all duration-150"
                        leave-from-class="scale-100 opacity-100"
                        leave-to-class="scale-95 opacity-0"
                    >
                        <div
                            v-if="showRoleSelect"
                            role="dialog"
                            aria-modal="true"
                            :aria-labelledby="roleDialogTitleId"
                            class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-stone-800"
                        >
                            <h3
                                :id="roleDialogTitleId"
                                ref="roleDialogHeading"
                                tabindex="-1"
                                class="mb-4 text-center text-lg font-semibold text-gray-900 focus:outline-none dark:text-stone-100"
                            >
                                {{ showNotesInput ? t('gameTables.registration.addNotes') : t('gameTables.registration.selectRole') }}
                            </h3>

                            <!-- Role buttons -->
                            <div v-if="!showNotesInput" class="space-y-3">
                                <!-- The whole card is the button (stretched over it); the reason sits outside it -->
                                <div
                                    class="relative w-full rounded-lg px-4 py-3 text-left transition-colors"
                                    :class="{
                                        'bg-primary-light hover:bg-primary-200 dark:bg-primary-900/20 dark:hover:bg-primary-900/30':
                                            playerEligible && !canJoinWaitingList,
                                        'bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30':
                                            canJoinWaitingList,
                                        'bg-gray-100 dark:bg-stone-700': !playerEligible,
                                    }"
                                >
                                    <button
                                        ref="playerOptionButton"
                                        type="button"
                                        :disabled="!playerEligible"
                                        :aria-describedby="playerOptionHelpId"
                                        class="w-full text-left font-medium text-gray-900 before:absolute before:inset-0 before:rounded-lg focus:outline-none focus-visible:before:ring-2 focus-visible:before:ring-primary-500 disabled:cursor-not-allowed dark:text-stone-100"
                                        @click="selectRole('player')"
                                    >
                                        {{ canJoinWaitingList ? t('gameTables.registration.joinWaitingList') : t('gameTables.registration.registerAsPlayer') }}
                                    </button>
                                    <p :id="playerOptionHelpId" class="mt-1 text-sm text-gray-500 dark:text-stone-400">
                                        <template v-if="canJoinWaitingList">
                                            {{ waitingListHelp }}
                                            <template v-if="waitingListCount > 0">
                                                ({{ waitingListCount }} {{ t('gameTables.participants.waitingList').toLowerCase() }})
                                            </template>
                                        </template>
                                        <template v-else-if="playerEligible">
                                            {{ t('gameTables.details.spotsAvailable', { spots: spotsAvailable }) }}
                                        </template>
                                        <template v-else>
                                            {{ eligibility?.message ?? t('gameTables.details.full') }}
                                        </template>
                                    </p>
                                </div>

                                <div
                                    v-if="tableData?.maxSpectators && tableData.maxSpectators > 0"
                                    class="relative w-full rounded-lg px-4 py-3 text-left transition-colors"
                                    :class="{
                                        'bg-gray-50 hover:bg-gray-100 dark:bg-stone-700/50 dark:hover:bg-stone-700':
                                            spectatorEligible,
                                        'bg-gray-100 dark:bg-stone-700': !spectatorEligible,
                                    }"
                                >
                                    <button
                                        ref="spectatorOptionButton"
                                        type="button"
                                        :disabled="!spectatorEligible"
                                        :aria-describedby="spectatorOptionHelpId"
                                        class="w-full text-left font-medium text-gray-900 before:absolute before:inset-0 before:rounded-lg focus:outline-none focus-visible:before:ring-2 focus-visible:before:ring-primary-500 disabled:cursor-not-allowed dark:text-stone-100"
                                        @click="selectRole('spectator')"
                                    >
                                        {{ t('gameTables.registration.registerAsSpectator') }}
                                    </button>
                                    <p :id="spectatorOptionHelpId" class="mt-1 text-sm text-gray-500 dark:text-stone-400">
                                        <template v-if="spectatorEligible && spectatorSpotsAvailable !== null">
                                            {{ t('gameTables.details.spotsAvailable', { spots: spectatorSpotsAvailable }) }}
                                        </template>
                                        <template v-else-if="spectatorEligible">
                                            {{ t('gameTables.details.spectators') }}
                                        </template>
                                        <template v-else-if="eligibility?.spectator?.reason === 'spectators_full'">
                                            {{ t('gameTables.registration.spectatorsFull') }}
                                        </template>
                                        <template v-else>
                                            {{ eligibility?.spectator?.message ?? t('gameTables.registration.spectatorsFull') }}
                                        </template>
                                    </p>
                                </div>
                            </div>

                            <!-- Notes input -->
                            <div v-else class="space-y-4">
                                <div>
                                    <label
                                        for="registration-notes"
                                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-stone-300"
                                    >
                                        {{ t('gameTables.registration.notesLabel') }}
                                    </label>
                                    <textarea
                                        id="registration-notes"
                                        ref="notesInput"
                                        v-model="notes"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-100 dark:focus:border-primary-400"
                                        :placeholder="t('gameTables.registration.notesPlaceholder')"
                                    ></textarea>
                                </div>

                                <div class="flex gap-3">
                                    <button
                                        type="button"
                                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-stone-600 dark:text-stone-300 dark:hover:bg-stone-700"
                                        @click="showNotesInput = false"
                                    >
                                        {{ $t('common.back') }}
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="loading"
                                        class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="submitWithNotes"
                                    >
                                        <span v-if="loading" class="flex items-center justify-center">
                                            <svg
                                                class="mr-2 h-4 w-4 animate-spin"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle
                                                    class="opacity-25"
                                                    cx="12"
                                                    cy="12"
                                                    r="10"
                                                    stroke="currentColor"
                                                    stroke-width="4"
                                                ></circle>
                                                <path
                                                    class="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                ></path>
                                            </svg>
                                        </span>
                                        <span v-else>{{ $t('buttons.confirm') }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Cancel button (role selection step only) -->
                            <button
                                v-if="!showNotesInput"
                                type="button"
                                class="mt-4 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-stone-600 dark:text-stone-300 dark:hover:bg-stone-700"
                                @click="cancelRoleSelect"
                            >
                                {{ $t('common.cancel') }}
                            </button>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Main action button -->
        <button
            v-if="!showRoleSelect"
            ref="triggerButton"
            type="button"
            :disabled="buttonDisabled"
            :aria-describedby="triggerDescribedBy"
            class="w-full rounded-lg px-6 py-3 text-center font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
            :class="{
                'bg-primary text-white hover:bg-primary-700 focus:ring-primary-500':
                    (isAuthenticated && canRegister && !canJoinWaitingList && !verificationBlocked) || (canRegisterAsGuest && !guestJoinsWaitingList),
                'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500':
                    (isAuthenticated && canJoinWaitingList && !verificationBlocked) || (canRegisterAsGuest && guestJoinsWaitingList),
                'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500': canCancel && !verificationBlocked,
                'bg-stone-600 text-white hover:bg-stone-700 focus:ring-stone-500 dark:bg-stone-500 dark:hover:bg-stone-400':
                    !isAuthenticated && !canRegisterAsGuest,
                'cursor-not-allowed bg-gray-300 text-gray-500 dark:bg-stone-700 dark:text-stone-500':
                    isAuthenticated && buttonDisabled && (!canCancel || verificationBlocked),
            }"
            @click="handleClick"
        >
            <span v-if="loading" class="flex items-center justify-center">
                <svg
                    class="mr-2 h-5 w-5 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                {{ $t('common.loading') }}
            </span>
            <span v-else>{{ buttonText }}</span>
        </button>

        <!-- Why the button is disabled while the account must verify its email -->
        <p
            v-if="verificationBlockedAction"
            :id="verificationReasonId"
            class="mt-2 rounded-md bg-warning-bg p-3 text-center text-sm text-base-primary"
        >
            {{ gate.reason(verificationBlockedAction) }}
            <Link
                :href="gate.noticeUrl"
                class="font-semibold underline underline-offset-2 hover:no-underline"
            >
                {{ gate.linkLabel.value }}
                <span aria-hidden="true">→</span>
            </Link>
        </p>

        <!-- Capacity info -->
        <div
            v-if="tableData && !isRegistrationClosed"
            class="mt-2 text-center text-sm text-gray-500 dark:text-stone-400"
        >
            <template v-if="!isTableFull">
                {{ t('gameTables.details.spotsAvailable', { spots: spotsAvailable }) }}
            </template>
            <template v-else>
                {{ t('gameTables.details.full') }}
            </template>
            <template v-if="waitingListCount > 0">
                · {{ waitingListCount }} {{ t('gameTables.participants.waitingList').toLowerCase() }}
            </template>
        </div>

        <!-- How the waiting list works -->
        <p
            v-if="showsWaitingListHelp"
            :id="waitingListHelpId"
            class="mt-2 text-center text-sm text-gray-500 dark:text-stone-400"
        >
            {{ waitingListHelp }}
        </p>

        <!-- Cancel confirmation modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showCancelConfirm"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    @click.self="showCancelConfirm = false"
                >
                    <Transition
                        enter-active-class="transition-all duration-200"
                        enter-from-class="scale-95 opacity-0"
                        enter-to-class="scale-100 opacity-100"
                        leave-active-class="transition-all duration-150"
                        leave-from-class="scale-100 opacity-100"
                        leave-to-class="scale-95 opacity-0"
                    >
                        <div
                            v-if="showCancelConfirm"
                            class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-stone-800"
                        >
                            <!-- Icon -->
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                <svg
                                    class="h-6 w-6 text-red-600 dark:text-red-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>

                            <!-- Title -->
                            <h3 class="mb-2 text-center text-lg font-semibold text-gray-900 dark:text-stone-100">
                                {{ t('gameTables.registration.cancelRegistration') }}
                            </h3>

                            <!-- Message -->
                            <p class="mb-6 text-center text-sm text-gray-600 dark:text-stone-400">
                                {{ t('gameTables.registration.confirmCancel') }}
                            </p>

                            <!-- Buttons -->
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 dark:border-stone-600 dark:text-stone-300 dark:hover:bg-stone-700"
                                    @click="showCancelConfirm = false"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                    @click="handleCancel"
                                >
                                    {{ $t('buttons.confirm') }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Guest registration modal -->
        <GuestRegistrationModal
            :is-open="showGuestModal"
            :table-slug="resolvedTableSlug"
            :has-spectator-slots="(tableData?.maxSpectators ?? 0) > 0 && guestEligibility?.spectator.eligible === true"
            :player-available="guestEligibility?.eligible === true"
            :waiting-list="guestJoinsWaitingList"
            :waiting-list-help="waitingListHelp"
            @close="closeGuestModal"
            @success="onGuestSuccess"
        />
    </div>
</template>
