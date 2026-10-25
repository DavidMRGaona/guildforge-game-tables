<script setup lang="ts">
import { ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';

interface Props {
    tool: string;
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
    'inline-flex items-center rounded-full bg-success-light font-medium text-base-primary',
    sizeClasses[props.size],
]);
</script>

<template>
    <span :class="badgeClasses">
        <ShieldCheck class="mr-1 h-3 w-3" aria-hidden="true" />
        {{ tool }}
    </span>
</template>
