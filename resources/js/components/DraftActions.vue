<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useVerificationGate } from '@/composables/useVerificationGate';

/**
 * A draft's actions (edit, send to review, delete) for its author, shared by «Mis mesas»,
 * «Mis campañas» and the profile's drafts so they cannot drift apart. The caller shows it only
 * while the author may change the draft (`can_edit` / `canEdit`, the rule the server enforces).
 */
interface Props {
    kind: 'table' | 'campaign';
    /** What the author routes take: a table's slug, a campaign's id */
    routeKey: string;
    title: string;
    /** Where the server sends the author back; only this whitelisted value, never a URL */
    returnTo?: 'profile' | null;
    /** Id of the line saying why the actions are closed (an email to verify) */
    disabledReasonId?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    returnTo: null,
    disabledReasonId: null,
});

const { t } = useI18n();
// The author routes are closed to accounts that must verify their email
const gate = useVerificationGate();

const showSubmitDialog = ref(false);
const showDeleteDialog = ref(false);

const keys = computed(() =>
    props.kind === 'table' ? 'gameTables.myTables' : 'campaigns.myCampaigns'
);

const baseUrl = computed(() =>
    props.kind === 'table'
        ? `/mesas/mis-mesas/${props.routeKey}`
        : `/campanas/mis-campanas/${props.routeKey}`
);

const returnQuery = computed(() => (props.returnTo === 'profile' ? '?return_to=profile' : ''));

// Landing back on the profile (the server adds the tab to the URL) keeps the page's state: no remount
const visitOptions = computed(() =>
    props.returnTo === 'profile' ? { preserveState: true, preserveScroll: true } : {}
);

function handleEdit(): void {
    router.visit(`${baseUrl.value}/editar`);
}

function confirmSubmitForReview(): void {
    router.post(
        `${baseUrl.value}/enviar-revision${returnQuery.value}`,
        {},
        {
            preserveScroll: true,
            ...visitOptions.value,
        }
    );
}

function confirmDelete(): void {
    router.delete(`${baseUrl.value}${returnQuery.value}`, visitOptions.value);
}
</script>

<template>
    <!-- Below sm a full-width row of equal buttons under the card's content; from sm at the right -->
    <div
        role="group"
        :aria-label="t('gameTables.draftActions.groupLabel', { title })"
        class="grid w-full grid-cols-3 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:justify-end"
    >
        <BaseButton
            variant="secondary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="gate.blocked.value"
            :aria-describedby="gate.blocked.value ? (disabledReasonId ?? undefined) : undefined"
            @click="handleEdit"
        >
            {{ t('common.edit') }}
        </BaseButton>

        <BaseButton
            variant="primary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="gate.blocked.value"
            :aria-describedby="gate.blocked.value ? (disabledReasonId ?? undefined) : undefined"
            @click="showSubmitDialog = true"
        >
            {{ t(`${keys}.submitForReview`) }}
        </BaseButton>

        <BaseButton
            variant="danger"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="gate.blocked.value"
            :aria-describedby="gate.blocked.value ? (disabledReasonId ?? undefined) : undefined"
            @click="showDeleteDialog = true"
        >
            {{ t('common.delete') }}
        </BaseButton>

        <ConfirmDialog
            v-model="showSubmitDialog"
            :title="t(`${keys}.submitForReviewTitle`)"
            :message="t(`${keys}.confirmSubmit`)"
            :confirm-label="t(`${keys}.submitForReview`)"
            :cancel-label="t('common.cancel')"
            confirm-variant="primary"
            @confirm="confirmSubmitForReview"
        />

        <ConfirmDialog
            v-model="showDeleteDialog"
            :title="t(`${keys}.deleteTitle`)"
            :message="t(`${keys}.confirmDelete`, { title })"
            :confirm-label="t('common.delete')"
            :cancel-label="t('common.cancel')"
            confirm-variant="danger"
            @confirm="confirmDelete"
        />
    </div>
</template>
