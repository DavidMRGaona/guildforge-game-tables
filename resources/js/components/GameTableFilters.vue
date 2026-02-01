<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { GameSystem, GameTableFilters, EventFilter } from '../types/gametables';

interface Props {
    gameSystems: GameSystem[];
    events?: EventFilter[];
    currentFilters: GameTableFilters;
    basePath: string;
}

const props = withDefaults(defineProps<Props>(), {
    events: () => [],
});

const { t } = useI18n();

const selectedSystems = ref<string[]>(props.currentFilters.systems || []);
const selectedFormat = ref<string>(props.currentFilters.format || '');
const selectedStatus = ref<string>(props.currentFilters.status || '');
const selectedEvent = ref<string>(props.currentFilters.event || '');

const formatOptions = [
    { value: '', label: t('gameTables.filters.allFormats') },
    { value: 'in_person', label: t('gameTables.formats.inPerson') },
    { value: 'online', label: t('gameTables.formats.online') },
    { value: 'hybrid', label: t('gameTables.formats.hybrid') },
];

const statusOptions = [
    { value: '', label: t('gameTables.filters.allStatuses') },
    { value: 'scheduled', label: t('gameTables.statuses.scheduled') },
    { value: 'in_progress', label: t('gameTables.statuses.inProgress') },
    { value: 'full', label: t('gameTables.statuses.full') },
    { value: 'completed', label: t('gameTables.statuses.completed') },
    { value: 'cancelled', label: t('gameTables.statuses.cancelled') },
];

function applyFilters(): void {
    const params: Record<string, string | string[]> = {};

    if (selectedSystems.value.length > 0) {
        params.systems = selectedSystems.value;
    }

    if (selectedFormat.value) {
        params.format = selectedFormat.value;
    }

    if (selectedStatus.value) {
        params.status = selectedStatus.value;
    }

    if (selectedEvent.value) {
        params.event = selectedEvent.value;
    }

    router.visit(props.basePath, {
        data: params,
        preserveState: true,
        preserveScroll: true,
    });
}

function toggleSystem(systemId: string): void {
    const index = selectedSystems.value.indexOf(systemId);
    if (index === -1) {
        selectedSystems.value = [...selectedSystems.value, systemId];
    } else {
        selectedSystems.value = selectedSystems.value.filter((id) => id !== systemId);
    }
}

function isSystemSelected(systemId: string): boolean {
    return selectedSystems.value.includes(systemId);
}

function clearFilters(): void {
    selectedSystems.value = [];
    selectedFormat.value = '';
    selectedStatus.value = '';
    selectedEvent.value = '';
    applyFilters();
}

function removeSystemFilter(systemId: string): void {
    selectedSystems.value = selectedSystems.value.filter((id) => id !== systemId);
}

function removeFormatFilter(): void {
    selectedFormat.value = '';
}

function removeStatusFilter(): void {
    selectedStatus.value = '';
}

function removeEventFilter(): void {
    selectedEvent.value = '';
}

const hasFilters = computed(
    () =>
        selectedSystems.value.length > 0 ||
        selectedFormat.value !== '' ||
        selectedStatus.value !== '' ||
        selectedEvent.value !== ''
);

const activeFilterPills = computed(() => {
    const pills: Array<{ key: string; label: string; remove: () => void }> = [];

    for (const systemId of selectedSystems.value) {
        const system = props.gameSystems.find((s) => s.id === systemId);
        if (system) {
            pills.push({
                key: `system-${systemId}`,
                label: system.name,
                remove: () => removeSystemFilter(systemId),
            });
        }
    }

    if (selectedFormat.value) {
        const format = formatOptions.find((o) => o.value === selectedFormat.value);
        if (format) {
            pills.push({
                key: 'format',
                label: format.label,
                remove: removeFormatFilter,
            });
        }
    }

    if (selectedStatus.value) {
        const status = statusOptions.find((o) => o.value === selectedStatus.value);
        if (status) {
            pills.push({
                key: 'status',
                label: status.label,
                remove: removeStatusFilter,
            });
        }
    }

    if (selectedEvent.value) {
        const event = props.events.find((e) => e.id === selectedEvent.value);
        if (event) {
            pills.push({
                key: 'event',
                label: event.title,
                remove: removeEventFilter,
            });
        }
    }

    return pills;
});

