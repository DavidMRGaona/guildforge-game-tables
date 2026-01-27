<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    status: string;
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

// Map Filament-style color names to Tailwind classes
const colorClasses: Record<string, string> = {
    success: 'bg-green-500 text-white',
    info: 'bg-blue-500 text-white',
    warning: 'bg-amber-500 text-white',
    danger: 'bg-red-500 text-white',
    gray: 'bg-stone-500 text-white',
    primary: 'bg-amber-600 text-white',
};

const badgeClasses = computed(() => [
    'inline-flex items-center rounded-full font-medium',
    sizeClasses[props.size],
    colorClasses[props.color] || 'bg-stone-500 text-white',
]);
</script>

<template>
    <span :class="badgeClasses">
        {{ label }}
    </span>
</template>
