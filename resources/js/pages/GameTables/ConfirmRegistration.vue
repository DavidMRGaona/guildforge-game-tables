<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    participant: {
        first_name: string | null;
        role_label: string;
        status_label: string;
    };
    gameTable: {
        title: string;
        startsAt: string | null;
        slug: string;
    };
    token: string;
    /** The link was already followed: nothing left to confirm */
    alreadyConfirmed: boolean;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const formattedDate = computed(() => {
    if (props.gameTable.startsAt === null) {
        return '';
    }

    return new Date(props.gameTable.startsAt).toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const isLoading = ref(false);

// Only the button confirms: opening the link (as mail scanners do) changes nothing
function handleConfirm(): void {
    router.post(`/mesas/confirmar/${props.token}`, {}, {
        preserveScroll: true,
        onStart: () => {
            isLoading.value = true;
        },
        onFinish: () => {
            isLoading.value = false;
        },
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
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-base-primary">
                        {{ t('confirmRegistration.title') }}
                    </h1>
                </div>

                <!-- Registration info -->
                <div class="mb-6 rounded-lg bg-muted p-4">
                    <dl class="space-y-2">
                        <div
                            v-if="participant.first_name"
                            class="flex justify-between"
                        >
                            <dt class="text-sm text-base-muted">
                                {{ t('confirmRegistration.participant') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ participant.first_name }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('confirmRegistration.table') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ gameTable.title }}
                            </dd>
                        </div>
                        <div
                            v-if="formattedDate"
                            class="flex justify-between"
                        >
                            <dt class="text-sm text-base-muted">
                                {{ t('confirmRegistration.date') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ formattedDate }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('confirmRegistration.role') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ participant.role_label }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-base-muted">
                                {{ t('confirmRegistration.status') }}
                            </dt>
                            <dd class="text-sm font-medium text-base-primary">
                                {{ participant.status_label }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Already confirmed -->
                <template v-if="alreadyConfirmed">
                    <div class="mb-6 rounded-lg bg-success-light p-4 text-center">
                        <p class="text-sm text-success">
                            {{ t('confirmRegistration.already') }}
                        </p>
                    </div>

                    <Link :href="`/mesas/${gameTable.slug}`">
                        <BaseButton
                            variant="primary"
                            class="w-full"
                        >
                            {{ t('confirmRegistration.viewTable') }}
                        </BaseButton>
                    </Link>
                </template>

                <!-- To confirm -->
                <template v-else>
                    <p class="mb-6 text-center text-base-secondary">
                        {{ t('confirmRegistration.intro', { tableTitle: gameTable.title }) }}
                    </p>

                    <div class="flex gap-4">
                        <Link
                            :href="`/mesas/${gameTable.slug}`"
                            class="flex-1"
                        >
                            <BaseButton
                                variant="secondary"
                                class="w-full"
                            >
                                {{ t('confirmRegistration.viewTable') }}
                            </BaseButton>
                        </Link>
                        <BaseButton
                            variant="primary"
                            class="flex-1"
                            :loading="isLoading"
                            @click="handleConfirm"
                        >
                            {{ t('confirmRegistration.button') }}
                        </BaseButton>
                    </div>
                </template>
            </div>
        </div>
    </DefaultLayout>
</template>
