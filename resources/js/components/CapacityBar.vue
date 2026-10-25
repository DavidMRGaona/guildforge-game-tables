<script setup lang="ts">
import { computed } from 'vue';

type Tone = 'occupancy' | 'info' | 'primary';

interface Props {
    current: number;
    max: number;
    // Accessible name of the bar ("Capacidad", "Jugadores"…)
    label: string;
    tone?: Tone;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    tone: 'occupancy',
    size: 'sm',
});

const percentage = computed(() => {
    if (props.max <= 0) return 0;
    return Math.min(Math.max(props.current / props.max, 0), 1) * 100;
});

// Occupancy fades from the theme's warning colour (yellow) to its error colour (red) as seats fill.
// Both ends are theme tokens, so the bar follows light/dark mode; mixing in OKLCH keeps the oranges
// in between saturated instead of the muddy brown an sRGB mix gives.
const fillColour = computed(() => {
    switch (props.tone) {
        case 'info':
            return 'var(--color-info)';
        case 'primary':
            return 'var(--color-primary)';
        default:
            return `color-mix(in oklch, var(--color-warning), var(--color-error) ${Math.round(percentage.value)}%)`;
    }
});
</script>

<template>
    <div
        :class="[
            'w-full overflow-hidden rounded-full bg-base-secondary/15',
            size === 'md' ? 'h-2.5' : 'h-2',
        ]"
        role="progressbar"
        :aria-label="label"
        :aria-valuenow="current"
        :aria-valuemin="0"
        :aria-valuemax="max"
        :aria-valuetext="`${current} / ${max}`"
    >
        <div
            class="h-full rounded-full transition-all"
            :style="{ width: `${percentage}%`, backgroundColor: fillColour }"
        />
    </div>
</template>