const hasEvents = computed(() => props.events.length > 0);

watch([selectedSystems, selectedFormat, selectedStatus, selectedEvent], () => {
    applyFilters();
});
</script>

<template>
    <div class="rounded-lg border border-default bg-surface p-4 shadow-sm">
        <!-- Game system chips (full width row) -->
        <div
            v-if="gameSystems.length > 0"
            role="group"
            :aria-label="t('gameTables.filters.gameSystem')"
            class="mb-4"
        >
            <span class="mb-2 block text-sm font-medium text-base-secondary">
                {{ t('gameTables.filters.gameSystem') }}
            </span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="system in gameSystems"
                    :key="system.id"
                    type="button"
                    :aria-pressed="isSystemSelected(system.id)"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                        isSystemSelected(system.id)
                            ? 'bg-primary-600 text-white ring-2 ring-primary-600 ring-offset-1 dark:ring-offset-page'
                            : 'bg-muted text-base-secondary hover:bg-neutral-200 dark:hover:bg-neutral-600',
                    ]"
                    @click="toggleSystem(system.id)"
                >
                    <svg
                        v-if="isSystemSelected(system.id)"
                        class="h-3.5 w-3.5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="3"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    {{ system.name }}
                </button>
            </div>
        </div>

        <!-- Dropdown filters row -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Format Filter -->
            <div>
                <label
                    for="format-filter"
                    class="mb-1.5 block text-sm font-medium text-base-secondary"
                >
                    {{ t('gameTables.filters.format') }}
                </label>
                <div class="relative">
                    <select
                        id="format-filter"
                        v-model="selectedFormat"
                        class="block w-full appearance-none rounded-lg border border-default bg-surface py-2 pl-3 pr-10 text-sm text-base-primary shadow-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    >
                        <option v-for="option in formatOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <svg
                        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-muted"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label
                    for="status-filter"
                    class="mb-1.5 block text-sm font-medium text-base-secondary"
                >
                    {{ t('gameTables.filters.status') }}
                </label>
                <div class="relative">
                    <select
                        id="status-filter"
                        v-model="selectedStatus"
                        class="block w-full appearance-none rounded-lg border border-default bg-surface py-2 pl-3 pr-10 text-sm text-base-primary shadow-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <svg
                        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-muted"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Event Filter (always visible) -->
            <div>
                <label
                    for="event-filter"
                    class="mb-1.5 block text-sm font-medium text-base-secondary"
                >
                    {{ t('gameTables.filters.event') }}
                </label>
                <div class="relative">
                    <select
                        id="event-filter"
                        v-model="selectedEvent"
                        :disabled="!hasEvents"
                        :class="[
                            'block w-full appearance-none rounded-lg border border-default bg-surface py-2 pl-3 pr-10 text-sm shadow-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500',
                            hasEvents
                                ? 'text-base-primary'
                                : 'cursor-not-allowed text-base-muted',
                        ]"
                    >
                        <option value="">
                            {{ hasEvents ? t('gameTables.filters.allEvents') : t('gameTables.filters.noEvents') }}
                        </option>
                        <option v-for="event in events" :key="event.id" :value="event.id">
                            {{ event.title }}
                        </option>
                    </select>
                    <svg
                        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-muted"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Active filter pills -->
        <div v-if="hasFilters" class="mt-4 flex flex-wrap items-center gap-2">
            <span
                v-for="pill in activeFilterPills"
                :key="pill.key"
                class="inline-flex items-center gap-1 rounded-full bg-primary-light px-2.5 py-1 text-xs font-medium text-primary"
            >
                {{ pill.label }}
                <button
                    type="button"
                    :aria-label="`${t('gameTables.filters.removeFilter')}: ${pill.label}`"
                    class="ml-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full transition-colors hover:bg-primary-200 dark:hover:bg-primary-800"
                    @click.prevent="pill.remove()"
                >
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>

            <button
                type="button"
                class="text-xs font-medium text-base-muted transition-colors hover:text-base-primary"
                @click="clearFilters"
            >
                {{ t('gameTables.filters.clearAll') }}
            </button>
        </div>
    </div>
</template>
