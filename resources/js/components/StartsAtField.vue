<script setup lang="ts">
import { ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { FormDatePicker, FormTimePicker } from '@/components/form';
import { joinWallDateTime, splitWallDateTime } from '../utils/wallDateTime';

/**
 * The start of a table: the day and the time are picked apart and sent together as the
 * site's wall time ('YYYY-MM-DDTHH:MM'), the value the server has always read. The day keeps
 * the id `starts_at`, so the error summary still takes people to it.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string;
        error?: string | undefined;
    }>(),
    {
        error: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useI18n();
const errorId = `${useId()}-error`;

const initial = splitWallDateTime(props.modelValue);
const date = ref(initial.date);
const time = ref(initial.time);
let lastEmitted: string | null = null;

// A start set from outside (a time slot, a reset) replaces both fields
watch(
    () => props.modelValue,
    (value) => {
        if (value === lastEmitted) {
            return;
        }
        const parts = splitWallDateTime(value);
        date.value = parts.date;
        time.value = parts.time;
    }
);

function send(): void {
    lastEmitted = joinWallDateTime(date.value, time.value);
    emit('update:modelValue', lastEmitted);
}

function onDate(value: string): void {
    date.value = value;
    send();
}

function onTime(value: string): void {
    time.value = value;
    send();
}
</script>

<template>
    <div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="starts_at" class="block text-sm font-medium text-base-secondary">
                    {{ t('gameTables.create.fields.startDate') }}
                    <span class="text-error">*</span>
                </label>
                <div class="mt-1">
                    <FormDatePicker
                        id="starts_at"
                        name="starts_at_date"
                        :model-value="date"
                        :error="error"
                        required
                        :aria-describedby="error ? errorId : undefined"
                        @update:model-value="onDate"
                    />
                </div>
            </div>
            <div>
                <label for="starts_at_time" class="block text-sm font-medium text-base-secondary">
                    {{ t('gameTables.create.fields.startTime') }}
                    <span class="text-error">*</span>
                </label>
                <div class="mt-1">
                    <FormTimePicker
                        id="starts_at_time"
                        name="starts_at_time"
                        :model-value="time"
                        :error="error"
                        required
                        :aria-describedby="error ? errorId : undefined"
                        @update:model-value="onTime"
                    />
                </div>
            </div>
        </div>
        <p v-if="error" :id="errorId" data-testid="starts-at-error" class="mt-1 text-sm text-error">
            {{ error }}
        </p>
    </div>
</template>
