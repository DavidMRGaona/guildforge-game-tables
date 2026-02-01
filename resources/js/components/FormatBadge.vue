<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    format: string;
    label: string;
    color: string;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
});

const sizeClasses: Record<NonNullable<typeof props.size>, string> = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-3 py-1 text-sm',
};

// Map Filament-style color names to semantic CSS variable classes
const colorClasses: Record<string, string> = {
    success: 'bg-success-light text-success',
    info: 'bg-info-light text-info',
    warning: 'bg-warning-light text-warning',
    danger: 'bg-error-light text-error',
    gray: 'bg-muted text-base-secondary',
    primary: 'bg-primary-light text-primary',
};

const badgeClasses = computed(() => [
    'inline-flex items-center rounded-full font-medium',
    sizeClasses[props.size],
    colorClasses[props.color] || 'bg-muted text-base-secondary',
]);

const iconMap: Record<string, string> = {
    in_person: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
    online: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9',
    hybrid: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
};
</script>

<template>
    <span :class="badgeClasses">
        <svg
            v-if="iconMap[format]"
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
                :d="iconMap[format]"
            />
        </svg>
        {{ label }}
    </span>
</template>
