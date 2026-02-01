<script setup lang="ts">
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
watch(filters, () => {
    visiblePastCount.value = INITIAL_PAST_COUNT;
}, { deep: true });

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
        <ProfileGameTablesFilters
            v-model="filters"
            :participations="allParticipations"
        />

        <!-- No results message -->
        <div
            v-if="hasActiveFilters && filteredUpcoming.length === 0 && filteredPast.length === 0"
            class="rounded-lg border border-dashed border-stone-300 bg-muted p-6 text-center dark:border-stone-600"
        >
            <svg
                class="mx-auto h-10 w-10 text-stone-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
            <p class="mt-3 text-sm text-base-muted">
                {{ t('gameTables.profile.filters.noResults') }}
            </p>
        </div>

        <template v-else>
            <!-- Upcoming section -->
            <section>
                <h2 class="mb-6 flex items-center gap-2 text-lg font-semibold text-base-primary">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-light text-xs font-bold text-primary-700 dark:text-primary-400">
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
                    class="rounded-lg border border-dashed border-stone-300 bg-muted p-6 text-center text-sm text-base-muted dark:border-stone-600"
                >
                    {{ hasActiveFilters ? t('gameTables.profile.filters.noUpcomingMatch') : t('gameTables.profile.noUpcoming') }}
                </p>
            </section>

            <!-- Past section (collapsible) -->
            <section v-if="filteredPast.length > 0" class="border-t border-default pt-8">
                <button
                    type="button"
                    class="mb-4 flex w-full items-center justify-between rounded-lg bg-muted px-4 py-3 text-left transition-colors hover:bg-stone-200 dark:hover:bg-stone-700"
                    :aria-expanded="showPastSection"
                    @click="togglePastSection"
                >
                    <span class="flex items-center gap-2 text-lg font-semibold text-base-primary">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-stone-200 text-xs font-bold text-stone-600 dark:bg-stone-700 dark:text-stone-400">
                            {{ filteredPast.length }}
                        </span>
                        {{ t('gameTables.profile.past') }}
                    </span>
                    <svg
                        :class="['h-5 w-5 text-base-muted transition-transform', showPastSection ? 'rotate-180' : '']"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-stone-300 bg-surface py-3 text-sm font-medium text-base-secondary transition-colors hover:bg-stone-50 dark:border-stone-600 dark:hover:bg-stone-700"
                        @click="loadMore"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        {{ t('gameTables.profile.loadMore', { count: remainingPastCount }) }}
                    </button>
                </div>
            </section>
        </template>
    </div>

    <!-- Empty state when no tables at all -->
    <div
        v-else
        class="rounded-lg border border-dashed border-stone-300 bg-muted p-12 text-center dark:border-stone-600"
    >
        <svg
            class="mx-auto h-12 w-12 text-stone-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
            />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-base-primary">
            {{ t('gameTables.profile.noTables') }}
        </h3>
        <p class="mt-1 text-sm text-base-muted">
            {{ t('gameTables.profile.noTablesDescription') }}
        </p>
    </div>
</template>
