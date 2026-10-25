<script setup lang="ts">
import { X } from '@lucide/vue';
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
                <span class="mb-2 block text-xs font-medium uppercase tracking-wider text-base-muted">
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
                                ? 'bg-primary-light text-base-primary'
                                : 'bg-base-secondary/10 text-base-secondary hover:bg-base-secondary/20',
                        ]"
                        @click="toggleFilter('statuses', option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <!-- Role filter -->
            <div>
                <span class="mb-2 block text-xs font-medium uppercase tracking-wider text-base-muted">
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
                                ? 'bg-primary-light text-base-primary'
                                : 'bg-base-secondary/10 text-base-secondary hover:bg-base-secondary/20',
                        ]"
                        @click="toggleFilter('roles', option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <!-- Game system filter (only show if there are multiple systems) -->
            <div v-if="systemOptions.length > 1">
                <span class="mb-2 block text-xs font-medium uppercase tracking-wider text-base-muted">
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
                                ? 'bg-primary-light text-base-primary'
                                : 'bg-base-secondary/10 text-base-secondary hover:bg-base-secondary/20',
                        ]"
                        @click="toggleFilter('systems', option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Active filters pills -->
        <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 border-t border-default pt-3">
            <span class="text-xs text-base-muted">
                {{ t('gameTables.profile.filters.active', activeFilterCount) }}:
            </span>

            <!-- Status pills -->
            <button
                v-for="status in modelValue.statuses"
                :key="`status-${status}`"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-primary-500/10 px-2 py-1 text-xs font-medium text-base-primary transition-colors hover:bg-primary-500/20"
                :aria-label="t('gameTables.filters.removeFilter')"
                @click="removeFilter('statuses', status)"
            >
                {{ getFilterLabel('statuses', status) }}
                <X class="h-3 w-3" aria-hidden="true" />
            </button>

            <!-- Role pills -->
            <button
                v-for="role in modelValue.roles"
                :key="`role-${role}`"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-primary-500/10 px-2 py-1 text-xs font-medium text-base-primary transition-colors hover:bg-primary-500/20"
                :aria-label="t('gameTables.filters.removeFilter')"
                @click="removeFilter('roles', role)"
            >
                {{ getFilterLabel('roles', role) }}
                <X class="h-3 w-3" aria-hidden="true" />
            </button>

            <!-- System pills -->
            <button
                v-for="system in modelValue.systems"
                :key="`system-${system}`"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-primary-500/10 px-2 py-1 text-xs font-medium text-base-primary transition-colors hover:bg-primary-500/20"
                :aria-label="t('gameTables.filters.removeFilter')"
                @click="removeFilter('systems', system)"
            >
                {{ getFilterLabel('systems', system) }}
                <X class="h-3 w-3" aria-hidden="true" />
            </button>

            <!-- Clear all button -->
            <button
                type="button"
                class="text-xs text-base-secondary underline transition-colors hover:no-underline"
                @click="clearAllFilters"
            >
                {{ t('gameTables.filters.clearAll') }}
            </button>
        </div>
    </div>
</template>
