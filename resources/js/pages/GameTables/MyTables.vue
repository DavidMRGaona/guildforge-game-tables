<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import FormatBadge from '../../components/FormatBadge.vue';
import { useSeo } from '@/composables/useSeo';

interface GameTableResponseDTO {
    id: string;
    title: string;
    slug: string | null;
    gameSystemName: string;
    startsAt: string | null;
    durationMinutes: number;
    tableFormat: {
        value: string;
        label: string;
        color: string;
    };
    status: {
        value: string;
        label: string;
        color: string;
    };
    isPublished: boolean;
    createdAt: string | null;
}

interface Props {
    tables: GameTableResponseDTO[];
    canCreate: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    tables: () => [],
    canCreate: false,
});

const { t, locale } = useI18n();

useSeo({
    title: 'Mis mesas',
    description: 'Administra tus mesas de juego',
});

const hasTables = computed(() => props.tables.length > 0);

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours > 0 && mins > 0) {
        return `${hours}h ${mins}min`;
    } else if (hours > 0) {
        return `${hours}h`;
    } else {
        return `${mins}min`;
    }
}

function canEdit(table: GameTableResponseDTO): boolean {
    return !table.isPublished && (table.status.value === 'draft' || table.status.value === 'rejected');
}

function canSubmitForReview(table: GameTableResponseDTO): boolean {
    return !table.isPublished && (table.status.value === 'draft' || table.status.value === 'rejected');
}

function canDelete(table: GameTableResponseDTO): boolean {
    return !table.isPublished && (table.status.value === 'draft' || table.status.value === 'rejected');
}

function isApproved(table: GameTableResponseDTO): boolean {
    return table.isPublished;
}

function isPendingReview(table: GameTableResponseDTO): boolean {
    return !table.isPublished && table.status.value === 'pending_review';
}

function handleEdit(tableId: string): void {
    router.visit(`/mesas/mis-mesas/${tableId}/editar`);
}

function handleSubmitForReview(tableId: string): void {
    if (confirm(t('gameTables.myTables.confirmSubmit'))) {
        router.post(`/mesas/mis-mesas/${tableId}/enviar-revision`);
    }
}

function handleDelete(tableId: string, tableTitle: string): void {
    if (confirm(t('gameTables.myTables.confirmDelete', { title: tableTitle }))) {
        router.delete(`/mesas/mis-mesas/${tableId}`);
    }
}

function handleCreate(): void {
    router.visit('/mesas/crear');
}

function getTableLink(table: GameTableResponseDTO): string {
    if (table.slug && table.isPublished) {
        return `/mesas/${table.slug}`;
    }
    return '#';
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                            {{ t('gameTables.myTables.title') }}
                        </h1>
                        <p class="mt-2 text-lg text-stone-600 dark:text-stone-400">
                            {{ t('gameTables.myTables.subtitle') }}
                        </p>
                    </div>

                    <BaseButton
                        v-if="props.canCreate"
                        variant="primary"
                        @click="handleCreate"
                    >
                        {{ t('gameTables.myTables.createTable') }}
                    </BaseButton>
                </div>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <EmptyState
                v-if="!hasTables"
                icon="calendar"
                :title="t('gameTables.myTables.noTables')"
                :description="t('gameTables.myTables.noTablesDescription')"
            >
                <BaseButton
                    v-if="props.canCreate"
                    variant="primary"
                    @click="handleCreate"
                >
                    {{ t('gameTables.myTables.createTable') }}
                </BaseButton>
            </EmptyState>

            <div v-else class="space-y-4">
                <div
                    v-for="table in props.tables"
                    :key="table.id"
                    class="overflow-hidden rounded-lg bg-white shadow transition-shadow hover:shadow-md dark:bg-stone-800 dark:shadow-stone-900/50"
                >
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex items-center gap-2">
                                    <h2 class="truncate text-xl font-semibold text-stone-900 dark:text-stone-100">
                                        <Link
                                            v-if="isApproved(table)"
                                            :href="getTableLink(table)"
                                            class="hover:text-amber-600 dark:hover:text-amber-500"
                                        >
                                            {{ table.title }}
                                        </Link>
                                        <span v-else>{{ table.title }}</span>
                                    </h2>
                                    <StatusBadge
                                        :status="table.status.value"
                                        :label="table.status.label"
                                        :color="table.status.color"
                                    />
                                </div>

                                <p class="text-sm font-medium text-amber-600 dark:text-amber-500">
                                    {{ table.gameSystemName }}
                                </p>

                                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-stone-600 dark:text-stone-400">
                                    <div class="flex items-center gap-1.5">
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <span>{{ formatDate(table.startsAt) }}</span>
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                        <span>{{ formatDuration(table.durationMinutes) }}</span>
                                    </div>

                                    <FormatBadge
                                        :format="table.tableFormat.value"
                                        :label="table.tableFormat.label"
                                        :color="table.tableFormat.color"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-shrink-0 flex-col gap-2 sm:flex-row">
                                <template v-if="isPendingReview(table)">
                                    <span class="text-sm text-stone-500 dark:text-stone-400">
                                        {{ t('gameTables.myTables.pendingReviewMessage') }}
                                    </span>
                                </template>

                                <template v-else-if="isApproved(table)">
                                    <Link
                                        :href="getTableLink(table)"
                                        class="inline-flex items-center justify-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-stone-900"
                                    >
                                        {{ t('gameTables.myTables.viewPublic') }}
                                    </Link>
                                </template>

                                <template v-else>
                                    <BaseButton
                                        v-if="canEdit(table)"
                                        variant="secondary"
                                        size="sm"
                                        @click="handleEdit(table.id)"
                                    >
                                        {{ t('common.edit') }}
                                    </BaseButton>

                                    <BaseButton
                                        v-if="canSubmitForReview(table)"
                                        variant="primary"
                                        size="sm"
                                        @click="handleSubmitForReview(table.id)"
                                    >
                                        {{ t('gameTables.myTables.submitForReview') }}
                                    </BaseButton>

                                    <BaseButton
                                        v-if="canDelete(table)"
                                        variant="danger"
                                        size="sm"
                                        @click="handleDelete(table.id, table.title)"
                                    >
                                        {{ t('common.delete') }}
                                    </BaseButton>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </DefaultLayout>
</template>
