<script setup lang="ts">
// The request to join a campaign, with an optional message for the direction. ConfirmDialog's look
// (the core one only takes a message, not a form) on a native modal <dialog>: the rest of the page
// is inert while it is open, Esc and the backdrop close it, and the focus goes to the message on
// open and back to the button that opened it on close.
import { computed, nextTick, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';

interface Props {
    modelValue: boolean;
    campaignTitle: string;
    /** The server's refusal of the last attempt, shown inside the dialog */
    error?: string | null;
    processing?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    error: null,
    processing: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    submit: [message: string | null];
}>();

// As RequestToJoinCampaignRequest validates it
const MAX_LENGTH = 500;

const { t } = useI18n();
const titleId = useId();
const messageId = useId();
const counterId = useId();
const errorId = useId();

const message = ref('');

const dialog = ref<HTMLDialogElement | null>(null);

const textarea = ref<HTMLTextAreaElement | null>(null);
// The button that opened the dialog, to take the focus back there
let opener: HTMLElement | null = null;

const describedBy = computed(() => (props.error ? `${counterId} ${errorId}` : counterId));

watch(
    () => props.modelValue,
    async (open) => {
        if (open) {
            opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            message.value = '';
            await nextTick();
            dialog.value?.showModal();
            textarea.value?.focus();
            return;
        }

        if (dialog.value?.open) {
            dialog.value.close();
        }
        opener?.focus();
        opener = null;
    }
);

function close(): void {
    if (props.processing) return;

    emit('update:modelValue', false);
}

function onBackdropClick(event: MouseEvent): void {
    if (event.target === dialog.value) {
        close();
    }
}

function submit(): void {
    if (props.processing) return;

    const text = message.value.trim();
    emit('submit', text === '' ? null : text);
}
</script>

<template>
    <Teleport to="body">
        <dialog
            ref="dialog"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-lg border-0 bg-transparent p-0 backdrop:bg-black/60"
            @click="onBackdropClick"
            @cancel.prevent="close"
        >
            <form class="w-full rounded-lg bg-surface p-6 shadow-xl" @submit.prevent="submit">
                <h3 :id="titleId" class="text-lg font-semibold text-base-primary">
                    {{ t('campaigns.join.dialogTitle', { title: campaignTitle }) }}
                </h3>
                <p class="mt-2 text-sm text-base-secondary">
                    {{ t('campaigns.join.requestHint') }}
                </p>

                <label :for="messageId" class="mt-4 block text-sm font-medium text-base-secondary">
                    {{ t('campaigns.join.message') }}
                </label>
                <textarea
                    :id="messageId"
                    ref="textarea"
                    v-model="message"
                    name="message"
                    rows="4"
                    :maxlength="MAX_LENGTH"
                    :disabled="processing"
                    :aria-invalid="error ? 'true' : undefined"
                    :aria-describedby="describedBy"
                    class="mt-1 block w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary placeholder-base-muted hover:border-strong focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                />
                <p :id="counterId" class="mt-1 text-right text-xs text-base-muted">
                    {{ t('campaigns.join.counter', { count: message.length, max: MAX_LENGTH }) }}
                </p>
                <p v-if="error" :id="errorId" role="alert" class="mt-2 text-sm text-error">
                    {{ error }}
                </p>

                <div class="mt-6 grid grid-cols-2 gap-3 sm:flex sm:justify-end">
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        class="w-full sm:w-auto"
                        :disabled="processing"
                        @click="close"
                    >
                        {{ t('common.cancel') }}
                    </BaseButton>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="sm"
                        class="w-full sm:w-auto"
                        :loading="processing"
                    >
                        {{ t('campaigns.join.send') }}
                    </BaseButton>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>
