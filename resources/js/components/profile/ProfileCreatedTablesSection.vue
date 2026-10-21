<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ref, computed, useId } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useVerificationGate } from '@/composables/useVerificationGate';
import VerificationBlockedButton from '../VerificationBlockedButton.vue';
import ProfileCreatedTableCard from './ProfileCreatedTableCard.vue';
import type { ProfileCreatedTablesData, ProfileCreatedTable } from '../../types/gametables';

interface Props {
    profileCreatedTables: ProfileCreatedTablesData | null;
}

const props = defineProps<Props>();
const { t } = useI18n();
// The creation form's routes are closed to accounts that must verify their email
const gate = useVerificationGate();
// One reason for every draft's disabled actions (aria-describedby), not one per card
const actionsReasonId = useId();

const showDraftsSection = ref(true);

const publishedTables = computed<ProfileCreatedTable[]>(() => props.profileCreatedTables?.tables ?? []);
const draftTables = computed<ProfileCreatedTable[]>(() => props.profileCreatedTables?.drafts ?? []);
const totalTables = computed(() => props.profileCreatedTables?.total ?? 0);
const canCreate = computed(() => props.profileCreatedTables?.canCreate ?? false);

const createUrl = '/mesas/crear';
// «Mis mesas»: every table the user created, with its actions
const manageUrl = '/mesas/mis-mesas';

// The look of the manage link; its disabled stand-in keeps it
const manageLinkClass = 'inline-flex min-h-[44px] items-center gap-1 text-sm font-medium text-primary underline-offset-2 hover:underline';

function toggleDraftsSection(): void {
    showDraftsSection.value = !showDraftsSection.value;
}
</script>

<template>
    <div v-if="profileCreatedTables && totalTables > 0" class="space-y-10">
        <!-- Manage link and top create button -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <VerificationBlockedButton
                v-if="gate.blocked.value"
                :reason="gate.reason('form')"
                :button-class="manageLinkClass"
            >
                {{ t('gameTables.profile.created.manage') }}
            </VerificationBlockedButton>
            <Link
                v-else
                :href="manageUrl"
                :class="manageLinkClass"
            >
                {{ t('gameTables.profile.created.manage') }}
                <span aria-hidden="true">→</span>
            </Link>

            <VerificationBlockedButton
                v-if="canCreate && gate.blocked.value"
                :reason="gate.reason('createTable')"
                align="end"
                button-class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('gameTables.profile.created.createNew') }}
            </VerificationBlockedButton>
            <Link
                v-else-if="canCreate"
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('gameTables.profile.created.createNew') }}
            </Link>
        </div>

        <!-- Published tables section -->
        <section>
            <h2 class="mb-6 flex items-center gap-2 text-lg font-semibold text-base-primary">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-success-light text-xs font-bold text-base-primary">
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
                class="rounded-lg border border-dashed border-default bg-muted p-6 text-center text-sm text-base-secondary"
            >
                {{ t('gameTables.profile.created.noPublished') }}
            </p>
        </section>

        <!-- Drafts section (collapsible) -->
        <section v-if="draftTables.length > 0" class="border-t border-default pt-8">
            <button
                type="button"
                class="mb-4 flex w-full items-center justify-between rounded-lg bg-base-secondary/10 px-4 py-3 text-left transition-colors hover:bg-base-secondary/20"
                :aria-expanded="showDraftsSection"
                @click="toggleDraftsSection"
            >
                <span class="flex items-center gap-2 text-lg font-semibold text-base-primary">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-warning-light text-xs font-bold text-base-primary">
                        {{ draftTables.length }}
                    </span>
                    {{ t('gameTables.profile.created.drafts') }}
                </span>
                <svg
                    :class="['h-5 w-5 text-base-muted transition-transform', showDraftsSection ? 'rotate-180' : '']"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-if="showDraftsSection" class="space-y-3">
                <p
                    v-if="gate.blocked.value"
                    :id="actionsReasonId"
                    class="rounded-md bg-warning-bg px-3 py-2 text-sm text-base-primary"
                >
                    {{ t('gameTables.verificationGate.edit') }}
                    <Link
                        :href="gate.noticeUrl"
                        class="font-semibold underline underline-offset-2 hover:no-underline"
                    >
                        {{ gate.linkLabel.value }}
                        <span aria-hidden="true">→</span>
                    </Link>
                </p>
                <ProfileCreatedTableCard
                    v-for="table in draftTables"
                    :key="table.id"
                    :table="table"
                    :show-actions="true"
                    :actions-reason-id="actionsReasonId"
                />
            </div>
        </section>

        <!-- Create new table link (secondary) -->
        <!-- Only once on the page while blocked: the top one says why -->
        <div v-if="canCreate && !gate.blocked.value" class="flex justify-center border-t border-default pt-8">
            <Link
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg border-2 border-primary px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary-light"
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
        class="rounded-lg border border-dashed border-default bg-muted p-12 text-center"
    >
        <svg
            class="mx-auto h-12 w-12 text-base-secondary"
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
        <h3 class="mt-4 text-lg font-medium text-base-primary">
            {{ t('gameTables.profile.created.noTables') }}
        </h3>
        <p class="mt-1 text-sm text-base-muted">
            {{ t('gameTables.profile.created.noTablesDescription') }}
        </p>
        <div v-if="canCreate" class="mt-6">
            <VerificationBlockedButton
                v-if="gate.blocked.value"
                :reason="gate.reason('createTable')"
                align="center"
                button-class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('gameTables.profile.created.createFirst') }}
            </VerificationBlockedButton>
            <Link
                v-else
                :href="createUrl"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('gameTables.profile.created.createFirst') }}
            </Link>
            <!-- While blocked the create button above already says why -->
            <p v-if="!gate.blocked.value" class="mt-4">
                <Link
                    :href="manageUrl"
                    :class="manageLinkClass"
                >
                    {{ t('gameTables.profile.created.manage') }}
                    <span aria-hidden="true">→</span>
                </Link>
            </p>
        </div>
    </div>
</template>
