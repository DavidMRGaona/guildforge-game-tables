<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useFlashMessages } from '@/composables/useFlashMessages';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { TableRegistrationRow, TableRegistrationsPage } from '../../types/gametables';

const props = defineProps<TableRegistrationsPage>();

const { t, locale } = useI18n();
const { success: flashSuccess, error: flashError } = useFlashMessages();

const baseUrl = computed(() => `/mesas/mis-mesas/${props.table.slug}/inscritos`);

const pendingRows = computed(() => props.rows.filter((row) => row.status === 'pending'));
const otherRows = computed(() => props.rows.filter((row) => row.status !== 'pending'));

// An action is on its way: the buttons stay focusable (aria-disabled) and ignore clicks until the
// answer, so keyboard users keep their place
const processing = ref(false);

type VisitOptions = NonNullable<Parameters<typeof router.post>[2]>;

function visitOptions(): VisitOptions {
    processing.value = true;

    return {
        preserveScroll: true,
        // Same page: keep the instance, so the focused button survives the reload of the props
        preserveState: true,
        onFinish: () => {
            processing.value = false;
        },
    };
}

function formatDateTime(iso: string | null): string {
    if (iso === null) return '';

    return new Date(iso).toLocaleString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// Approve
function approve(row: TableRegistrationRow): void {
    if (processing.value || !row.can_approve) return;

    router.post(`${baseUrl.value}/${row.id}/aprobar`, {}, visitOptions());
}

// The reject dialog asks for an optional reason, as on the campaign roster page
const rejecting = ref<TableRegistrationRow | null>(null);
const rejectReason = ref('');
// eslint-disable-next-line no-undef
const rejectDialog = ref<HTMLDialogElement | null>(null);
const rejectTitleId = useId();
const rejectReasonId = useId();

watch(rejecting, async (row) => {
    if (row === null) {
        rejectDialog.value?.close();
        return;
    }

    rejectReason.value = '';
    await nextTick();
    rejectDialog.value?.showModal();
});

function openReject(row: TableRegistrationRow): void {
    if (processing.value || !row.can_reject) return;

    rejecting.value = row;
}

function closeReject(): void {
    rejecting.value = null;
}

function onRejectBackdropClick(event: MouseEvent): void {
    if (event.target === rejectDialog.value) {
        closeReject();
    }
}

function confirmReject(): void {
    const row = rejecting.value;
    if (row === null) return;

    const reason = rejectReason.value.trim();
    closeReject();
    router.post(`${baseUrl.value}/${row.id}/rechazar`, { reason: reason === '' ? null : reason }, visitOptions());
}

function awaitingId(row: TableRegistrationRow): string {
    return `registration-awaiting-${row.id}`;
}

const inputClass = 'mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5 text-base-primary placeholder-base-muted hover:border-strong focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500';
const sectionClass = 'rounded-lg bg-surface p-4 shadow sm:p-6';
const badgeClass = 'inline-flex items-center rounded-full border border-default px-2 py-0.5 text-xs font-medium text-base-secondary';
</script>

<template>
    <DefaultLayout>
        <div class="bg-surface shadow">
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    href="/mesas/mis-mesas"
                    class="-my-3 inline-flex min-h-[44px] items-center gap-1 text-sm text-base-secondary hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
                >
                    <span aria-hidden="true">←</span>
                    {{ t('gameTables.registrations.back') }}
                </Link>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-base-primary">
                    {{ t('gameTables.registrations.title') }}
                </h1>
                <p class="mt-2 break-words text-base text-base-secondary">
                    <Link
                        :href="`/mesas/${table.slug}`"
                        class="font-medium text-base-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
                    >
                        {{ table.title }}
                    </Link>
                    <span> · {{ formatDateTime(table.starts_at) }}</span>
                </p>
                <p class="mt-1 text-sm text-base-secondary">
                    {{ t('gameTables.registrations.seats', { confirmed: table.confirmed_players, max: table.max_players }) }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <!-- The server's answer to the last action -->
            <div
                v-if="flashSuccess"
                role="status"
                class="rounded-lg bg-success-light p-4 text-sm text-base-primary"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                role="alert"
                class="rounded-lg bg-error-light p-4 text-sm text-base-primary"
            >
                {{ flashError }}
            </div>

            <p v-if="table.auto_confirm" class="rounded-lg border border-default bg-surface p-4 text-sm text-base-secondary">
                {{ t('gameTables.registrations.autoConfirmNotice') }}
            </p>

            <p v-if="rows.length === 0" :class="[sectionClass, 'text-sm text-base-secondary']">
                {{ t('gameTables.registrations.empty') }}
            </p>

            <template v-else>
                <!-- Pending requests -->
                <section :class="sectionClass" aria-labelledby="registrations-pending-title">
                    <h2 id="registrations-pending-title" class="text-lg font-semibold text-base-primary">
                        {{ t('gameTables.registrations.pending') }}
                        <span class="text-base-muted">({{ pending_count }})</span>
                    </h2>

                    <p v-if="pendingRows.length === 0" class="mt-3 text-sm text-base-secondary">
                        {{ t('gameTables.registrations.noPending') }}
                    </p>

                    <ul v-else class="mt-4 divide-y divide-default" role="list">
                        <li
                            v-for="row in pendingRows"
                            :key="row.id"
                            class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 text-sm text-base-primary">
                                    <span class="break-words font-medium">{{ row.name }}</span>
                                    <span v-if="row.is_guest" :class="badgeClass">{{ t('gameTables.registrations.guest') }}</span>
                                    <span :class="badgeClass">{{ row.role_label }}</span>
                                </p>
                                <p v-if="row.requested_at" class="mt-1 text-sm text-base-muted">
                                    {{ formatDateTime(row.requested_at) }}
                                </p>
                                <p
                                    v-if="row.awaiting_email_confirmation"
                                    :id="awaitingId(row)"
                                    class="mt-1 text-sm italic text-base-secondary"
                                >
                                    {{ t('gameTables.registrations.awaitingEmail') }}
                                </p>
                            </div>
                            <!-- Below sm a full-width row of equal buttons -->
                            <div class="grid flex-shrink-0 grid-cols-2 gap-2 sm:flex">
                                <BaseButton
                                    variant="primary"
                                    size="sm"
                                    :aria-disabled="processing || !row.can_approve ? 'true' : undefined"
                                    :aria-label="t('gameTables.registrations.approveNamed', { name: row.name })"
                                    :aria-describedby="row.awaiting_email_confirmation ? awaitingId(row) : undefined"
                                    class="w-full aria-disabled:cursor-not-allowed aria-disabled:opacity-50 sm:w-auto"
                                    @click="approve(row)"
                                >
                                    {{ t('gameTables.registrations.approve') }}
                                </BaseButton>
                                <BaseButton
                                    variant="secondary"
                                    size="sm"
                                    :aria-disabled="processing || !row.can_reject ? 'true' : undefined"
                                    :aria-label="t('gameTables.registrations.rejectTitle', { name: row.name })"
                                    class="w-full aria-disabled:cursor-not-allowed aria-disabled:opacity-50 sm:w-auto"
                                    @click="openReject(row)"
                                >
                                    {{ t('gameTables.registrations.reject') }}
                                </BaseButton>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Everything else: confirmed, waiting list, then the rest -->
                <section v-if="otherRows.length > 0" :class="sectionClass" aria-labelledby="registrations-others-title">
                    <h2 id="registrations-others-title" class="text-lg font-semibold text-base-primary">
                        {{ t('gameTables.registrations.others') }}
                    </h2>

                    <ul class="mt-4 divide-y divide-default" role="list">
                        <li
                            v-for="row in otherRows"
                            :key="row.id"
                            class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="flex min-w-0 flex-wrap items-center gap-2 text-sm text-base-primary">
                                <span class="break-words font-medium">{{ row.name }}</span>
                                <span v-if="row.is_guest" :class="badgeClass">{{ t('gameTables.registrations.guest') }}</span>
                                <span :class="badgeClass">{{ row.role_label }}</span>
                            </p>
                            <p class="text-sm text-base-secondary">
                                {{ row.status_label }}
                            </p>
                        </li>
                    </ul>
                </section>
            </template>
        </main>

        <!-- Reject a request, with an optional reason -->
        <Teleport to="body">
            <dialog
                ref="rejectDialog"
                :aria-labelledby="rejectTitleId"
                class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-lg border-0 bg-transparent p-0 backdrop:bg-black/60"
                @click="onRejectBackdropClick"
                @cancel.prevent="closeReject"
                @close="closeReject"
            >
                <form
                    class="w-full rounded-lg bg-surface p-6 shadow-xl"
                    @submit.prevent="confirmReject"
                >
                    <h3 :id="rejectTitleId" class="break-words text-lg font-semibold text-base-primary">
                        {{ t('gameTables.registrations.rejectTitle', { name: rejecting?.name ?? '' }) }}
                    </h3>
                    <label :for="rejectReasonId" class="mt-4 block text-sm font-medium text-base-secondary">
                        {{ t('gameTables.registrations.rejectReason') }}
                    </label>
                    <textarea
                        :id="rejectReasonId"
                        v-model="rejectReason"
                        name="reason"
                        rows="3"
                        maxlength="500"
                        :class="inputClass"
                    />
                    <div class="mt-6 flex justify-end gap-3">
                        <BaseButton variant="secondary" size="sm" @click="closeReject">
                            {{ t('common.cancel') }}
                        </BaseButton>
                        <BaseButton type="submit" variant="danger" size="sm">
                            {{ t('gameTables.registrations.rejectConfirm') }}
                        </BaseButton>
                    </div>
                </form>
            </dialog>
        </Teleport>
    </DefaultLayout>
</template>
