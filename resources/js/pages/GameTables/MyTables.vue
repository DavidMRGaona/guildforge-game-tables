<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import FormatBadge from '../../components/FormatBadge.vue';
import DraftActions from '../../components/DraftActions.vue';
import DraftRejectionReason from '../../components/DraftRejectionReason.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { useFlashMessages } from '@/composables/useFlashMessages';

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
    // Whether the author may still change it (edit, send to review, delete): the server's rule
    can_edit: boolean;
    // Why it was rejected, while the author may still change it
    rejection_reason: string | null;
    created_at: string | null;
}

interface PublishedTable {
    id: string;
    title: string;
    slug: string;
    starts_at: string | null;
    status: string;
    status_label: string;
    status_color: string;
    // Whether the person directs it and the table delegates its registrations
    manages_registrations: boolean;
    // Pending requests; null when the person does not manage the registrations
    pending_registrations: number | null;
}

interface Props {
    drafts: GameTableResponseDTO[];
    published: PublishedTable[];
    canCreate: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    drafts: () => [],
    published: () => [],
    canCreate: false,
});

// What the last action did (created, saved, sent, deleted) or why it was refused (e.g. a table
// already sent to review or published)
const { success: flashSuccess, error: flashError } = useFlashMessages();

const { t, locale } = useI18n();

const hasTables = computed(() => props.drafts.length > 0 || props.published.length > 0);

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
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

function isApproved(table: GameTableResponseDTO): boolean {
    return table.is_published;
}

function isPendingReview(table: GameTableResponseDTO): boolean {
    return !table.is_published && table.frontend_creation_status === 'pending_review';
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
        <div class="bg-surface shadow">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <!-- Back to the profile tab that links here -->
                        <Link
                            href="/perfil?tab=gametables-created"
                            class="mb-2 inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-primary underline-offset-2 hover:underline"
                        >
                            <span aria-hidden="true">←</span>
                            {{ t('gameTables.backToProfile') }}
                        </Link>
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
                        class="shrink-0 self-start sm:self-auto"
                        @click="handleCreate"
                    >
                        {{ t('gameTables.myTables.createTable') }}
                    </BaseButton>
                </div>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div
                v-if="flashSuccess"
                role="status"
                class="mb-6 rounded-lg border-l-4 border-success bg-success/10 p-4 text-sm text-base-primary"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                role="alert"
                class="mb-6 rounded-lg border-l-4 border-error bg-error/10 p-4 text-sm text-base-primary"
            >
                {{ flashError }}
            </div>

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

            <section v-if="hasTables" aria-labelledby="my-tables-drafts">
                <h2 id="my-tables-drafts" class="mb-4 text-xl font-semibold text-base-primary">
                    {{ t('gameTables.myTables.drafts') }}
                </h2>
                <p v-if="props.drafts.length === 0" class="text-sm text-base-secondary">
                    {{ t('gameTables.myTables.emptyDrafts') }}
                </p>
            <div v-else class="space-y-4">
                <div
                    v-for="table in props.drafts"
                    :key="table.id"
                    class="overflow-hidden rounded-lg bg-surface shadow transition-shadow hover:shadow-md"
                >
                    <div class="p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex items-center gap-2">
                                    <h3 class="truncate text-xl font-semibold text-base-primary">
                                        <Link
                                            v-if="isApproved(table)"
                                            :href="getTableLink(table)"
                                            class="hover:text-primary"
                                        >
                                            {{ table.title }}
                                        </Link>
                                        <span v-else>{{ table.title }}</span>
                                    </h3>
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

                                <!-- Rejected in review: back to a draft until it is sent again -->
                                <DraftRejectionReason
                                    v-if="table.rejection_reason"
                                    kind="table"
                                    :reason="table.rejection_reason"
                                    class="mt-3"
                                />
                            </div>

                            <div class="flex flex-shrink-0 flex-wrap gap-2">
                                <template v-if="isPendingReview(table)">
                                    <span class="text-sm text-base-muted">
                                        {{ t('gameTables.myTables.pendingReviewMessage') }}
                                    </span>
                                </template>

                                <template v-else-if="isApproved(table)">
                                    <Link
                                        :href="getTableLink(table)"
                                        class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
                                    >
                                        {{ t('gameTables.myTables.viewPublic') }}
                                    </Link>
                                </template>

                                <!-- While the author may still change it: can_edit, the rule the server enforces -->
                                <DraftActions
                                    v-else-if="table.can_edit"
                                    kind="table"
                                    :route-key="table.slug!"
                                    :title="table.title"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </section>

            <!-- Published single tables the person created or directs -->
            <section v-if="hasTables" class="mt-10" aria-labelledby="my-tables-published">
                <h2 id="my-tables-published" class="mb-4 text-xl font-semibold text-base-primary">
                    {{ t('gameTables.myTables.published') }}
                </h2>
                <p v-if="props.published.length === 0" class="text-sm text-base-secondary">
                    {{ t('gameTables.myTables.emptyPublished') }}
                </p>
                <div v-else class="space-y-4">
                    <div
                        v-for="table in props.published"
                        :key="table.id"
                        class="overflow-hidden rounded-lg bg-surface p-6 shadow transition-shadow hover:shadow-md"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex items-center gap-2">
                                    <h3 class="truncate text-xl font-semibold text-base-primary">
                                        <Link :href="`/mesas/${table.slug}`" class="hover:text-primary">
                                            {{ table.title }}
                                        </Link>
                                    </h3>
                                    <StatusBadge :status="table.status" :label="table.status_label" :color="table.status_color" />
                                </div>
                                <p class="text-sm text-base-secondary">{{ formatDate(table.starts_at) }}</p>
                                <span
                                    v-if="table.manages_registrations && (table.pending_registrations ?? 0) > 0"
                                    class="mt-2 inline-flex items-center rounded-full bg-warning-light px-2 py-0.5 text-xs font-medium text-base-primary"
                                >
                                    {{ t('gameTables.myTables.pendingCount', { count: table.pending_registrations }, table.pending_registrations ?? 0) }}
                                </span>
                            </div>

                            <div class="flex flex-shrink-0 flex-wrap gap-2">
                                <Link
                                    :href="`/mesas/${table.slug}`"
                                    class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
                                >
                                    {{ t('gameTables.myTables.viewPublic') }}
                                </Link>
                                <Link
                                    v-if="table.manages_registrations"
                                    :href="`/mesas/mis-mesas/${table.slug}/inscritos`"
                                    class="inline-flex items-center justify-center gap-2 rounded-md border border-default px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
                                >
                                    {{ t('gameTables.myTables.manageRegistrations') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </DefaultLayout>
</template>
