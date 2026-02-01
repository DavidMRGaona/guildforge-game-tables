<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { FormCheckbox, FormToggle } from '@/components/form';

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
}

interface Props {
    modelValue: GameMaster[];
    currentUser: CurrentUser;
    gdprConsent?: boolean;
    disabled?: boolean;
    error?: string | undefined;
}

const props = withDefaults(defineProps<Props>(), {
    gdprConsent: false,
    disabled: false,
    error: undefined,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: GameMaster[]): void;
    (e: 'update:gdprConsent', value: boolean): void;
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
                    phone: '',
                    custom_title: '',
                    is_name_public: true,
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
    let actualIndex = -1;
    let additionalCount = 0;
    for (let i = 0; i < props.modelValue.length; i++) {
        if (props.modelValue[i]?.role !== 'main') {
            if (additionalCount === index) {
                actualIndex = i;
                break;
            }
            additionalCount++;
        }
    }

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
    let actualIndex = -1;
    let additionalCount = 0;
    for (let i = 0; i < props.modelValue.length; i++) {
        if (props.modelValue[i]?.role !== 'main') {
            if (additionalCount === index) {
                actualIndex = i;
                break;
            }
            additionalCount++;
        }
    }

    if (actualIndex >= 0) {
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

        <!-- Main GM (current user) - Highlighted card -->
        <div
            v-if="mainGm"
            class="relative rounded-lg border border-primary-500/20 bg-primary-50 p-4 dark:bg-primary-950/30"
        >
            <!-- "Tú" badge -->
            <span
                class="absolute right-3 top-3 inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/50 dark:text-primary-300"
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
                    <div class="text-sm text-primary-600 dark:text-primary-400">
                        {{ t('gameTables.create.gameMasters.mainGmLabel') }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Custom title -->
                <div>
                    <label class="block text-sm font-medium text-base-secondary mb-1">
                        {{ t('gameTables.create.gameMasters.customTitle') }}
                    </label>
                    <input
                        type="text"
                        :value="mainGm.custom_title"
                        :placeholder="t('gameTables.create.gameMasters.customTitlePlaceholder')"
                        :disabled="disabled"
                        class="w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                        @input="updateMainGm('custom_title', ($event.target as HTMLInputElement).value)"
                    />
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-base-secondary mb-1">
                        {{ t('gameTables.create.gameMasters.phone') }}
                    </label>
                    <input
                        type="tel"
                        :value="mainGm.phone"
                        :placeholder="t('gameTables.create.gameMasters.phonePlaceholder')"
                        :disabled="disabled"
                        class="w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                        @input="updateMainGm('phone', ($event.target as HTMLInputElement).value)"
                    />
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
                        <label class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.firstName') }}
                            <span class="text-status-error">*</span>
                        </label>
                        <input
                            type="text"
                            :value="gm.first_name"
                            :disabled="disabled"
                            class="w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'first_name', ($event.target as HTMLInputElement).value)"
                        />
                    </div>

                    <!-- Last name -->
                    <div>
                        <label class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.lastName') }}
                        </label>
                        <input
                            type="text"
                            :value="gm.last_name"
                            :disabled="disabled"
                            class="w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'last_name', ($event.target as HTMLInputElement).value)"
                        />
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.email') }}
                            <span class="text-status-error">*</span>
                        </label>
                        <input
                            type="email"
                            :value="gm.email"
                            :disabled="disabled"
                            class="w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'email', ($event.target as HTMLInputElement).value)"
                        />
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.phone') }}
                        </label>
                        <input
                            type="tel"
                            :value="gm.phone"
                            :placeholder="t('gameTables.create.gameMasters.phonePlaceholder')"
                            :disabled="disabled"
                            class="w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'phone', ($event.target as HTMLInputElement).value)"
                        />
                    </div>

                    <!-- Custom title -->
                    <div>
                        <label class="block text-sm font-medium text-base-secondary mb-1">
                            {{ t('gameTables.create.gameMasters.customTitle') }}
                        </label>
                        <input
                            type="text"
                            :value="gm.custom_title"
                            :placeholder="t('gameTables.create.gameMasters.customTitlePlaceholder')"
                            :disabled="disabled"
                            class="w-full rounded-md border border-default bg-surface px-3 py-2 text-sm text-base-primary placeholder-base-muted focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                            @input="updateAdditionalGm(index, 'custom_title', ($event.target as HTMLInputElement).value)"
                        />
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
        <div v-if="showGdprConsent" class="rounded-lg border border-default bg-surface p-4">
            <FormCheckbox
                :model-value="gdprConsent"
                :disabled="disabled"
                :label="t('gameTables.create.gameMasters.gdprConsent')"
                @update:model-value="emit('update:gdprConsent', $event)"
            />
        </div>

        <!-- Add GM button (ghost variant with user icon) -->
        <button
            type="button"
            :disabled="disabled"
            class="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-default py-3 text-sm font-medium text-base-muted transition-colors hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-600 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:border-primary-700 dark:hover:bg-primary-950/30 dark:hover:text-primary-400"
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
        <p v-if="error" class="text-sm text-status-error" role="alert">{{ error }}</p>

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
