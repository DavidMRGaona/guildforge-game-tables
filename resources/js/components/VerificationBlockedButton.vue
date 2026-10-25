<script setup lang="ts">
// Stands in for a link or button whose route the server closes to accounts that must verify
// their email: the action shows disabled, and the reason (tied by aria-describedby) links to
// the verification notice
import { computed, useId } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useVerificationGate } from '@/composables/useVerificationGate';

interface Props {
    /** Translated reason, e.g. `gate.reason('createTable')` */
    reason: string;
    /** Classes of the action it replaces, so it keeps its place and shape */
    buttonClass?: string;
    align?: 'start' | 'center' | 'end';
}

const props = withDefaults(defineProps<Props>(), {
    buttonClass: '',
    align: 'start',
});

const gate = useVerificationGate();
// Unique per instance: a page may show several of these
const reasonId = useId();

const alignClass = computed(
    () =>
        ({
            start: 'items-start text-left',
            center: 'items-center text-center',
            end: 'items-end text-right',
        })[props.align]
);
</script>

<template>
    <div :class="['inline-flex max-w-full flex-col gap-2', alignClass]">
        <button
            type="button"
            disabled
            :aria-describedby="reasonId"
            :class="['cursor-not-allowed opacity-60', buttonClass]"
        >
            <slot />
        </button>
        <p :id="reasonId" class="rounded-md bg-warning-bg px-3 py-2 text-sm text-base-primary">
            {{ reason }}
            <Link
                :href="gate.noticeUrl"
                class="font-semibold underline underline-offset-2 hover:no-underline"
            >
                {{ gate.linkLabel.value }}
                <span aria-hidden="true">→</span>
            </Link>
        </p>
    </div>
</template>
