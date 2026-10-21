<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The moderator's reason on a rejected draft, addressed to its author («Mis mesas», «Mis campañas»
 * and the profile's drafts). A long reason is clamped to three lines with a toggle, offered only
 * when the text overflows.
 */
interface Props {
    kind: 'table' | 'campaign';
    reason: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const headingId = useId();
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
    <!-- A note, not an alert: it is there when the page loads, not a live change -->
    <div
        role="note"
        :aria-labelledby="headingId"
        class="rounded-lg border border-l-4 border-error/40 border-l-error bg-error-light p-4"
    >
        <div class="flex gap-3">
            <svg
                class="h-5 w-5 shrink-0 text-error"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                    clip-rule="evenodd"
                />
            </svg>
            <div class="min-w-0 flex-1 text-sm">
                <p :id="headingId" class="font-semibold text-error">{{ t(`${keys}.rejectionReason`) }}</p>
                <p
                    :id="reasonId"
                    ref="reasonElement"
                    :class="['mt-1 whitespace-pre-line break-words text-base-primary', expanded ? '' : 'line-clamp-3']"
                >{{ reason }}</p>
                <button
                    v-if="overflows"
                    type="button"
                    class="inline-flex min-h-[44px] items-center font-medium text-base-primary underline underline-offset-2 hover:no-underline"
                    :aria-expanded="expanded"
                    :aria-controls="reasonId"
                    @click="expanded = !expanded"
                >
                    {{ expanded ? t('gameTables.draftActions.showLess') : t('gameTables.draftActions.showMore') }}
                </button>
                <p class="mt-1 text-base-secondary">{{ t(`${keys}.rejectionHint`) }}</p>
            </div>
        </div>
    </div>
</template>
