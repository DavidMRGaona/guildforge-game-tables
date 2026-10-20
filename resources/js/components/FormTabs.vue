<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

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

const { t } = useI18n();

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

// «Participantes, 2 errores»: the badge is visual only, the tab's name carries the count
function tabAccessibleName(tab: FormTab): string | undefined {
    const count = getErrorCount(tab.id);

    return count > 0 ? t('gameTables.formErrors.tabWithErrors', { tab: tab.label, count }, count) : undefined;
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
                :aria-label="tabAccessibleName(tab)"
                :class="[
                    'relative flex shrink-0 items-center gap-2 px-3 py-3 text-sm font-medium transition-colors sm:px-4',
                    hasErrors(tab.id)
                        ? 'text-error'
                        : activeTab === tab.id
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

                <!-- Error badge: warning icon and count -->
                <span
                    v-if="hasErrors(tab.id)"
                    aria-hidden="true"
                    class="inline-flex items-center gap-1 rounded-full bg-error px-1.5 py-0.5 text-xs font-semibold text-base-inverted"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path
                            fill-rule="evenodd"
                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    {{ getErrorCount(tab.id) }}
                </span>

                <!-- Active indicator -->
                <span
                    v-if="activeTab === tab.id"
                    :class="['absolute inset-x-0 bottom-0 h-0.5', hasErrors(tab.id) ? 'bg-error' : 'bg-primary-500']"
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
