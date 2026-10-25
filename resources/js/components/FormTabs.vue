<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
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

const tabButtons = ref<HTMLButtonElement[]>([]);

function setTabButton(element: unknown, index: number): void {
    if (element instanceof HTMLButtonElement) {
        tabButtons.value[index] = element;
    }
}

// ARIA tabs pattern: only the selected tab is in the tab order, so the focus moves with the selection
function onKeydown(event: KeyboardEvent, index: number): void {
    const last = props.tabs.length - 1;
    const targets: Record<string, number> = {
        ArrowLeft: index > 0 ? index - 1 : last,
        ArrowRight: index < last ? index + 1 : 0,
        Home: 0,
        End: last,
    };
    const target = targets[event.key];

    const tab = target === undefined ? undefined : props.tabs[target];
    if (target === undefined || tab === undefined) {
        return;
    }

    event.preventDefault();
    selectTab(tab.id);
    void nextTick(() => tabButtons.value[target]?.focus());
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
            :aria-label="t('gameTables.formTabs.label')"
        >
            <button
                v-for="(tab, index) in tabs"
                :key="tab.id"
                :ref="(element) => setTabButton(element, index)"
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
                          ? 'text-[color:var(--color-primary-link)]'
                          : 'text-[color:var(--color-text-secondary)] hover:text-[color:var(--color-text-primary)]',
                ]"
                @click="selectTab(tab.id)"
                @keydown="onKeydown($event, index)"
            >
                <span>{{ tab.label }}</span>

                <!-- Error badge: warning icon and count -->
                <span
                    v-if="hasErrors(tab.id)"
                    aria-hidden="true"
                    class="inline-flex items-center gap-1 rounded-full bg-error px-1.5 py-0.5 text-xs font-semibold text-base-inverted"
                >
                    <TriangleAlert class="h-3.5 w-3.5" aria-hidden="true" />
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
