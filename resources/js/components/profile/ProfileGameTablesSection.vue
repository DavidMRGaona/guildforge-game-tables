<script setup lang="ts">
import { Box, ChevronDown, Plus, Search } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { ref, computed, watch } from 'vue';
import ProfileParticipationCard from './ProfileParticipationCard.vue';
import ProfileGameTablesFilters from './ProfileGameTablesFilters.vue';
import type {
    ProfileParticipation,
    ProfileGameTablesData,
    ProfileParticipationFilters,
} from '../../types/gametables';

interface Props {
    profileGameTables: ProfileGameTablesData | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const INITIAL_PAST_COUNT = 5;
const LOAD_MORE_COUNT = 5;

const showPastSection = ref(false);
const visiblePastCount = ref(INITIAL_PAST_COUNT);

const filters = ref<ProfileParticipationFilters>({
    statuses: [],
    roles: [],
    systems: [],
});

const allParticipations = computed<ProfileParticipation[]>(() => {
    if (!props.profileGameTables) return [];
    return [...props.profileGameTables.upcoming, ...props.profileGameTables.past];
});

function filterParticipations(items: ProfileParticipation[]): ProfileParticipation[] {
    return items.filter((p) => {
        if (filters.value.statuses.length > 0 && !filters.value.statuses.includes(p.statusKey)) {
            return false;
        }
        if (filters.value.roles.length > 0 && !filters.value.roles.includes(p.roleKey)) {
            return false;
        }
        if (filters.value.systems.length > 0 && !filters.value.systems.includes(p.gameSystemName)) {
            return false;
        }
        return true;
    });
}

const hasActiveFilters = computed(() => {
    return (
        filters.value.statuses.length > 0 ||
        filters.value.roles.length > 0 ||
        filters.value.systems.length > 0
    );
});

const upcomingTables = computed(() => props.profileGameTables?.upcoming ?? []);
const filteredUpcoming = computed(() => filterParticipations(upcomingTables.value));

const pastTables = computed(() => props.profileGameTables?.past ?? []);
const filteredPast = computed(() => filterParticipations(pastTables.value));
const visiblePastTables = computed(() => filteredPast.value.slice(0, visiblePastCount.value));
const hasMorePast = computed(() => visiblePastCount.value < filteredPast.value.length);
const remainingPastCount = computed(() => filteredPast.value.length - visiblePastCount.value);

// Reset visible count when filters change
watch(
    filters,
    () => {
        visiblePastCount.value = INITIAL_PAST_COUNT;
    },
    { deep: true }
);

function togglePastSection(): void {
    showPastSection.value = !showPastSection.value;
}

function loadMore(): void {
    visiblePastCount.value = Math.min(
        visiblePastCount.value + LOAD_MORE_COUNT,
        filteredPast.value.length
    );
}
</script>

<template>
    <div v-if="profileGameTables && profileGameTables.total > 0" class="space-y-10">
        <!-- Filters -->
        <ProfileGameTablesFilters v-model="filters" :participations="allParticipations" />

        <!-- No results message -->
        <div
            v-if="hasActiveFilters && filteredUpcoming.length === 0 && filteredPast.length === 0"
            class="rounded-lg border border-dashed border-default bg-muted p-6 text-center"
        >
            <Search class="mx-auto h-10 w-10 text-base-secondary" aria-hidden="true" />
            <p class="mt-3 text-sm text-base-muted">
                {{ t('gameTables.profile.filters.noResults') }}
            </p>
        </div>

        <template v-else>
            <!-- Upcoming section -->
            <section>
                <h2 class="mb-6 flex items-center gap-2 text-lg font-semibold text-base-primary">
                    <span
                        class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-light text-xs font-bold text-primary"
                    >
                        {{ filteredUpcoming.length }}
                    </span>
                    {{ t('gameTables.profile.upcoming') }}
                </h2>

                <div v-if="filteredUpcoming.length > 0" class="space-y-3">
                    <ProfileParticipationCard
                        v-for="participation in filteredUpcoming"
                        :key="participation.id"
                        :participation="participation"
                    />
                </div>

                <p
                    v-else
                    class="rounded-lg border border-dashed border-default bg-muted p-6 text-center text-sm text-base-secondary"
                >
                    {{
                        hasActiveFilters
                            ? t('gameTables.profile.filters.noUpcomingMatch')
                            : t('gameTables.profile.noUpcoming')
                    }}
                </p>
            </section>

            <!-- Past section (collapsible) -->
            <section v-if="filteredPast.length > 0" class="border-t border-default pt-8">
                <button
                    type="button"
                    class="mb-4 flex w-full items-center justify-between rounded-lg bg-base-secondary/10 px-4 py-3 text-left transition-colors hover:bg-base-secondary/20"
                    :aria-expanded="showPastSection"
                    @click="togglePastSection"
                >
                    <span class="flex items-center gap-2 text-lg font-semibold text-base-primary">
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-base-secondary/10 text-xs font-bold text-base-secondary"
                        >
                            {{ filteredPast.length }}
                        </span>
                        {{ t('gameTables.profile.past') }}
                    </span>
                    <ChevronDown
                        :class="[
                            'h-5 w-5 text-base-muted transition-transform',
                            showPastSection ? 'rotate-180' : '',
                        ]"
                        aria-hidden="true"
                    />
                </button>

                <div v-if="showPastSection" class="space-y-3">
                    <ProfileParticipationCard
                        v-for="participation in visiblePastTables"
                        :key="participation.id"
                        :participation="participation"
                    />

                    <!-- Load more button -->
                    <button
                        v-if="hasMorePast"
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-default bg-transparent py-3 text-sm font-medium text-base-secondary transition-colors hover:bg-base-secondary/10"
                        @click="loadMore"
                    >
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        {{ t('gameTables.profile.loadMore', { count: remainingPastCount }) }}
                    </button>
                </div>
            </section>
        </template>
    </div>

    <!-- Empty state when no tables at all -->
    <div v-else class="rounded-lg border border-dashed border-default bg-muted p-12 text-center">
        <Box class="mx-auto h-12 w-12 text-base-secondary" aria-hidden="true" />
        <h3 class="mt-4 text-lg font-medium text-base-primary">
            {{ t('gameTables.profile.noTables') }}
        </h3>
        <p class="mt-1 text-sm text-base-muted">
            {{ t('gameTables.profile.noTablesDescription') }}
        </p>
    </div>
</template>
