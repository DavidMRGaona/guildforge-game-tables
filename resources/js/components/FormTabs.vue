<script setup lang="ts">
import { computed } from 'vue';

export interface FormTab {
    id: string;
    label: string;
    icon?: string;
}

interface Props {
    tabs: FormTab[];
    modelValue: string;
    errors?: Record<string, number>;
}

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const activeTab = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});

function selectTab(tabId: string): void {
    activeTab.value = tabId;
}

function getErrorCount(tabId: string): number {
    return props.errors[tabId] ?? 0;
}

function hasErrors(tabId: string): boolean {
    return getErrorCount(tabId) > 0;
}
</script>

<template>
    <div class="w-full">
        <!-- Tab navigation -->
        <nav
            class="flex gap-1 overflow-x-auto border-b border-default bg-surface px-1 sm:px-4"
            role="tablist"
            aria-label="Form sections"
        >
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                role="tab"
                :id="`tab-${tab.id}`"
                :aria-selected="activeTab === tab.id"
                :aria-controls="`panel-${tab.id}`"
                :tabindex="activeTab === tab.id ? 0 : -1"
                :class="[
                    'relative flex shrink-0 items-center gap-2 px-3 py-3 text-sm font-medium transition-colors sm:px-4',
                    activeTab === tab.id
                        ? 'text-primary-600 dark:text-primary-400'
                        : 'text-base-muted hover:text-base-secondary',
                ]"
                @click="selectTab(tab.id)"
                @keydown.left.prevent="() => {
                    const currentIndex = tabs.findIndex(t => t.id === activeTab);
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
                    selectTab(tabs[prevIndex]?.id ?? tabs[0]?.id ?? '');
                }"
                @keydown.right.prevent="() => {
                    const currentIndex = tabs.findIndex(t => t.id === activeTab);
                    const nextIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
                    selectTab(tabs[nextIndex]?.id ?? tabs[0]?.id ?? '');
                }"
            >
                <span>{{ tab.label }}</span>

                <!-- Error badge -->
                <span
                    v-if="hasErrors(tab.id)"
                    class="inline-flex items-center justify-center rounded-full bg-error px-1.5 py-0.5 text-xs font-semibold text-white"
                    :aria-label="`${getErrorCount(tab.id)} errores`"
                >
                    {{ getErrorCount(tab.id) }}
                </span>

                <!-- Active indicator -->
                <span
                    v-if="activeTab === tab.id"
                    class="absolute inset-x-0 bottom-0 h-0.5 bg-primary-500"
                    aria-hidden="true"
                />
            </button>
        </nav>

        <!-- Tab panels -->
        <div class="mt-6">
            <div
                v-for="tab in tabs"
                :key="tab.id"
                :id="`panel-${tab.id}`"
                role="tabpanel"
                :aria-labelledby="`tab-${tab.id}`"
                :hidden="activeTab !== tab.id"
            >
                <slot :name="tab.id" />
            </div>
        </div>
    </div>
</template>
