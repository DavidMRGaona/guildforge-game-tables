<script setup lang="ts">
// The campaign of a «Sesión de campaña», shown right under the type of the table form. Choosing
// one fills the game system and the maximum of players (emitted as `fill`); without campaigns it
// explains why and links to the campaign form in another tab, so the table form is not lost.
import { computed, onMounted, ref, useId, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { FormSelect } from '@/components/form';
import type { SessionCampaignOption } from '../types/gametables';

interface Props {
    campaigns: SessionCampaignOption[];
    /** The module's default maximum, used when the campaign has none */
    defaultMaxPlayers: number;
    error?: string | undefined;
    /** Fill the form from the campaign already chosen (it arrives with «Añadir sesión») */
    fillOnMount?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    error: '',
    fillOnMount: false,
});

const campaignId = defineModel<string | null>({ required: true });

const emit = defineEmits<{
    fill: [values: { gameSystemId: string; maxPlayers: number }];
}>();

const { t } = useI18n();

const options = computed(() => props.campaigns.map((campaign) => ({ value: campaign.id, label: campaign.name })));
const selected = computed(() => props.campaigns.find((campaign) => campaign.id === campaignId.value) ?? null);

// The maximum this field filled in; the notice only shows once it filled the form
const filledMax = ref<number | null>(null);

// FormSelect (core) leaves extra attributes on its wrapper, not on the button that takes the
// focus: the button is told which message describes its error. A button does not take
// `aria-required`; the visible marker on the label says the field is required.
const errorId = useId();
const selectWrapper = ref<HTMLElement | null>(null);

watchEffect(
    () => {
        const button = selectWrapper.value?.querySelector<HTMLElement>('#campaign_id') ?? null;
        if (button === null) return;

        if (props.error) {
            button.setAttribute('aria-describedby', errorId);
        } else {
            button.removeAttribute('aria-describedby');
        }
    },
    { flush: 'post' },
);

function fill(campaign: SessionCampaignOption): void {
    // Never below the roster: every member gets a seat when the session is published
    const maxPlayers = campaign.max_players ?? Math.max(props.defaultMaxPlayers, campaign.roster_count);
    filledMax.value = maxPlayers;
    emit('fill', { gameSystemId: campaign.game_system_id, maxPlayers });
}

function choose(value: string | number | null): void {
    campaignId.value = value === null || value === '' ? null : String(value);
    if (selected.value !== null) {
        fill(selected.value);
    } else {
        filledMax.value = null;
    }
}

onMounted(() => {
    if (props.fillOnMount && selected.value !== null) {
        fill(selected.value);
    }
});
</script>

<template>
    <div
        class="rounded-lg border-l-4 p-4"
        :class="campaigns.length > 0
            ? 'border-primary-500 bg-primary-500/10'
            : 'border-warning bg-warning-bg'"
    >
        <template v-if="campaigns.length > 0">
            <label for="campaign_id" class="block text-sm font-medium text-base-secondary">
                {{ t('gameTables.create.fields.campaign') }}
                <span class="text-error">*</span>
            </label>
            <div ref="selectWrapper">
                <FormSelect
                    id="campaign_id"
                    name="campaign_id"
                    :model-value="campaignId"
                    :options="options"
                    :placeholder="t('gameTables.create.campaign.placeholder')"
                    :error="error"
                    class="mt-1"
                    @update:model-value="choose"
                />
            </div>
            <p v-if="error" :id="errorId" class="mt-1 text-sm text-error">
                {{ error }}
            </p>
            <p v-if="selected !== null && filledMax !== null" class="mt-2 text-sm text-base-secondary">
                {{ t('gameTables.create.campaign.notice', { count: selected.roster_count, max: filledMax }, selected.roster_count) }}
            </p>
        </template>

        <!-- Nothing to choose: create a campaign without losing this form, or change the type -->
        <template v-else>
            <p class="text-sm text-base-primary">
                {{ t('gameTables.create.campaign.empty') }}
                <a
                    href="/campanas/crear"
                    target="_blank"
                    rel="noopener"
                    class="font-medium text-primary underline underline-offset-2 hover:no-underline focus:outline-none focus:ring-2 focus:ring-primary-500"
                >{{ t('gameTables.create.campaign.createLink') }} {{ t('gameTables.create.campaign.newTab') }}</a>
                {{ t('gameTables.create.campaign.emptyAlternative') }}
            </p>
            <p v-if="error" class="mt-1 text-sm text-error">
                {{ error }}
            </p>
        </template>
    </div>
</template>
