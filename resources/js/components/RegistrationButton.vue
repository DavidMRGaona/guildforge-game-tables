<script setup lang="ts">
import { ref, computed, onMounted, watch, Teleport, Transition } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage, router } from '@inertiajs/vue3';
import type { GameTable } from '../types/gametables';
import type {
    ParticipantData,
    ParticipantRole,
    EligibilityResponse,
} from '../types/registration';
import { isFinalStatus } from '../types/registration';
import RegistrationStatus from './RegistrationStatus.vue';
import GuestRegistrationModal from './GuestRegistrationModal.vue';

interface Props {
    tableId?: string;
    table?: GameTable;
    /** Initial eligibility data from server (avoids API call) */
    eligibility?: EligibilityResponse | null;
    /** Initial user registration data from server (avoids API call) */
    userRegistration?: ParticipantData | null;
}

const props = defineProps<Props>();
const page = usePage();
const { t } = useI18n();

// Support both tableId prop directly or extracting from table object (for slot system)
const resolvedTableId = computed(() => props.tableId ?? props.table?.id ?? '');

// Check if initial data was provided via props (from Inertia)
const hasInitialEligibility = props.eligibility !== undefined;
const hasInitialRegistration = props.userRegistration !== undefined;

// State - initialize from props if available
const eligibility = ref<EligibilityResponse | null>(props.eligibility ?? null);
const registration = ref<ParticipantData | null>(props.userRegistration ?? null);
const loading = ref(false);
// Skip loading if initial data was provided via props
const eligibilityLoading = ref(!hasInitialEligibility);
const registrationLoading = ref(false);
const error = ref<string | null>(null);
const showRoleSelect = ref(false);
const showCancelConfirm = ref(false);
const showNotesInput = ref(false);
const showGuestModal = ref(false);
const selectedRole = ref<ParticipantRole>('player');
const notes = ref('');

// Watch for prop changes (when Inertia reloads data after mutations)
watch(
    () => props.eligibility,
    (newEligibility) => {
        if (newEligibility !== undefined) {
            eligibility.value = newEligibility;
            eligibilityLoading.value = false;
        }
    }
);

