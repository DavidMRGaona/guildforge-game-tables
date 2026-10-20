<script setup lang="ts">
import { ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormErrorItem } from '../composables/useFormErrorSummary';

defineProps<{
    items: FormErrorItem[];
}>();

const emit = defineEmits<{
    select: [item: FormErrorItem];
}>();

const { t } = useI18n();

const titleId = useId();
const root = ref<HTMLElement | null>(null);

// After a failed save the page moves the focus here, so the summary is read first. That focus is
// the announcement: the summary is a region named by its title, not an alert (it would be read twice)
function focus(): void {
    root.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    root.value?.focus({ preventScroll: true });
}

defineExpose({ focus });
</script>

<template>
    <section
        v-if="items.length > 0"
        ref="root"
        tabindex="-1"
        :aria-labelledby="titleId"
        class="mb-6 rounded-lg border border-l-4 border-error/40 border-l-error bg-surface p-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-error"
    >
        <div class="flex gap-3">
            <svg
                class="h-5 w-5 shrink-0 text-error"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                    clip-rule="evenodd"
                />
            </svg>
            <div class="min-w-0 flex-1">
                <h2 :id="titleId" class="text-sm font-semibold text-error">
                    {{ t('gameTables.formErrors.title') }}
                </h2>
                <p class="mt-1 text-sm text-base-primary">
                    {{ t('gameTables.formErrors.intro', { count: items.length }, items.length) }}
                </p>
                <ul class="mt-2 space-y-1">
                    <li v-for="item in items" :key="item.key">
                        <button
                            type="button"
                            class="group w-full rounded-md px-2 py-1.5 text-left transition-colors hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-error"
                            @click="emit('select', item)"
                        >
                            <span class="block text-sm font-medium text-error underline-offset-2 group-hover:underline">
                                {{ item.tabLabel }} › {{ item.label }}
                            </span>
                            <span class="block text-sm text-base-secondary">
                                {{ item.message }}
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
