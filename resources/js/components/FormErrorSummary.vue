<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
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
            <TriangleAlert class="h-5 w-5 shrink-0 text-error" aria-hidden="true" />
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