watch(
    () => props.userRegistration,
    (newRegistration) => {
        if (newRegistration !== undefined) {
            registration.value = newRegistration;
            registrationLoading.value = false;
        }
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

// Determine if component should render
const shouldRender = computed(() => {
    if (!resolvedTableId.value) return false;
    if (eligibilityLoading.value) return false;
    // If user is authenticated, wait for registration status to load
    if (isAuthenticated.value && registrationLoading.value) return false;
    return true;
});

// Registration flow conditions
const canRegister = computed(() => {
    if (!eligibility.value?.eligible) return false;
    if (registration.value && !isFinalStatus(registration.value.status)) return false;
    return true;
});

const canCancel = computed(() => {
    if (!registration.value) return false;
    return !isFinalStatus(registration.value.status);
});

const isTableFull = computed(() => spotsAvailable.value <= 0);

const canJoinWaitingList = computed(() => {
    if (!canRegister.value) return false;
    return isTableFull.value;
});

const isRegistrationClosed = computed(() => {
    if (!eligibility.value) return true;
    return !eligibility.value.eligible && eligibility.value.reason === 'registration_closed';
});

const registrationOpensAt = computed(() => {
    return eligibility.value?.canRegisterAt ?? null;
});

// Check if the table is open to everyone (allows guest registration)
const isOpenToEveryone = computed(() => {
    return tableData.value?.registrationType === 'everyone';
});

// Check if guest registration should be offered (unauthenticated + open to everyone + table is scheduled or in_progress with acceptsRegistrationsInProgress)
const canRegisterAsGuest = computed(() => {
    if (isAuthenticated.value) return false;
    if (!isOpenToEveryone.value) return false;
    // For unauthenticated users, check table status directly (eligibility is null for guests)
    const status = tableData.value?.status;
    if (status === 'scheduled') return true;
    if (status === 'in_progress' && tableData.value?.acceptsRegistrationsInProgress) return true;
    return false;
});

// Button text and styling
const buttonText = computed(() => {
    // For unauthenticated users on open tables, show guest registration option
    if (!isAuthenticated.value) {
        if (canRegisterAsGuest.value) {
            return t('guestRegistration.registerAsGuest');
        }
        return t('gameTables.registration.loginRequired');
    }

    if (registration.value && !isFinalStatus(registration.value.status)) {
        return t('gameTables.registration.cancelRegistration');
    }

    if (isRegistrationClosed.value) {
        return t('gameTables.registration.closed');
    }

    if (registrationOpensAt.value) {
        const date = new Date(registrationOpensAt.value).toLocaleDateString();
        return t('gameTables.registration.opensAt', { date });
    }

    if (!eligibility.value?.eligible) {
        return eligibility.value?.message ?? t('gameTables.registration.closed');
    }

    if (isTableFull.value) {
        return t('gameTables.registration.joinWaitingList');
    }

    return t('gameTables.registration.register');
});

const buttonDisabled = computed(() => {
    if (loading.value) return true;
    if (!isAuthenticated.value) {
        // For guest registration, button is enabled if open to everyone
        if (canRegisterAsGuest.value) return false;
        return false; // Login redirect still works
    }
    if (isRegistrationClosed.value) return true;
    if (registrationOpensAt.value) return true;
    if (!eligibility.value?.eligible && !canCancel.value) return true;
    return false;
});

// API functions
async function fetchEligibility(): Promise<void> {
    if (!resolvedTableId.value) return;

    eligibilityLoading.value = true;
    try {
        const response = await fetch(`/api/gametables/tables/${resolvedTableId.value}/eligibility`, {
            credentials: 'include',
        });
        const data = await response.json();
        // API returns data at root level, not wrapped in 'data'
        eligibility.value = data;
    } catch (e) {
        console.error('Failed to fetch eligibility:', e);
        eligibility.value = { eligible: false, reason: 'error', message: null, canRegisterAt: null };
    } finally {
        eligibilityLoading.value = false;
    }
}

async function fetchRegistration(): Promise<void> {
    if (!isAuthenticated.value || !resolvedTableId.value) return;

    registrationLoading.value = true;
    try {
        const response = await fetch(`/api/gametables/tables/${resolvedTableId.value}/registration`, {
            credentials: 'include',
        });
        const data = await response.json();
        registration.value = data.data;
    } catch (e) {
        console.error('Failed to fetch user registration:', e);
    } finally {
        registrationLoading.value = false;
    }
}

function handleRegister(role: ParticipantRole): void {
    loading.value = true;
    error.value = null;

    router.post(
        `/mesas/${resolvedTableId.value}/inscripcion`,
        {
            role,
            notes: notes.value.trim() || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
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
    loading.value = true;
    error.value = null;

    router.delete(`/mesas/${resolvedTableId.value}/inscripcion`, {
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

    if (registration.value && canCancel.value) {
        showCancelConfirm.value = true;
        return;
    }

    // Show role selection if spectators are allowed
    if (tableData.value?.maxSpectators && tableData.value.maxSpectators > 0) {
        showRoleSelect.value = true;
        return;
    }

    // Direct registration as player
    handleRegister('player');
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

onMounted(async () => {
    // Only fetch if data was not provided via props (from Inertia)
    if (!hasInitialEligibility) {
        await fetchEligibility();
    }
    if (!hasInitialRegistration) {
        await fetchRegistration();
    }
});
</script>

<template>
    <div v-if="shouldRender" class="registration-button-container">
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
                            class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-stone-800"
                        >
                            <h3 class="mb-4 text-center text-lg font-semibold text-gray-900 dark:text-stone-100">
                                {{ showNotesInput ? t('gameTables.registration.addNotes') : t('gameTables.registration.selectRole') }}
                            </h3>

                            <!-- Role buttons -->
                            <div v-if="!showNotesInput" class="space-y-3">
                                <button
                                    type="button"
                                    :disabled="isTableFull && !canJoinWaitingList"
                                    class="w-full rounded-lg px-4 py-3 text-left transition-colors"
                                    :class="{
                                        'bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/20 dark:hover:bg-amber-900/30':
                                            !isTableFull,
                                        'bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30':
                                            isTableFull && canJoinWaitingList,
                                        'cursor-not-allowed bg-gray-100 dark:bg-stone-700':
                                            isTableFull && !canJoinWaitingList,
                                    }"
                                    @click="selectRole('player')"
                                >
                                    <div class="font-medium text-gray-900 dark:text-stone-100">
                                        {{ t('gameTables.registration.registerAsPlayer') }}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-stone-400">
                                        <template v-if="!isTableFull">
                                            {{ t('gameTables.details.spotsAvailable', { spots: spotsAvailable }) }}
                                        </template>
                                        <template v-else-if="canJoinWaitingList">
                                            {{ t('gameTables.registration.joinWaitingList') }}
                                            <template v-if="waitingListCount > 0">
                                                ({{ waitingListCount }} {{ t('gameTables.participants.waitingList').toLowerCase() }})
                                            </template>
                                        </template>
                                        <template v-else>
                                            {{ t('gameTables.details.full') }}
                                        </template>
                                    </div>
                                </button>

                                <button
                                    v-if="tableData?.maxSpectators && tableData.maxSpectators > 0"
                                    type="button"
                                    :disabled="spectatorSpotsAvailable !== null && spectatorSpotsAvailable <= 0"
                                    class="w-full rounded-lg px-4 py-3 text-left transition-colors"
                                    :class="{
                                        'bg-gray-50 hover:bg-gray-100 dark:bg-stone-700/50 dark:hover:bg-stone-700':
                                            spectatorSpotsAvailable === null || spectatorSpotsAvailable > 0,
                                        'cursor-not-allowed bg-gray-100 dark:bg-stone-700':
                                            spectatorSpotsAvailable !== null && spectatorSpotsAvailable <= 0,
                                    }"
                                    @click="selectRole('spectator')"
                                >
                                    <div class="font-medium text-gray-900 dark:text-stone-100">
                                        {{ t('gameTables.registration.registerAsSpectator') }}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-stone-400">
                                        <template v-if="spectatorSpotsAvailable !== null && spectatorSpotsAvailable > 0">
                                            {{ t('gameTables.details.spotsAvailable', { spots: spectatorSpotsAvailable }) }}
                                        </template>
                                        <template v-else-if="spectatorSpotsAvailable === null">
                                            {{ t('gameTables.details.spectators') }}
                                        </template>
                                        <template v-else>
                                            {{ t('gameTables.registration.spectatorsFull') }}
                                        </template>
                                    </div>
                                </button>
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
                                        v-model="notes"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-100 dark:focus:border-amber-500"
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
                                        class="flex-1 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
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
            type="button"
            :disabled="buttonDisabled"
            class="w-full rounded-lg px-6 py-3 text-center font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
            :class="{
                'bg-amber-600 text-white hover:bg-amber-700 focus:ring-amber-500':
                    (isAuthenticated && canRegister && !isTableFull) || canRegisterAsGuest,
                'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500':
                    isAuthenticated && canRegister && isTableFull,
                'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500': canCancel,
                'bg-stone-600 text-white hover:bg-stone-700 focus:ring-stone-500 dark:bg-stone-500 dark:hover:bg-stone-400':
                    !isAuthenticated && !canRegisterAsGuest,
                'cursor-not-allowed bg-gray-300 text-gray-500 dark:bg-stone-700 dark:text-stone-500':
                    isAuthenticated && buttonDisabled && !canCancel,
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
            :table-id="resolvedTableId"
            :has-spectator-slots="(tableData?.maxSpectators ?? 0) > 0"
            @close="closeGuestModal"
            @success="onGuestSuccess"
        />
    </div>
</template>
