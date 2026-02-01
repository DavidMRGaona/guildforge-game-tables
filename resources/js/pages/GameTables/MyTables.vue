<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import FormatBadge from '../../components/FormatBadge.vue';
import { useSeo } from '@/composables/useSeo';

interface GameTableResponseDTO {
    id: string;
    title: string;
    slug: string | null;
    game_system_name: string;
    starts_at: string | null;
    duration_minutes: number;
    table_format: string;
    table_format_label: string;
    table_format_color: string;
    status: string;
    status_label: string;
    status_color: string;
    frontend_creation_status: string | null;
    frontend_creation_status_label: string | null;
    frontend_creation_status_color: string | null;
    is_published: boolean;
    created_at: string | null;
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

// Confirmation dialog state
const showSubmitDialog = ref(false);
const showDeleteDialog = ref(false);
const pendingTableSlug = ref<string | null>(null);
const pendingTableTitle = ref<string>('');

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
    const status = table.frontend_creation_status;
    return !table.is_published && (status === 'draft' || status === 'rejected');
}

function canSubmitForReview(table: GameTableResponseDTO): boolean {
    const status = table.frontend_creation_status;
    return !table.is_published && (status === 'draft' || status === 'rejected');
}

function canDelete(table: GameTableResponseDTO): boolean {
    const status = table.frontend_creation_status;
    return !table.is_published && (status === 'draft' || status === 'rejected');
}

function isApproved(table: GameTableResponseDTO): boolean {
    return table.is_published;
}

function isPendingReview(table: GameTableResponseDTO): boolean {
    return !table.is_published && table.frontend_creation_status === 'pending_review';
}

function handleEdit(tableSlug: string): void {
    router.visit(`/mesas/mis-mesas/${tableSlug}/editar`);
}

function handleSubmitForReview(tableSlug: string): void {
    pendingTableSlug.value = tableSlug;
    showSubmitDialog.value = true;
}

function confirmSubmitForReview(): void {
    if (pendingTableSlug.value) {
        router.post(`/mesas/mis-mesas/${pendingTableSlug.value}/enviar-revision`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                pendingTableSlug.value = null;
            },
        });
    } else {
        pendingTableSlug.value = null;
    }
}

function handleDelete(tableSlug: string, tableTitle: string): void {
    pendingTableSlug.value = tableSlug;
    pendingTableTitle.value = tableTitle;
    showDeleteDialog.value = true;
}

function confirmDelete(): void {
    if (pendingTableSlug.value) {
        router.delete(`/mesas/mis-mesas/${pendingTableSlug.value}`);
    }
    pendingTableSlug.value = null;
    pendingTableTitle.value = '';
}

function handleCreate(): void {
    router.visit('/mesas/crear');
}

function getTableLink(table: GameTableResponseDTO): string {
    if (table.slug && table.is_published) {
        return `/mesas/${table.slug}`;
    }
    return '#';
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-surface shadow dark:shadow-neutral-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                            {{ t('gameTables.myTables.title') }}
                        </h1>
                        <p class="mt-2 text-lg text-base-secondary">
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
                    class="overflow-hidden rounded-lg bg-surface shadow transition-shadow hover:shadow-md dark:shadow-neutral-900/50"
                >
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex items-center gap-2">
                                    <h2 class="truncate text-xl font-semibold text-base-primary">
                                        <Link
                                            v-if="isApproved(table)"
                                            :href="getTableLink(table)"
                                            class="hover:text-primary"
                                        >
                                            {{ table.title }}
                                        </Link>
                                        <span v-else>{{ table.title }}</span>
                                    </h2>
                                    <StatusBadge
                                        :status="table.frontend_creation_status ?? table.status"
                                        :label="table.frontend_creation_status_label ?? table.status_label"
                                        :color="table.frontend_creation_status_color ?? table.status_color"
                                    />
                                </div>

                                <p class="text-sm font-medium text-primary">
                                    {{ table.game_system_name }}
                                </p>

                                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-base-secondary">
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
                                        <span>{{ formatDate(table.starts_at) }}</span>
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
                                        <span>{{ formatDuration(table.duration_minutes) }}</span>
                                    </div>

                                    <FormatBadge
                                        :format="table.table_format"
                                        :label="table.table_format_label"
                                        :color="table.table_format_color"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-shrink-0 flex-col gap-2 sm:flex-row">
                                <template v-if="isPendingReview(table)">
                                    <span class="text-sm text-base-muted">
                                        {{ t('gameTables.myTables.pendingReviewMessage') }}
                                    </span>
                                </template>

                                <template v-else-if="isApproved(table)">
                                    <Link
                                        :href="getTableLink(table)"
                                        class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page"
                                    >
                                        {{ t('gameTables.myTables.viewPublic') }}
                                    </Link>
                                </template>

                                <template v-else>
                                    <BaseButton
                                        v-if="canEdit(table)"
                                        variant="secondary"
                                        size="sm"
                                        @click="handleEdit(table.slug!)"
                                    >
                                        {{ t('common.edit') }}
                                    </BaseButton>

                                    <BaseButton
                                        v-if="canSubmitForReview(table)"
                                        variant="primary"
                                        size="sm"
                                        @click="handleSubmitForReview(table.slug!)"
                                    >
                                        {{ t('gameTables.myTables.submitForReview') }}
                                    </BaseButton>

                                    <BaseButton
                                        v-if="canDelete(table)"
                                        variant="danger"
                                        size="sm"
                                        @click="handleDelete(table.slug!, table.title)"
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

        <!-- Submit for review confirmation dialog -->
        <ConfirmDialog
            v-model="showSubmitDialog"
            :title="t('gameTables.myTables.submitForReviewTitle')"
            :message="t('gameTables.myTables.confirmSubmit')"
            :confirm-label="t('gameTables.myTables.submitForReview')"
            :cancel-label="t('common.cancel')"
            confirm-variant="primary"
            @confirm="confirmSubmitForReview"
        />

        <!-- Delete confirmation dialog -->
        <ConfirmDialog
            v-model="showDeleteDialog"
            :title="t('gameTables.myTables.deleteTitle')"
            :message="t('gameTables.myTables.confirmDelete', { title: pendingTableTitle })"
            :confirm-label="t('common.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmDelete"
        />
    </DefaultLayout>
</template>
