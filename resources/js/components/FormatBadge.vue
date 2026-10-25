<script setup lang="ts">
import { computed } from 'vue';
import { ArrowLeftRight, Globe, MapPin, type LucideIcon } from '@lucide/vue';

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
    success: 'bg-success-light text-base-primary',
    info: 'bg-info-light text-base-primary',
    warning: 'bg-warning-light text-base-primary',
    danger: 'bg-error-light text-base-primary',
    gray: 'bg-muted text-base-secondary',
    primary: 'bg-primary-light text-primary',
};

const badgeClasses = computed(() => [
    'inline-flex items-center rounded-full font-medium',
    sizeClasses[props.size],
    colorClasses[props.color] || 'bg-muted text-base-secondary',
]);

const iconMap: Record<string, LucideIcon> = {
    in_person: MapPin,
    online: Globe,
    hybrid: ArrowLeftRight,
};
</script>

<template>
    <span :class="badgeClasses">
        <component
            :is="iconMap[format]"
            v-if="iconMap[format]"
            class="mr-1 h-3 w-3"
            aria-hidden="true"
        />
        {{ label }}
    </span>
</template>
