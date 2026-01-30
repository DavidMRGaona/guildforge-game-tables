<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ProfileParticipationFilters, ProfileParticipation } from '../../types/gametables';

interface Props {
    modelValue: ProfileParticipationFilters;
    participations: ProfileParticipation[];
}

interface FilterOption {
    value: string;
    label: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: ProfileParticipationFilters];
}>();

const { t } = useI18n();

const statusOptions = computed<FilterOption[]>(() => [
    { value: 'pending', label: t('gameTables.profile.filters.statuses.pending') },
    { value: 'confirmed', label: t('gameTables.profile.filters.statuses.confirmed') },
    { value: 'waiting_list', label: t('gameTables.profile.filters.statuses.waitingList') },
]);

const roleOptions = computed<FilterOption[]>(() => [
    { value: 'player', label: t('gameTables.profile.filters.roles.player') },
    { value: 'spectator', label: t('gameTables.profile.filters.roles.spectator') },
    { value: 'game_master', label: t('gameTables.profile.filters.roles.gameMaster') },
    { value: 'co_gm', label: t('gameTables.profile.filters.roles.coGm') },
]);

const systemOptions = computed<FilterOption[]>(() => {
    const systems = new Set<string>();
    props.participations.forEach((p) => {
        if (p.gameSystemName) {
            systems.add(p.gameSystemName);
        }
    });
    return Array.from(systems)
        .sort()
        .map((name) => ({ value: name, label: name }));
});

const hasActiveFilters = computed(() => {
    return (
        props.modelValue.statuses.length > 0 ||
        props.modelValue.roles.length > 0 ||
        props.modelValue.systems.length > 0
    );
});

const activeFilterCount = computed(() => {
    return (
        props.modelValue.statuses.length +
        props.modelValue.roles.length +
        props.modelValue.systems.length
    );
});

function toggleFilter(
    filterType: 'statuses' | 'roles' | 'systems',
    value: string
): void {
    const currentValues = [...props.modelValue[filterType]];
    const index = currentValues.indexOf(value);

    if (index === -1) {
        currentValues.push(value);
    } else {
        currentValues.splice(index, 1);
    }

    emit('update:modelValue', {
        ...props.modelValue,
        [filterType]: currentValues,
    });
}

function isSelected(
    filterType: 'statuses' | 'roles' | 'systems',
    value: string
): boolean {
    return props.modelValue[filterType].includes(value);
}

function removeFilter(
    filterType: 'statuses' | 'roles' | 'systems',
    value: string
): void {
    const currentValues = props.modelValue[filterType].filter((v) => v !== value);
    emit('update:modelValue', {
        ...props.modelValue,
        [filterType]: currentValues,
    });
}

function clearAllFilters(): void {
    emit('update:modelValue', {
        statuses: [],
        roles: [],
        systems: [],
    });
}

function getFilterLabel(
    filterType: 'statuses' | 'roles' | 'systems',
    value: string
): string {
    if (filterType === 'systems') {
        return value;
    }

    const options = filterType === 'statuses' ? statusOptions.value : roleOptions.value;
    const option = options.find((o) => o.value === value);
    return option?.label ?? value;
}
</script>

<template>
    <div class="space-y-4">
        <!-- Filter sections -->
        <div class="space-y-3">
            <!-- Status filter -->
            <div>
                <span class="mb-2 block text-xs font-medium uppercase tracking-wider text-stone-500 dark:text-stone-400">
                    {{ t('gameTables.profile.filters.status') }}
                </span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in statusOptions"
                        :key="option.value"
                        type="button"
                        :class="[
                            'rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                            isSelected('statuses', option.value)
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                : 'bg-stone-100 text-stone-600 hover:bg-stone-200 dark:bg-stone-700 dark:text-stone-300 dark:hover:bg-stone-600',
                        ]"
                        @click="toggleFilter('statuses', option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <!-- Role filter -->
            <div>
                <span class="mb-2 block text-xs font-medium uppercase tracking-wider text-stone-500 dark:text-stone-400">
                    {{ t('gameTables.profile.filters.role') }}
                </span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in roleOptions"
                        :key="option.value"
                        type="button"
                        :class="[
                            'rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                            isSelected('roles', option.value)
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                : 'bg-stone-100 text-stone-600 hover:bg-stone-200 dark:bg-stone-700 dark:text-stone-300 dark:hover:bg-stone-600',
                        ]"
                        @click="toggleFilter('roles', option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <!-- Game system filter (only show if there are multiple systems) -->
            <div v-if="systemOptions.length > 1">
                <span class="mb-2 block text-xs font-medium uppercase tracking-wider text-stone-500 dark:text-stone-400">
                    {{ t('gameTables.profile.filters.gameSystem') }}
                </span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in systemOptions"
                        :key="option.value"
                        type="button"
                        :class="[
                            'rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                            isSelected('systems', option.value)
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                : 'bg-stone-100 text-stone-600 hover:bg-stone-200 dark:bg-stone-700 dark:text-stone-300 dark:hover:bg-stone-600',
                        ]"
                        @click="toggleFilter('systems', option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Active filters pills -->
        <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 border-t border-stone-200 pt-3 dark:border-stone-700">
            <span class="text-xs text-stone-500 dark:text-stone-400">
                {{ t('gameTables.profile.filters.active', activeFilterCount) }}:
            </span>

            <!-- Status pills -->
            <button
                v-for="status in modelValue.statuses"
                :key="`status-${status}`"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-amber-900/60"
                :aria-label="t('gameTables.filters.removeFilter')"
                @click="removeFilter('statuses', status)"
            >
                {{ getFilterLabel('statuses', status) }}
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Role pills -->
            <button
                v-for="role in modelValue.roles"
                :key="`role-${role}`"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-amber-900/60"
                :aria-label="t('gameTables.filters.removeFilter')"
                @click="removeFilter('roles', role)"
            >
                {{ getFilterLabel('roles', role) }}
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- System pills -->
            <button
                v-for="system in modelValue.systems"
                :key="`system-${system}`"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-amber-900/60"
                :aria-label="t('gameTables.filters.removeFilter')"
                @click="removeFilter('systems', system)"
            >
                {{ getFilterLabel('systems', system) }}
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Clear all button -->
            <button
                type="button"
                class="text-xs text-stone-500 underline transition-colors hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-300"
                @click="clearAllFilters"
            >
                {{ t('gameTables.filters.clearAll') }}
            </button>
        </div>
    </div>
</template>
