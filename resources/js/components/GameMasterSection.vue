<script setup lang="ts">
import { computed, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { FormCheckbox, FormToggle } from '@/components/form';
import { fieldStateClass } from '../utils/fieldStateClass';

interface GameMaster {
    id?: string;
    user_id?: string | null;
    first_name?: string;
    last_name?: string;
    email?: string;
    phone?: string;
    custom_title?: string;
    is_name_public: boolean;
    role: 'main' | 'co_gm';
}

interface CurrentUser {
    id: string;
    name: string;
    email: string;
    /** The author's own values as main director, from their record shared by every table they direct */
    phone?: string;
    custom_title?: string;
    is_name_public?: boolean;
}

interface Props {
    modelValue: GameMaster[];
    currentUser: CurrentUser;
    gdprConsent?: boolean;
    disabled?: boolean;
    error?: string | undefined;
    /** Errors of each director's fields (`game_masters.<i>.<field>`, `i` in the full list) and of `gm_gdpr_consent`;
     *  the page's error summary lists every one, including those with no input here (e.g. a role) */
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    gdprConsent: false,
    disabled: false,
    error: undefined,
    errors: () => ({}),
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: GameMaster[]): void;
    (e: 'update:gdprConsent', value: boolean): void;
    /** A co-director row is removed: its position in the full list, so the page can move the errors of the rows after it */
    (e: 'remove', index: number): void;
}>();

const { t } = useI18n();

// Initialize with current user as main GM if empty
watch(
    () => props.modelValue,
    (value) => {
        if (value.length === 0) {
            emit('update:modelValue', [
                {
                    user_id: props.currentUser.id,
                    first_name: props.currentUser.name.split(' ')[0] || props.currentUser.name,
                    last_name: props.currentUser.name.split(' ').slice(1).join(' ') || '',
                    email: props.currentUser.email,
                    phone: props.currentUser.phone ?? '',
                    custom_title: props.currentUser.custom_title ?? '',
                    is_name_public: props.currentUser.is_name_public ?? true,
                    role: 'main',
                },
            ]);
        }
    },
    { immediate: true }
);

// Main GM (current user, always first)
const mainGm = computed(() => props.modelValue.find((gm) => gm.role === 'main'));

// Additional GMs
const additionalGms = computed(() => props.modelValue.filter((gm) => gm.role !== 'main'));

// GDPR consent is required when there are additional GMs
const showGdprConsent = computed(() => additionalGms.value.length > 0);

// Position in the full list of the main GM and of each additional GM: the errors are keyed by it
const mainGmIndex = computed(() => props.modelValue.findIndex((gm) => gm.role === 'main'));
const additionalGmIndexes = computed(() =>
    props.modelValue.flatMap((gm, i) => (gm.role !== 'main' ? [i] : []))
);

function fullIndexOf(additionalIndex: number): number {
    return additionalGmIndexes.value[additionalIndex] ?? -1;
}

// The fields shown for each director: their errors go under the input
type GameMasterField = 'first_name' | 'last_name' | 'email' | 'phone' | 'custom_title';

function fieldName(fullIndex: number, field: GameMasterField): string {
    return `game_masters.${fullIndex}.${field}`;
}

function fieldId(fullIndex: number, field: GameMasterField): string {
    return `gm-${fullIndex}-${field}`;
}

function fieldError(fullIndex: number, field: GameMasterField): string | undefined {
    return props.errors[fieldName(fullIndex, field)];
}

function errorId(fullIndex: number, field: GameMasterField): string | undefined {
    return fieldError(fullIndex, field) ? `${fieldId(fullIndex, field)}-error` : undefined;
}

const gdprConsentError = computed(() => props.errors.gm_gdpr_consent);

// FormCheckbox (core) leaves extra attributes on its label, not on the input that takes the focus:
// the input is told it is invalid and which message describes its error
const consentWrapper = ref<HTMLElement | null>(null);

watchEffect(
    () => {
        const input = consentWrapper.value?.querySelector<HTMLInputElement>('#gm_gdpr_consent') ?? null;
        if (input === null) return;

        if (gdprConsentError.value) {
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', 'gm_gdpr_consent-error');
        } else {
            input.removeAttribute('aria-invalid');
            input.removeAttribute('aria-describedby');
        }
    },
    { flush: 'post' },
);

// Update main GM fields
function updateMainGm(field: keyof GameMaster, value: unknown): void {
    const updated = props.modelValue.map((gm) => {
        if (gm.role === 'main') {
            return { ...gm, [field]: value };
        }
        return gm;
    });
    emit('update:modelValue', updated);
}

// Add a new additional GM
function addGm(): void {
    const newGm: GameMaster = {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        custom_title: '',
        is_name_public: true,
        role: 'co_gm',
    };
    emit('update:modelValue', [...props.modelValue, newGm]);
}

