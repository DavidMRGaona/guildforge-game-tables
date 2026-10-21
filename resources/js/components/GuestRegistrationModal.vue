<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { useRoutes } from '@/composables/useRoutes';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { GuestConfirmationNotice, ParticipantRole } from '../types/registration';
import { hasGuestRegistration, rememberGuestRegistration } from '../utils/guestRegistrationCookie';

interface Props {
    isOpen: boolean;
    tableSlug: string;
    /** A spectator registration is admitted */
    hasSpectatorSlots?: boolean;
    /** A player registration is admitted (a seat or the waiting list) */
    playerAvailable?: boolean;
    /** The player registration goes to the waiting list */
    waitingList?: boolean;
    /** How the waiting list works on this table */
    waitingListHelp?: string;
    /** What the server tells the guest about the email confirmation (null when it has nothing to say) */
    confirmation?: GuestConfirmationNotice | null;
    /** ISO 8601 start of the table, for the browser hint */
    tableStartsAt: string;
}

const props = withDefaults(defineProps<Props>(), {
    hasSpectatorSlots: false,
    playerAvailable: true,
    waitingList: false,
    waitingListHelp: '',
    confirmation: null,
});

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const { t, locale } = useI18n();
const routes = useRoutes();

const confirmationNotice = computed(() => {
    const confirmation = props.confirmation;
    if (!confirmation) return null;

    if (confirmation.mode === 'hold' && confirmation.deadline) {
        const deadline = new Date(confirmation.deadline);
        if (!Number.isNaN(deadline.getTime())) {
            return t('guestRegistration.confirmationNoticeHold', {
                deadline: deadline.toLocaleString(locale.value, {
                    timeZone: VENUE_TIMEZONE,
                    day: 'numeric',
                    month: 'long',
                    hour: '2-digit',
                    minute: '2-digit',
                }),
            });
        }
    }

    return t('guestRegistration.confirmationNoticeWait');
});

// A sign-up from this browser was remembered (needs the preferences cookie): a hint, not a block
const alreadyFromBrowser = ref(false);

function checkBrowserHint(): void {
    alreadyFromBrowser.value = props.isOpen && hasGuestRegistration(props.tableSlug);
}

onMounted(() => {
    checkBrowserHint();
    window.addEventListener('guildforge:cookie-consent-changed', checkBrowserHint);
});

onBeforeUnmount(() => {
    window.removeEventListener('guildforge:cookie-consent-changed', checkBrowserHint);
});

// The role step is only needed when both roles are admitted
const choosesRole = computed(() => props.hasSpectatorSlots && props.playerAvailable);

// Player seats taken and no waiting list: the guest can only be a spectator
const spectatorOnly = computed(() => props.hasSpectatorSlots && !props.playerAvailable);

const submitText = computed(() => {
    if (choosesRole.value) return t('common.next');
    if (spectatorOnly.value) return t('gameTables.registration.registerAsSpectator');
    if (props.playerAvailable && props.waitingList) return t('gameTables.registration.joinWaitingList');
    return t('guestRegistration.submit');
});

const loading = ref(false);
const error = ref<string | null>(null);
// The email belongs to an account: the refusal comes with a link to log in
const accountError = ref(false);
const step = ref<'form' | 'role'>('form');
const selectedRole = ref<ParticipantRole>('player');

const form = ref({
    first_name: '',
    email: '',
    phone: '',
    notes: '',
    gdpr_consent: false,
});

// Reset form when modal opens
watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            form.value = {
                first_name: '',
                email: '',
                phone: '',
                notes: '',
                gdpr_consent: false,
            };
            step.value = 'form';
            selectedRole.value = 'player';
            error.value = null;
            accountError.value = false;
            checkBrowserHint();
        }
    }
);

const isFormValid = computed(() => {
    return (
        form.value.first_name.trim() !== '' &&
        form.value.email.trim() !== '' &&
        form.value.gdpr_consent
    );
});

