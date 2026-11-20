<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    label: string;
    /** One of the theme's state colours, as the panel stores it */
    color: string;
}

const props = defineProps<Props>();

// The site's semantic tokens: they keep their contrast in light and dark mode. Literal classes,
// so the module's sheet compiles them; a colour not listed here is drawn grey. Primary takes the
// base text: the primary text colour reads 4.5:1 on the page, not on its own light background
const colorClasses: Record<string, string> = {
    primary: 'bg-primary-light text-base-primary',
    info: 'bg-info-light text-base-primary',
    success: 'bg-success-light text-base-primary',
    warning: 'bg-warning-light text-base-primary',
    danger: 'bg-error-light text-base-primary',
    gray: 'bg-muted text-base-secondary',
};

const badgeClasses = computed(() => [
    'inline-flex items-center rounded-full px-3 py-1 text-sm',
    colorClasses[props.color] ?? colorClasses.gray,
]);
</script>

<template>
    <span :class="badgeClasses">{{ label }}</span>
</template>
