<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The moderator's reason on a profile draft, with the look «Mis mesas» gives it. A long reason is
 * clamped to three lines with a toggle, offered only when the text overflows.
 */
interface Props {
    kind: 'table' | 'campaign';
    reason: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const reasonId = useId();

const keys = computed(() => (props.kind === 'table' ? 'gameTables.myTables' : 'campaigns.myCampaigns'));

const reasonElement = ref<HTMLElement | null>(null);
const expanded = ref(false);
const overflows = ref(false);

function measure(): void {
    const element = reasonElement.value;
    // Expanded, the text no longer overflows, but the toggle must stay to collapse it again
    if (element === null || expanded.value) {
        return;
    }
    overflows.value = element.scrollHeight > element.clientHeight + 1;
}

// The text's width follows the viewport's (rotating a phone may make it fit or overflow)
onMounted(() => {
    measure();
    window.addEventListener('resize', measure);
});

onBeforeUnmount(() => window.removeEventListener('resize', measure));

watch(
    () => props.reason,
    () => {
        expanded.value = false;
        void nextTick(measure);
    }
);
</script>

<template>
    <div class="rounded-md bg-danger-bg p-3 text-sm text-danger">
        <p class="font-semibold">{{ t(`${keys}.rejectionReason`) }}</p>
        <p
            :id="reasonId"
            ref="reasonElement"
            :class="['mt-1 whitespace-pre-line break-words', expanded ? '' : 'line-clamp-3']"
        >{{ reason }}</p>
        <button
            v-if="overflows"
            type="button"
            class="inline-flex min-h-[44px] items-center font-medium underline underline-offset-2 hover:no-underline"
            :aria-expanded="expanded"
            :aria-controls="reasonId"
            @click="expanded = !expanded"
        >
            {{ expanded ? t('gameTables.draftActions.showLess') : t('gameTables.draftActions.showMore') }}
        </button>
        <p class="mt-1">{{ t(`${keys}.rejectionHint`) }}</p>
    </div>
</template>
