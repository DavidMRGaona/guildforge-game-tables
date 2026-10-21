<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';

/** What confirming did, shown on this page after the button (POST) */
interface ConfirmationResult {
    tone: 'success' | 'info' | 'error';
    message: string;
}

interface Props {
    /** Null when the link no longer leads to a registration */
    participant: {
        first_name: string | null;
        role_label: string;
        /** «Pendiente de confirmar» until the link is followed, then the registration's state */
        status_label: string;
    } | null;
    gameTable: {
        title: string;
        startsAt: string | null;
        slug: string;
    } | null;
    token: string;
    /** The link was already followed: nothing left to confirm */
    alreadyConfirmed: boolean;
    result: ConfirmationResult | null;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const formattedDate = computed(() => {
    if (!props.gameTable?.startsAt) {
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

const resultClasses: Record<ConfirmationResult['tone'], string> = {
    success: 'bg-success-light text-base-primary',
    info: 'bg-info-light text-base-primary',
    error: 'bg-error-light text-base-primary',
};

// The table, or the list of tables when the link no longer leads to one
const backHref = computed(() => (props.gameTable ? `/mesas/${props.gameTable.slug}` : '/mesas'));
const backLabel = computed(() => (props.gameTable ? t('confirmRegistration.viewTable') : t('confirmRegistration.viewTables')));

// A link styled as a button (a button inside a link is invalid interactive nesting)
const linkButtonBase = 'inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-md px-4 py-2 text-base font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-surface';
const primaryLinkClass = `${linkButtonBase} bg-primary text-white hover:bg-primary-700 focus:ring-primary-500`;
const secondaryLinkClass = `${linkButtonBase} bg-base-secondary/10 text-base-primary hover:bg-base-secondary/20 focus:ring-primary-500`;

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
                <div
                    v-if="participant && gameTable"
                    class="mb-6 rounded-lg bg-muted p-4"
                >
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

                <!-- What confirming did -->
                <template v-if="result">
                    <div
                        class="mb-6 rounded-lg p-4 text-center"
                        :class="resultClasses[result.tone]"
                        :role="result.tone === 'error' ? 'alert' : 'status'"
                    >
                        <p class="text-sm">
                            {{ result.message }}
                        </p>
                    </div>

                    <Link
                        :href="backHref"
                        :class="primaryLinkClass"
                    >
                        {{ backLabel }}
                    </Link>
                </template>

                <!-- Already confirmed -->
                <template v-else-if="alreadyConfirmed">
                    <div class="mb-6 rounded-lg bg-success-light p-4 text-center">
                        <p class="text-sm text-success">
                            {{ t('confirmRegistration.already') }}
                        </p>
                    </div>

                    <Link
                        :href="backHref"
                        :class="primaryLinkClass"
                    >
                        {{ backLabel }}
                    </Link>
                </template>

                <!-- To confirm -->
                <template v-else-if="gameTable">
                    <p class="mb-6 text-center text-base-secondary">
                        {{ t('confirmRegistration.intro', { tableTitle: gameTable.title }) }}
                    </p>

                    <div class="flex gap-4">
                        <Link
                            :href="backHref"
                            :class="[secondaryLinkClass, 'flex-1']"
                        >
                            {{ backLabel }}
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
