<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';

interface Props {
    reason: string;
    canCreateAt?: string;
}

const props = defineProps<Props>();

const { t, d } = useI18n();

useSeo({
    title: t('gameTables.createNotEligible.title'),
});

const reasonMessage = computed((): string => {
    const reasonMap: Record<string, string> = {
        authentication_required: t('gameTables.createNotEligible.reasonAuthenticationRequired'),
        role_required: t('gameTables.createNotEligible.reasonRoleRequired'),
        frontend_creation_disabled: t('gameTables.createNotEligible.reasonFrontendCreationDisabled'),
    };

    return reasonMap[props.reason] ?? t('gameTables.createNotEligible.reasonDefault');
});

const formattedCanCreateAt = computed((): string | null => {
    if (!props.canCreateAt) {
        return null;
    }

    try {
        const date = new Date(props.canCreateAt);
        return d(date, 'long');
    } catch {
        return null;
    }
});
</script>

<template>
    <DefaultLayout>
        <main class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-md text-center">
                <!-- Icon -->
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/20">
                    <svg
                        class="h-8 w-8 text-amber-600 dark:text-amber-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                        />
                    </svg>
                </div>

                <!-- Title -->
                <h1 class="mt-6 text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                    {{ t('gameTables.createNotEligible.title') }}
                </h1>

                <!-- Reason description -->
                <p class="mt-4 text-lg text-stone-600 dark:text-stone-400">
                    {{ reasonMessage }}
                </p>

                <!-- Can create at date -->
                <p
                    v-if="formattedCanCreateAt"
                    class="mt-2 text-sm text-stone-500 dark:text-stone-500"
                >
                    {{ t('gameTables.createNotEligible.canCreateAt', { date: formattedCanCreateAt }) }}
                </p>

                <!-- Back to tables button -->
                <div class="mt-8">
                    <Link href="/mesas">
                        <BaseButton variant="primary">
                            {{ t('gameTables.createNotEligible.backToTables') }}
                        </BaseButton>
                    </Link>
                </div>
            </div>
        </main>
    </DefaultLayout>
</template>