function handleFormSubmit(): void {
    if (!isFormValid.value) return;

    // With both roles admitted, choose; otherwise submit the only one
    if (choosesRole.value) {
        step.value = 'role';
    } else {
        submitRegistration(props.playerAvailable ? 'player' : 'spectator');
    }
}

function selectRole(role: ParticipantRole): void {
    selectedRole.value = role;
    submitRegistration(role);
}

function submitRegistration(role: ParticipantRole): void {
    loading.value = true;
    error.value = null;
    accountError.value = false;

    router.post(
        `/mesas/${props.tableSlug}/inscripcion-invitado`,
        {
            first_name: form.value.first_name.trim(),
            email: form.value.email.trim(),
            phone: form.value.phone.trim() || undefined,
            notes: form.value.notes.trim() || undefined,
            role,
            gdpr_consent: form.value.gdpr_consent,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                rememberGuestRegistration(props.tableSlug, props.tableStartsAt);
                emit('success');
                emit('close');
            },
            onError: (errors) => {
                accountError.value = typeof errors.account === 'string' && errors.account !== '';
                error.value = (accountError.value ? errors.account : Object.values(errors)[0] as string) || t('guestRegistration.error');
                step.value = 'form';
            },
            onFinish: () => {
                loading.value = false;
            },
        }
    );
}

function handleClose(): void {
    if (!loading.value) {
        emit('close');
    }
}

function goBack(): void {
    step.value = 'form';
}
</script>