// Update an additional GM
function updateAdditionalGm(index: number, field: keyof GameMaster, value: unknown): void {
    // Find the actual index in the full array (skip main GM)
    const actualIndex = fullIndexOf(index);

    if (actualIndex < 0) return;

    const updated = [...props.modelValue];
    const current = updated[actualIndex];
    if (current) {
        updated[actualIndex] = { ...current, [field]: value };
        emit('update:modelValue', updated);
    }
}

// Delete confirmation dialog state
const showDeleteDialog = ref(false);
const gmToDeleteIndex = ref<number | null>(null);

// Request removal confirmation
function requestRemoveGm(index: number): void {
    gmToDeleteIndex.value = index;
    showDeleteDialog.value = true;
}

// Actually remove the GM after confirmation
function confirmRemoveGm(): void {
    if (gmToDeleteIndex.value === null) return;

    const index = gmToDeleteIndex.value;

    // Find the actual index in the full array
    const actualIndex = fullIndexOf(index);

    if (actualIndex >= 0) {
        emit('remove', actualIndex);
        const updated = props.modelValue.filter((_, i) => i !== actualIndex);
        emit('update:modelValue', updated);
    }

    gmToDeleteIndex.value = null;
}

// Cancel removal
function cancelRemoveGm(): void {
    gmToDeleteIndex.value = null;
}
</script>

