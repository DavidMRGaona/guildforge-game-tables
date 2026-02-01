<script setup lang="ts">
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';

interface Props {
    participant: {
        id: string;
        user_name: string;
        role: string;
        role_label: string;
        status: string;
        status_label: string;
        is_guest: boolean;
        first_name: string | null;
    };
    gameTable: {
        id: string;
        title: string;
        startsAt: string;
    };
    token: string;
    canCancel: boolean;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

useSeo({
    title: t('gameTables.cancelRegistration.title'),
    description: t('gameTables.cancelRegistration.title'),
});

const displayName = computed(() => {
    return props.participant.is_guest
        ? props.participant.first_name || t('gameTables.cancelRegistration.guest')
        : props.participant.user_name;
});

const formattedDate = computed(() => {
    const date = new Date(props.gameTable.startsAt);
    return date.toLocaleDateString(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const isLoading = computed(() => false);

function handleCancel(): void {
    router.delete(`/mesas/cancelar/${props.token}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <DefaultLayout>
        <div class="mx-auto max-w-2xl px-4 py-12">
            <div class="rounded-xl bg-surface p-8 shadow-lg">
                <!-- Header -->
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-light">
                        <svg
                            class="h-8 w-8 text-primary"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-base-primary">
                        {{ t('gameTables.cancelRegistration.title') }}
                    </h1>
                </div>

                <!-- Registration info -->
                <div class="mb-6 rounded-lg bg-muted p-4">
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('gameTables.cancelRegistration.participant') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ displayName }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('gameTables.cancelRegistration.table') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ gameTable.title }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('gameTables.cancelRegistration.date') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ formattedDate }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('gameTables.cancelRegistration.role') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ participant.role_label }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('gameTables.cancelRegistration.status') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ participant.status_label }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Can cancel -->
                <template v-if="canCancel">
                    <p class="mb-6 text-center text-base-secondary">
                        {{ t('gameTables.cancelRegistration.confirm', { tableTitle: gameTable.title }) }}
                    </p>

                    <div class="flex gap-4">
                        <Link
                            :href="`/mesas/${gameTable.id}`"
                            class="flex-1"
                        >
                            <BaseButton
                                variant="secondary"
                                class="w-full"
                            >
                                {{ $t('common.back') }}
                            </BaseButton>
                        </Link>
                        <BaseButton
                            variant="danger"
                            class="flex-1"
                            :loading="isLoading"
                            @click="handleCancel"
                        >
                            {{ t('gameTables.cancelRegistration.confirmButton') }}
                        </BaseButton>
                    </div>
                </template>

                <!-- Cannot cancel -->
                <template v-else>
                    <div class="mb-6 rounded-lg bg-warning-light p-4 text-center">
                        <p class="text-sm text-warning">
                            {{ t('gameTables.cancelRegistration.alreadyCancelled') }}
                        </p>
                    </div>

                    <Link :href="`/mesas/${gameTable.id}`">
                        <BaseButton
                            variant="primary"
                            class="w-full"
                        >
                            {{ t('gameTables.cancelRegistration.viewTable') }}
                        </BaseButton>
                    </Link>
                </template>
            </div>
        </div>
    </DefaultLayout>
</template>
