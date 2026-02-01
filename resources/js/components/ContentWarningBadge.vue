<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    warning: string;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
});

const sizeClasses: Record<NonNullable<typeof props.size>, string> = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-3 py-1 text-sm',
};

const badgeClasses = computed(() => [
    'inline-flex items-center rounded-full bg-error-light font-medium text-error',
    sizeClasses[props.size],
]);
</script>

<template>
    <span :class="badgeClasses">
        <svg
            class="mr-1 h-3 w-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
        </svg>
        {{ warning }}
    </span>
</template>