<template>
    <div class="space-y-6" role="region" :aria-label="t('gameTables.create.gameMasters.title')">
        <!-- Header -->
        <div>
            <h3 class="text-lg font-medium text-base-primary">
                {{ t('gameTables.create.gameMasters.title') }}
            </h3>
            <p class="text-sm text-base-muted mt-1">
                {{ t('gameTables.create.gameMasters.description') }}
            </p>
        </div>

        <!-- Main GM (current user) - Highlighted card: a translucent primary tint over the surface, so
             the theme's text colours read on it in both themes (no light/dark pairs) -->
        <div v-if="mainGm">
            <div
                class="relative rounded-lg border border-primary-500/30 bg-primary-500/10 p-4"
            >
                <!-- "Tú" badge -->
                <span
                    class="absolute right-3 top-3 inline-flex items-center rounded-full border border-primary-500/30 bg-primary-500/5 px-2 py-0.5 text-xs font-medium text-[color:var(--color-primary-link)]"
                >
                    {{ t('gameTables.create.gameMasters.youBadge') }}
                </span>

                <div class="flex items-center gap-3 mb-4">
                    <!-- Larger gradient icon -->
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-sm"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-base-primary">
                            {{ currentUser.name }}
                        </div>
                        <div class="text-sm text-[color:var(--color-primary-link)]">
                            {{ t('gameTables.create.gameMasters.mainGmLabel') }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Custom title -->
                    <div>
                        <label :for="fieldId(mainGmIndex, 'custom_title')" class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.customTitle') }}
                        </label>
                        <input
                            :id="fieldId(mainGmIndex, 'custom_title')"
                            :name="fieldName(mainGmIndex, 'custom_title')"
                            type="text"
                            :aria-invalid="!!fieldError(mainGmIndex, 'custom_title')"
                            :aria-describedby="errorId(mainGmIndex, 'custom_title')"
                            :value="mainGm.custom_title"
                            :placeholder="t('gameTables.create.gameMasters.customTitlePlaceholder')"
                            :disabled="disabled"
                            :class="fieldStateClass(!!fieldError(mainGmIndex, 'custom_title'))"
                            class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-1 disabled:opacity-50"
                            @input="updateMainGm('custom_title', ($event.target as HTMLInputElement).value)"
                        />
                        <p
                            v-if="fieldError(mainGmIndex, 'custom_title')"
                            :id="errorId(mainGmIndex, 'custom_title')"
                            class="mt-1 text-sm text-error"
                        >
                            {{ fieldError(mainGmIndex, 'custom_title') }}
                        </p>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label :for="fieldId(mainGmIndex, 'phone')" class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.phone') }}
                        </label>
                        <input
                            :id="fieldId(mainGmIndex, 'phone')"
                            :name="fieldName(mainGmIndex, 'phone')"
                            type="tel"
                            :aria-invalid="!!fieldError(mainGmIndex, 'phone')"
                            :aria-describedby="errorId(mainGmIndex, 'phone')"
                            :value="mainGm.phone"
                            :placeholder="t('gameTables.create.gameMasters.phonePlaceholder')"
                            :disabled="disabled"
                            :class="fieldStateClass(!!fieldError(mainGmIndex, 'phone'))"
                            class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-1 disabled:opacity-50"
                            @input="updateMainGm('phone', ($event.target as HTMLInputElement).value)"
                        />
                        <p
                            v-if="fieldError(mainGmIndex, 'phone')"
                            :id="errorId(mainGmIndex, 'phone')"
                            class="mt-1 text-sm text-error"
                        >
                            {{ fieldError(mainGmIndex, 'phone') }}
                        </p>
                    </div>

                    <!-- Name visibility -->
                    <div class="flex items-center sm:col-span-2">
                        <FormToggle
                            :model-value="mainGm.is_name_public"
                            :label="t('gameTables.create.gameMasters.showNamePublicly')"
                            :disabled="disabled"
                            @update:model-value="updateMainGm('is_name_public', $event)"
                        />
                    </div>
                </div>
            </div>
            <!-- The values live on the author's director record, shared by their tables -->
            <p class="mt-2 text-sm text-base-secondary">
                {{ t('gameTables.create.gameMasters.appliesToAllTables') }}
            </p>
        </div>

        <!-- Additional GMs -->
        <div v-if="additionalGms.length > 0" class="space-y-4">
            <h4 class="text-sm font-medium text-base-secondary">
                {{ t('gameTables.create.gameMasters.additionalGms') }}
            </h4>

            <div
                v-for="(gm, index) in additionalGms"
                :key="index"
                class="rounded-lg border border-default bg-surface p-4"
            >
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex items-center gap-2">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-base-muted"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                            />
                        </svg>
                        <span class="text-sm font-medium text-base-secondary">
                            {{ t('gameTables.create.gameMasters.cogmNumber', { n: index + 1 }) }}
                        </span>
                    </div>
                    <button
                        type="button"
                        :disabled="disabled"
                        :aria-label="t('gameTables.create.gameMasters.removeGmLabel', { n: index + 1 })"
                        class="rounded-full p-1.5 text-base-muted transition-colors hover:bg-error-light hover:text-error disabled:opacity-50"
                        @click="requestRemoveGm(index)"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- First name -->
                    <div>
                        <label :for="fieldId(fullIndexOf(index), 'first_name')" class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.firstName') }}
                            <span class="text-error">*</span>
                        </label>
                        <input
                            :id="fieldId(fullIndexOf(index), 'first_name')"
                            :name="fieldName(fullIndexOf(index), 'first_name')"
                            type="text"
                            :aria-invalid="!!fieldError(fullIndexOf(index), 'first_name')"
                            :aria-describedby="errorId(fullIndexOf(index), 'first_name')"
                            :value="gm.first_name"
                            :disabled="disabled"
                            :class="fieldStateClass(!!fieldError(fullIndexOf(index), 'first_name'))"
                            class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-1 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'first_name', ($event.target as HTMLInputElement).value)"
                        />
                        <p
                            v-if="fieldError(fullIndexOf(index), 'first_name')"
                            :id="errorId(fullIndexOf(index), 'first_name')"
                            class="mt-1 text-sm text-error"
                        >
                            {{ fieldError(fullIndexOf(index), 'first_name') }}
                        </p>
                    </div>

                    <!-- Last name -->
                    <div>
                        <label :for="fieldId(fullIndexOf(index), 'last_name')" class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.lastName') }}
                        </label>
                        <input
                            :id="fieldId(fullIndexOf(index), 'last_name')"
                            :name="fieldName(fullIndexOf(index), 'last_name')"
                            type="text"
                            :aria-invalid="!!fieldError(fullIndexOf(index), 'last_name')"
                            :aria-describedby="errorId(fullIndexOf(index), 'last_name')"
                            :value="gm.last_name"
                            :disabled="disabled"
                            :class="fieldStateClass(!!fieldError(fullIndexOf(index), 'last_name'))"
                            class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-1 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'last_name', ($event.target as HTMLInputElement).value)"
                        />
                        <p
                            v-if="fieldError(fullIndexOf(index), 'last_name')"
                            :id="errorId(fullIndexOf(index), 'last_name')"
                            class="mt-1 text-sm text-error"
                        >
                            {{ fieldError(fullIndexOf(index), 'last_name') }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label :for="fieldId(fullIndexOf(index), 'email')" class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.email') }}
                            <span class="text-error">*</span>
                        </label>
                        <input
                            :id="fieldId(fullIndexOf(index), 'email')"
                            :name="fieldName(fullIndexOf(index), 'email')"
                            type="email"
                            :aria-invalid="!!fieldError(fullIndexOf(index), 'email')"
                            :aria-describedby="errorId(fullIndexOf(index), 'email')"
                            :value="gm.email"
                            :disabled="disabled"
                            :class="fieldStateClass(!!fieldError(fullIndexOf(index), 'email'))"
                            class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-1 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'email', ($event.target as HTMLInputElement).value)"
                        />
                        <p
                            v-if="fieldError(fullIndexOf(index), 'email')"
                            :id="errorId(fullIndexOf(index), 'email')"
                            class="mt-1 text-sm text-error"
                        >
                            {{ fieldError(fullIndexOf(index), 'email') }}
                        </p>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label :for="fieldId(fullIndexOf(index), 'phone')" class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.phone') }}
                        </label>
                        <input
                            :id="fieldId(fullIndexOf(index), 'phone')"
                            :name="fieldName(fullIndexOf(index), 'phone')"
                            type="tel"
                            :aria-invalid="!!fieldError(fullIndexOf(index), 'phone')"
                            :aria-describedby="errorId(fullIndexOf(index), 'phone')"
                            :value="gm.phone"
                            :placeholder="t('gameTables.create.gameMasters.phonePlaceholder')"
                            :disabled="disabled"
                            :class="fieldStateClass(!!fieldError(fullIndexOf(index), 'phone'))"
                            class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-1 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'phone', ($event.target as HTMLInputElement).value)"
                        />
                        <p
                            v-if="fieldError(fullIndexOf(index), 'phone')"
                            :id="errorId(fullIndexOf(index), 'phone')"
                            class="mt-1 text-sm text-error"
                        >
                            {{ fieldError(fullIndexOf(index), 'phone') }}
                        </p>
                    </div>

                    <!-- Custom title -->
                    <div>
                        <label :for="fieldId(fullIndexOf(index), 'custom_title')" class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.customTitle') }}
                        </label>
                        <input
                            :id="fieldId(fullIndexOf(index), 'custom_title')"
                            :name="fieldName(fullIndexOf(index), 'custom_title')"
                            type="text"
                            :aria-invalid="!!fieldError(fullIndexOf(index), 'custom_title')"
                            :aria-describedby="errorId(fullIndexOf(index), 'custom_title')"
                            :value="gm.custom_title"
                            :placeholder="t('gameTables.create.gameMasters.customTitlePlaceholder')"
                            :disabled="disabled"
                            :class="fieldStateClass(!!fieldError(fullIndexOf(index), 'custom_title'))"
                            class="w-full rounded-md border bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:outline-none focus:ring-1 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'custom_title', ($event.target as HTMLInputElement).value)"
                        />
                        <p
                            v-if="fieldError(fullIndexOf(index), 'custom_title')"
                            :id="errorId(fullIndexOf(index), 'custom_title')"
                            class="mt-1 text-sm text-error"
                        >
                            {{ fieldError(fullIndexOf(index), 'custom_title') }}
                        </p>
                    </div>

                    <!-- Name visibility -->
                    <div class="flex items-center sm:col-span-2">
                        <FormToggle
                            :model-value="gm.is_name_public"
                            :label="t('gameTables.create.gameMasters.showNamePublicly')"
                            :disabled="disabled"
                            @update:model-value="updateAdditionalGm(index, 'is_name_public', $event)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- GDPR consent for co-GMs data -->
        <div v-if="showGdprConsent" ref="consentWrapper" class="rounded-lg border border-default bg-surface p-4">
            <FormCheckbox
                id="gm_gdpr_consent"
                name="gm_gdpr_consent"
                :model-value="gdprConsent"
                :disabled="disabled"
                :label="t('gameTables.create.gameMasters.gdprConsent')"
                @update:model-value="emit('update:gdprConsent', $event)"
            />
            <p v-if="gdprConsentError" id="gm_gdpr_consent-error" class="mt-1 text-sm text-error">
                {{ gdprConsentError }}
            </p>
        </div>

        <!-- Add GM button (ghost variant with user icon) -->
        <button
            type="button"
            :disabled="disabled"
            class="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-default py-3 text-sm font-medium text-base-secondary transition-colors hover:border-primary-500/50 hover:bg-primary-500/10 hover:text-[color:var(--color-primary-link)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
            @click="addGm"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                />
            </svg>
            {{ t('gameTables.create.gameMasters.addGmLong') }}
        </button>

        <!-- Error message -->
        <p v-if="error" class="text-sm text-error">{{ error }}</p>

        <!-- Delete confirmation dialog -->
        <ConfirmDialog
            v-model="showDeleteDialog"
            :title="t('gameTables.create.gameMasters.removeGmTitle')"
            :message="t('gameTables.create.gameMasters.removeGmMessage')"
            :confirm-label="t('gameTables.create.gameMasters.removeGmConfirm')"
            :cancel-label="t('gameTables.create.gameMasters.removeGmCancel')"
            confirm-variant="danger"
            @confirm="confirmRemoveGm"
            @cancel="cancelRemoveGm"
        />
    </div>
</template>
