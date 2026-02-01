<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import ProfileCreatedTableCard from './ProfileCreatedTableCard.vue';
import type { ProfileCreatedTablesData, ProfileCreatedTable } from '../../types/gametables';

interface Props {
    profileCreatedTables: ProfileCreatedTablesData | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const showDraftsSection = ref(true);

const publishedTables = computed<ProfileCreatedTable[]>(() => props.profileCreatedTables?.tables ?? []);
const draftTables = computed<ProfileCreatedTable[]>(() => props.profileCreatedTables?.drafts ?? []);
const totalTables = computed(() => props.profileCreatedTables?.total ?? 0);

const createUrl = '/mesas/crear';

function toggleDraftsSection(): void {
    showDraftsSection.value = !showDraftsSection.value;
}
</script>

<template>
    <div v-if="profileCreatedTables && totalTables > 0" class="space-y-6">
        <!-- Published tables section -->
        <section>
            <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-stone-900 dark:text-stone-100">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    {{ publishedTables.length }}
                </span>
                {{ t('gameTables.profile.created.published') }}
            </h2>

            <div v-if="publishedTables.length > 0" class="space-y-3">
                <ProfileCreatedTableCard
                    v-for="table in publishedTables"
                    :key="table.id"
                    :table="table"
                />
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-6 text-center text-sm text-stone-500 dark:border-stone-600 dark:bg-stone-800/50 dark:text-stone-400"
            >
                {{ t('gameTables.profile.created.noPublished') }}
            </p>
        </section>

        <!-- Drafts section (collapsible) -->
        <section v-if="draftTables.length > 0">
            <button
                type="button"
                class="mb-4 flex w-full items-center justify-between rounded-lg bg-stone-100 px-4 py-3 text-left transition-colors hover:bg-stone-200 dark:bg-stone-800 dark:hover:bg-stone-700"
                :aria-expanded="showDraftsSection"
                @click="toggleDraftsSection"
            >
                <span class="flex items-center gap-2 text-lg font-semibold text-stone-900 dark:text-stone-100">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-yellow-100 text-xs font-bold text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                        {{ draftTables.length }}
                    </span>
                    {{ t('gameTables.profile.created.drafts') }}
                </span>
                <svg
                    :class="['h-5 w-5 text-stone-500 transition-transform', showDraftsSection ? 'rotate-180' : '']"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-if="showDraftsSection" class="space-y-3">
                <ProfileCreatedTableCard
                    v-for="table in draftTables"
                    :key="table.id"
                    :table="table"
                    :show-edit-link="true"
                />
            </div>
        </section>

        <!-- Create new table link -->
        <div class="flex justify-center pt-4">
            <Link
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('gameTables.profile.created.createNew') }}
            </Link>
        </div>
    </div>

    <!-- Empty state when no tables created -->
    <div
        v-else
        class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-12 text-center dark:border-stone-600 dark:bg-stone-800/50"
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
                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-stone-900 dark:text-stone-100">
            {{ t('gameTables.profile.created.noTables') }}
        </h3>
        <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">
            {{ t('gameTables.profile.created.noTablesDescription') }}
        </p>
        <div class="mt-6">
            <Link
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('gameTables.profile.created.createFirst') }}
            </Link>
        </div>
    </div>
</template>
