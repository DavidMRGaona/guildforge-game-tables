<script setup lang="ts">
import { ref, computed, watch, Teleport, Transition } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import type { ParticipantRole } from '../types/registration';

interface Props {
    isOpen: boolean;
    tableId: string;
    hasSpectatorSlots?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    hasSpectatorSlots: false,
});

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const { t } = useI18n();

const loading = ref(false);
const error = ref<string | null>(null);
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

    // If spectator slots are available, show role selection
    if (props.hasSpectatorSlots) {
        step.value = 'role';
    } else {
        // Otherwise, submit directly as player
        submitRegistration('player');
    }
}

function selectRole(role: ParticipantRole): void {
    selectedRole.value = role;
    submitRegistration(role);
}

function submitRegistration(role: ParticipantRole): void {
    loading.value = true;
    error.value = null;

    router.post(
        `/mesas/${props.tableId}/inscripcion-invitado`,
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
                emit('success');
                emit('close');
            },
            onError: (errors) => {
                error.value = Object.values(errors)[0] as string || t('guestRegistration.error');
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
                        class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-stone-800"
                    >
                        <!-- Header -->
                        <h3 class="mb-4 text-center text-lg font-semibold text-gray-900 dark:text-stone-100">
                            {{ step === 'form' ? t('guestRegistration.title') : t('gameTables.registration.selectRole') }}
                        </h3>

                        <!-- Error message -->
                        <div
                            v-if="error"
                            class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400"
                        >
                            {{ error }}
                        </div>

                        <!-- Form step -->
                        <form v-if="step === 'form'" class="space-y-4" @submit.prevent="handleFormSubmit">
                            <!-- Name -->
                            <div>
                                <label
                                    for="guest-first-name"
                                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-stone-300"
                                >
                                    {{ t('guestRegistration.firstName') }} *
                                </label>
                                <input
                                    id="guest-first-name"
                                    v-model="form.first_name"
                                    type="text"
                                    required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-100 dark:focus:border-amber-500"
                                    :placeholder="t('guestRegistration.firstNamePlaceholder')"
                                />
                            </div>

                            <!-- Email -->
                            <div>
                                <label
                                    for="guest-email"
                                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-stone-300"
                                >
                                    {{ t('guestRegistration.email') }} *
                                </label>
                                <input
                                    id="guest-email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-100 dark:focus:border-amber-500"
                                    :placeholder="t('guestRegistration.emailPlaceholder')"
                                />
                            </div>

                            <!-- Phone -->
                            <div>
                                <label
                                    for="guest-phone"
                                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-stone-300"
                                >
                                    {{ t('guestRegistration.phone') }}
                                </label>
                                <input
                                    id="guest-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-100 dark:focus:border-amber-500"
                                    :placeholder="t('guestRegistration.phonePlaceholder')"
                                />
                            </div>

                            <!-- Notes -->
                            <div>
                                <label
                                    for="guest-notes"
                                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-stone-300"
                                >
                                    {{ t('gameTables.registration.notesLabel') }}
                                </label>
                                <textarea
                                    id="guest-notes"
                                    v-model="form.notes"
                                    rows="2"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-stone-600 dark:bg-stone-700 dark:text-stone-100 dark:focus:border-amber-500"
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
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-stone-600 dark:bg-stone-700"
                                />
                                <label
                                    for="guest-gdpr"
                                    class="text-sm text-gray-600 dark:text-stone-400"
                                >
                                    {{ t('guestRegistration.gdprConsent') }}
                                    <a
                                        href="/privacidad"
                                        target="_blank"
                                        class="text-amber-600 underline hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                                    >
                                        {{ t('guestRegistration.privacyPolicy') }}
                                    </a>
                                </label>
                            </div>

                            <!-- Buttons -->
                            <div class="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-stone-600 dark:text-stone-300 dark:hover:bg-stone-700"
                                    @click="handleClose"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="!isFormValid || loading"
                                    class="flex-1 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
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
                                    <span v-else>{{ hasSpectatorSlots ? $t('common.next') : t('guestRegistration.submit') }}</span>
                                </button>
                            </div>
                        </form>

                        <!-- Role selection step -->
                        <div v-else-if="step === 'role'" class="space-y-3">
                            <button
                                type="button"
                                :disabled="loading"
                                class="w-full rounded-lg bg-amber-50 px-4 py-3 text-left transition-colors hover:bg-amber-100 dark:bg-amber-900/20 dark:hover:bg-amber-900/30 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="selectRole('player')"
                            >
                                <div class="font-medium text-gray-900 dark:text-stone-100">
                                    {{ t('gameTables.registration.registerAsPlayer') }}
                                </div>
                            </button>

                            <button
                                type="button"
                                :disabled="loading"
                                class="w-full rounded-lg bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 dark:bg-stone-700/50 dark:hover:bg-stone-700 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="selectRole('spectator')"
                            >
                                <div class="font-medium text-gray-900 dark:text-stone-100">
                                    {{ t('gameTables.registration.registerAsSpectator') }}
                                </div>
                            </button>

                            <button
                                type="button"
                                :disabled="loading"
                                class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-stone-600 dark:text-stone-300 dark:hover:bg-stone-700"
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
