<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ParticipantData } from '../types/registration';
import { getStatusColor, isActiveStatus, isWaitingStatus } from '../types/registration';

interface Props {
    registration: ParticipantData;
}

const props = defineProps<Props>();

const { t } = useI18n();

const stateClasses = computed(() => {
    const color = getStatusColor(props.registration.status);
    const baseClasses = 'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium';

    switch (color) {
        case 'green':
            return `${baseClasses} bg-success-light text-success`;
        case 'yellow':
            return `${baseClasses} bg-warning-light text-warning`;
        case 'blue':
            return `${baseClasses} bg-info-light text-info`;
        case 'red':
            return `${baseClasses} bg-error-light text-error`;
        case 'orange':
            return `${baseClasses} bg-warning-light text-warning`;
        default:
            return `${baseClasses} bg-muted text-base-secondary`;
    }
});

const statusIcon = computed(() => {
    if (isActiveStatus(props.registration.status)) {
        return 'check-circle';
    }
    if (isWaitingStatus(props.registration.status)) {
        return 'clock';
    }
    return 'x-circle';
});

const showPosition = computed(() => {
    return props.registration.status === 'waiting_list' && props.registration.waitingListPosition !== null;
});
</script>

<template>
    <div class="rounded-lg bg-muted p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Status icon -->
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full"
                    :class="{
                        'bg-success-light text-success':
                            statusIcon === 'check-circle',
                        'bg-info-light text-info':
                            statusIcon === 'clock',
                        'bg-muted text-base-muted':
                            statusIcon === 'x-circle',
                    }"
                >
                    <!-- Check circle -->
                    <svg
                        v-if="statusIcon === 'check-circle'"
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <!-- Clock -->
                    <svg
                        v-else-if="statusIcon === 'clock'"
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <!-- X circle -->
                    <svg
                        v-else
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <div>
                    <p class="font-medium text-base-primary">
                        {{ t('gameTables.registration.yourStatus') }}
                    </p>
                    <span :class="stateClasses">
                        {{ registration.statusLabel }}
                    </span>
                </div>
            </div>

            <!-- Position in waiting list -->
            <div
                v-if="showPosition"
                class="text-right"
            >
                <p class="text-sm text-base-secondary">
                    {{ t('gameTables.participants.waitingList') }}
                </p>
                <p class="text-2xl font-bold text-base-primary">
                    #{{ registration.waitingListPosition }}
                </p>
            </div>
        </div>

        <!-- Registration details -->
        <div
            v-if="registration.confirmedAt || registration.createdAt"
            class="mt-3 border-t border-default pt-3 text-sm text-base-secondary"
        >
            <p v-if="registration.confirmedAt">
                {{ t('gameTables.registration.confirmedAt') }}:
                {{ new Date(registration.confirmedAt).toLocaleDateString() }}
            </p>
            <p v-else-if="registration.createdAt">
                {{ t('gameTables.registration.registeredAt') }}:
                {{ new Date(registration.createdAt).toLocaleDateString() }}
            </p>
        </div>
    </div>
</template>