<template>
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
                v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="handleClose"
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
                        v-if="isOpen"
                        class="w-full max-w-md rounded-xl bg-surface p-6 shadow-2xl"
                    >
                        <!-- Header -->
                        <h3 class="mb-4 text-center text-lg font-semibold text-base-primary">
                            {{ step === 'form' ? t('guestRegistration.title') : t('gameTables.registration.selectRole') }}
                        </h3>

                        <!-- Error message -->
                        <div
                            v-if="error"
                            class="mb-4 rounded-lg bg-error-light p-3 text-sm text-base-primary"
                        >
                            {{ error }}
                            <a
                                v-if="accountError"
                                :href="routes.auth.login"
                                class="ml-1 font-medium underline"
                            >
                                {{ t('guestRegistration.logIn') }}
                            </a>
                        </div>

                        <!-- Browser hint: a sign-up from this browser already exists -->
                        <p
                            v-if="step === 'form' && alreadyFromBrowser"
                            class="mb-4 rounded-lg bg-info-light p-3 text-sm text-base-primary"
                        >
                            {{ t('guestRegistration.alreadyFromBrowser') }}
                        </p>

                        <!-- Spectator notice (when it is the only option) -->
                        <p
                            v-if="step === 'form' && spectatorOnly"
                            class="mb-4 rounded-lg bg-info-light p-3 text-sm text-base-primary"
                        >
                            {{ t('gameTables.registration.spectatorOnly') }}
                        </p>

                        <!-- Waiting list notice (when it is the only option) -->
                        <p
                            v-if="step === 'form' && waitingList && playerAvailable && !choosesRole && waitingListHelp"
                            class="mb-4 rounded-lg bg-info-light p-3 text-sm text-base-primary"
                        >
                            {{ waitingListHelp }}
                        </p>

                        <!-- Form step -->
                        <form v-if="step === 'form'" class="space-y-4" @submit.prevent="handleFormSubmit">
                            <!-- Name -->
                            <div>
                                <label
                                    for="guest-first-name"
                                    class="mb-1 block text-sm font-medium text-base-secondary"
                                >
                                    {{ t('guestRegistration.firstName') }} *
                                </label>
                                <input
                                    id="guest-first-name"
                                    v-model="form.first_name"
                                    type="text"
                                    required
                                    class="w-full rounded-lg border border-strong bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 text-base-primary"
                                    :placeholder="t('guestRegistration.firstNamePlaceholder')"
                                />
                            </div>

                            <!-- Email -->
                            <div>
                                <label
                                    for="guest-email"
                                    class="mb-1 block text-sm font-medium text-base-secondary"
                                >
                                    {{ t('guestRegistration.email') }} *
                                </label>
                                <input
                                    id="guest-email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="w-full rounded-lg border border-strong bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 text-base-primary"
                                    :placeholder="t('guestRegistration.emailPlaceholder')"
                                />
                            </div>

                            <!-- Phone -->
                            <div>
                                <label
                                    for="guest-phone"
                                    class="mb-1 block text-sm font-medium text-base-secondary"
                                >
                                    {{ t('guestRegistration.phone') }}
                                </label>
                                <input
                                    id="guest-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    class="w-full rounded-lg border border-strong bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 text-base-primary"
                                    :placeholder="t('guestRegistration.phonePlaceholder')"
                                />
                            </div>

                            <!-- Notes -->
                            <div>
                                <label
                                    for="guest-notes"
                                    class="mb-1 block text-sm font-medium text-base-secondary"
                                >
                                    {{ t('gameTables.registration.notesLabel') }}
                                </label>
                                <textarea
                                    id="guest-notes"
                                    v-model="form.notes"
                                    rows="2"
                                    class="w-full rounded-lg border border-strong bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 text-base-primary"
                                    :placeholder="t('gameTables.registration.notesPlaceholder')"
                                ></textarea>
                            </div>

                            <!-- GDPR Consent -->
                            <div class="flex items-start gap-2">
                                <input
                                    id="guest-gdpr"
                                    v-model="form.gdpr_consent"
                                    type="checkbox"
                                    required
                                    class="mt-1 h-4 w-4 rounded border-strong bg-surface text-primary focus:ring-primary-500"
                                />
                                <label
                                    for="guest-gdpr"
                                    class="text-sm text-base-secondary"
                                >
                                    {{ t('guestRegistration.gdprConsent') }}
                                    <a
                                        href="/privacidad"
                                        target="_blank"
                                        class="text-primary underline"
                                    >
                                        {{ t('guestRegistration.privacyPolicy') }}
                                    </a>
                                </label>
                            </div>

                            <!-- What happens after sending -->
                            <p
                                v-if="confirmationNotice"
                                class="rounded-lg bg-muted p-3 text-sm text-base-secondary"
                            >
                                {{ confirmationNotice }}
                            </p>

                            <!-- Buttons -->
                            <div class="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-default px-4 py-2.5 text-sm font-medium text-base-secondary transition-colors hover:bg-muted"
                                    @click="handleClose"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="!isFormValid || loading"
                                    class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
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
                                    <span v-else>{{ submitText }}</span>
                                </button>
                            </div>
                        </form>

                        <!-- Role selection step -->
                        <div v-else-if="step === 'role'" class="space-y-3">
                            <button
                                type="button"
                                :disabled="loading"
                                class="w-full rounded-lg bg-primary-500/10 px-4 py-3 text-left transition-colors hover:bg-primary-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="selectRole('player')"
                            >
                                <div class="font-medium text-base-primary">
                                    {{ waitingList ? t('gameTables.registration.joinWaitingList') : t('gameTables.registration.registerAsPlayer') }}
                                </div>
                                <div v-if="waitingList && waitingListHelp" class="mt-1 text-sm text-base-secondary">
                                    {{ waitingListHelp }}
                                </div>
                            </button>

                            <button
                                type="button"
                                :disabled="loading"
                                class="w-full rounded-lg bg-base-secondary/10 px-4 py-3 text-left transition-colors hover:bg-base-secondary/20 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="selectRole('spectator')"
                            >
                                <div class="font-medium text-base-primary">
                                    {{ t('gameTables.registration.registerAsSpectator') }}
                                </div>
                            </button>

                            <button
                                type="button"
                                :disabled="loading"
                                class="mt-2 w-full rounded-lg border border-default px-4 py-2.5 text-sm font-medium text-base-secondary transition-colors hover:bg-muted"
                                @click="goBack"
                            >
                                {{ $t('common.back') }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
