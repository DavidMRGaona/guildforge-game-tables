<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { FormCombobox, FormSelect, FormCheckboxGroup, FormCheckboxWithTooltip, FormTagsInput, FormNumberInput } from '@/components/form';
import type { CheckboxGroup } from '@/components/form';
import { useSeo } from '@/composables/useSeo';
import type { GameSystem } from '../../types/gametables';
import FormTabs from '../../components/FormTabs.vue';
import type { FormTab } from '../../components/FormTabs.vue';

type OptionsInput = Record<string, string> | Array<{ value: string; label: string }> | null | undefined;

// Helper to transform {value: label} objects to [{value, label}] arrays
function objectToOptions(
    obj: OptionsInput
): Array<{ value: string; label: string }> {
    if (!obj) return [];
    if (Array.isArray(obj)) return obj;
    return Object.entries(obj).map(([value, label]) => ({ value, label }));
}

interface ContentWarning {
    id: string;
    name: string;
    description: string | null;
    severity: 'mild' | 'moderate' | 'severe';
}

interface FormData {
    game_systems: GameSystem[];
    table_formats: Record<string, string>;
    languages: Record<string, string>;
    experience_levels: Record<string, string>;
    character_creation: Record<string, string>;
    tones: Record<string, string>;
    genres: Record<string, string>;
    safety_tools: Record<string, string>;
    content_warnings: ContentWarning[];
    campaign_frequencies: Record<string, string>;
}

interface Eligibility {
    eligible: boolean;
    reason: string | null;
    canCreateAt: string | null;
    userTier: string | null;
}

interface Props {
    formData: FormData;
    eligibility: Eligibility;
}

const props = defineProps<Props>();

const { t } = useI18n();

useSeo({
    title: t('campaigns.create.title'),
});

// Active tab state
const activeTab = ref('basic');

// Tab definitions
const tabs = computed((): FormTab[] => [
    { id: 'basic', label: t('campaigns.create.tabs.basic') },
    { id: 'content', label: t('campaigns.create.tabs.content') },
    { id: 'location', label: t('campaigns.create.tabs.location') },
]);

// Field to tab mapping for error navigation
const fieldToTab: Record<string, string> = {
    // Basic tab
    game_system_id: 'basic',
    title: 'basic',
    frequency: 'basic',
    schedule_notes: 'basic',
    min_players: 'basic',
    max_players: 'basic',
    table_format: 'basic',
    language: 'basic',
    experience_level: 'basic',
    // Content tab
    synopsis: 'content',
    tone: 'content',
    genres: 'content',
    character_creation: 'content',
    safety_tools: 'content',
    content_warning_ids: 'content',
    custom_warnings: 'content',
    // Location tab
    location: 'location',
    online_url: 'location',
};

const form = useForm({
    game_system_id: '' as string | null,
    title: '',
    synopsis: '',
    frequency: '' as string | null,
    schedule_notes: '',
    min_players: 3,
    max_players: 5,
    table_format: 'in_person',
    location: '',
    online_url: '',
    language: 'es',
    experience_level: '' as string | null,
    character_creation: '' as string | null,
    genres: [] as string[],
    tone: '' as string | null,
    safety_tools: [] as string[],
    content_warning_ids: [] as string[],
    custom_warnings: [] as string[],
});

// Calculate errors per tab for badge display
const tabErrors = computed(() => {
    const counts: Record<string, number> = {};
    const errorKeys = Object.keys(form.errors);

    for (const key of errorKeys) {
        const tab = fieldToTab[key] ?? 'basic';
        counts[tab] = (counts[tab] ?? 0) + 1;
    }

    return counts;
});

// Find first tab with errors
function findFirstTabWithErrors(): string | null {
    for (const tab of tabs.value) {
        if ((tabErrors.value[tab.id] ?? 0) > 0) {
            return tab.id;
        }
    }
    return null;
}

const isOnline = computed(() => form.table_format === 'online');
const isInPerson = computed(() => form.table_format === 'in_person');
const isHybrid = computed(() => form.table_format === 'hybrid');

const showLocation = computed(() => isInPerson.value || isHybrid.value);
const showOnlineUrl = computed(() => isOnline.value || isHybrid.value);

// Transform game systems for combobox
const gameSystemOptions = computed(() =>
    props.formData.game_systems.map((system) => ({
        id: system.id,
        name: system.name,
    }))
);

// Safety tool descriptions mapped by enum value (ID)
const safetyToolDescriptions: Record<string, string> = {
    x_card: 'Permite a cualquier jugador detener una escena o pausar el juego sin necesidad de dar explicaciones. Una herramienta simple pero poderosa para mantener la comodidad de todos.',
    lines_and_veils: 'Define límites absolutos (líneas) que nunca se cruzarán, y temas que se pueden mencionar pero no explorar en detalle (velos). Se establecen al inicio de la partida.',
    open_door: 'Los jugadores pueden abandonar la sesión en cualquier momento sin dar explicaciones. Promueve un ambiente seguro donde nadie se siente obligado a quedarse si está incómodo.',
    stars: 'Sistema para pedir más o menos de cierto tipo de contenido durante la partida. Permite ajustar el tono y los temas en tiempo real según las preferencias del grupo.',
    support_flower: 'Método para indicar que necesitas apoyo emocional o un descanso. Especialmente útil en partidas con temas intensos donde los jugadores pueden necesitar un momento.',
    script: 'Permite rebobinar (volver atrás), avanzar rápidamente o pausar una escena, como en una película. Útil para ajustar momentos que no funcionan para el grupo.',
    roses: 'Al final de cada sesión, los jugadores comparten aspectos positivos (rosas) y constructivos (espinas). Fomenta la comunicación y mejora continua del juego.',
    other: 'Otra herramienta de seguridad no listada. Especifica cuál en las notas de la mesa.',
};

// Transform safety tools from Record<string, string> to array with descriptions
const safetyToolsWithDescriptions = computed(() => {
    const tools = props.formData.safety_tools;
    if (!tools || typeof tools !== 'object') return [];

    // Backend sends Record<string, string> from enum options (id => label)
    return Object.entries(tools).map(([id, name]) => ({
        id,
        name,
        description: safetyToolDescriptions[id] ?? '',
    }));
});

// Group content warnings by severity
const contentWarningGroups = computed((): CheckboxGroup[] => {
    const warnings = props.formData.content_warnings;
    if (!Array.isArray(warnings) || warnings.length === 0) return [];

    // Backend sends array of {id, name, description} objects
    const warningObjects = warnings as ContentWarning[];

    // Group by severity
    const mildWarnings = warningObjects.filter((w) => w.severity === 'mild');
    const moderateWarnings = warningObjects.filter((w) => w.severity === 'moderate');
    const severeWarnings = warningObjects.filter((w) => w.severity === 'severe');

    const groups: CheckboxGroup[] = [];

    // Only add groups that have warnings
    // If no severity info, put all in a single group
    if (moderateWarnings.length === 0 && severeWarnings.length === 0) {
        groups.push({
            key: 'all',
            label: t('gameTables.create.contentWarnings.all'),
            options: warningObjects.map((w) => ({ id: w.id, name: w.name })),
        });
    } else {
        if (mildWarnings.length > 0) {
            groups.push({
                key: 'mild',
                label: t('gameTables.create.contentWarnings.mild'),
                severity: 'mild',
                options: mildWarnings.map((w) => ({ id: w.id, name: w.name })),
            });
        }
        if (moderateWarnings.length > 0) {
            groups.push({
                key: 'moderate',
                label: t('gameTables.create.contentWarnings.moderate'),
                severity: 'moderate',
                options: moderateWarnings.map((w) => ({ id: w.id, name: w.name })),
            });
        }
        if (severeWarnings.length > 0) {
            groups.push({
                key: 'severe',
                label: t('gameTables.create.contentWarnings.severe'),
                severity: 'severe',
                options: severeWarnings.map((w) => ({ id: w.id, name: w.name })),
            });
        }
    }

    return groups;
});

function scrollToFirstError(): void {
    const errorKeys = Object.keys(form.errors);
    const firstErrorField = errorKeys[0];
    if (!firstErrorField) return;

    const element = document.querySelector(`[name="${firstErrorField}"]`)
        ?? document.getElementById(firstErrorField);

    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        (element as HTMLElement).focus?.();
    }
}

function submit(): void {
    form.post('/campanas/crear', {
        preserveScroll: true,
        onError: () => {
            nextTick(() => {
                // Navigate to first tab with errors
                const firstTabWithErrors = findFirstTabWithErrors();
                if (firstTabWithErrors) {
                    activeTab.value = firstTabWithErrors;
                }
                // Then scroll to first error field
                nextTick(() => {
                    scrollToFirstError();
                });
            });
        },
    });
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-surface shadow dark:shadow-neutral-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                    {{ t('campaigns.create.title') }}
                </h1>
                <p class="mt-2 text-lg text-base-secondary">
                    {{ t('campaigns.create.subtitle') }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Eligibility check -->
            <div v-if="!props.eligibility.eligible" class="mb-6 rounded-lg border border-warning/30 bg-warning-bg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-warning" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-warning">
                            {{ props.eligibility.reason }}
                        </p>
                        <p v-if="props.eligibility.canCreateAt" class="mt-1 text-sm text-warning/80">
                            {{ t('campaigns.create.canCreateAt', { date: props.eligibility.canCreateAt }) }}
                        </p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit">
                <div class="rounded-lg border border-default bg-surface">
                    <FormTabs v-model="activeTab" :tabs="tabs" :errors="tabErrors">
                        <!-- Tab 1: Basic Information -->
                        <template #basic>
                            <div class="space-y-6 p-6">
                                <!-- Game System -->
                                <div>
                                    <label for="game_system_id" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.gameSystem') }}
                                        <span class="text-error">*</span>
                                    </label>
                                    <FormCombobox
                                        id="game_system_id"
                                        v-model="form.game_system_id"
                                        :options="gameSystemOptions"
                                        option-label="name"
                                        option-value="id"
                                        :placeholder="t('campaigns.create.fields.selectGameSystem')"
                                        :error="form.errors.game_system_id"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.game_system_id" class="mt-1 text-sm text-error">
                                        {{ form.errors.game_system_id }}
                                    </p>
                                </div>

                                <!-- Title -->
                                <div>
                                    <label for="title" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.title') }}
                                        <span class="text-error">*</span>
                                    </label>
                                    <input
                                        id="title"
                                        v-model="form.title"
                                        type="text"
                                        name="title"
                                        required
                                        :aria-invalid="!!form.errors.title"
                                        :aria-describedby="form.errors.title ? 'title-error' : undefined"
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    />
                                    <p v-if="form.errors.title" id="title-error" class="mt-1 text-sm text-error">
                                        {{ form.errors.title }}
                                    </p>
                                </div>

                                <!-- Frequency -->
                                <div>
                                    <label for="frequency" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.frequency') }}
                                        <span class="text-error">*</span>
                                    </label>
                                    <FormSelect
                                        id="frequency"
                                        v-model="form.frequency"
                                        :options="objectToOptions(props.formData.campaign_frequencies)"
                                        :placeholder="t('campaigns.create.fields.selectFrequency')"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.frequency" class="mt-1 text-sm text-error">
                                        {{ form.errors.frequency }}
                                    </p>
                                </div>

                                <!-- Schedule Notes -->
                                <div>
                                    <label for="schedule_notes" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.scheduleNotes') }}
                                    </label>
                                    <input
                                        id="schedule_notes"
                                        v-model="form.schedule_notes"
                                        type="text"
                                        name="schedule_notes"
                                        :aria-invalid="!!form.errors.schedule_notes"
                                        :placeholder="t('campaigns.create.fields.scheduleNotesPlaceholder')"
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    />
                                    <p class="mt-1 text-xs text-base-muted">
                                        {{ t('campaigns.create.fields.scheduleNotesHelp') }}
                                    </p>
                                    <p v-if="form.errors.schedule_notes" class="mt-1 text-sm text-error">
                                        {{ form.errors.schedule_notes }}
                                    </p>
                                </div>

                                <!-- Table Format -->
                                <div>
                                    <label for="table_format" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.tableFormat') }}
                                        <span class="text-error">*</span>
                                    </label>
                                    <FormSelect
                                        id="table_format"
                                        v-model="form.table_format"
                                        :options="objectToOptions(props.formData.table_formats)"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.table_format" class="mt-1 text-sm text-error">
                                        {{ form.errors.table_format }}
                                    </p>
                                </div>

                                <!-- Players Range -->
                                <div>
                                    <h3 class="mb-4 text-base font-medium text-base-primary">
                                        {{ t('campaigns.create.sections.players') }}
                                    </h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="min_players" class="block text-sm font-medium text-base-secondary">
                                                {{ t('campaigns.create.fields.minPlayers') }}
                                                <span class="text-error">*</span>
                                            </label>
                                            <FormNumberInput
                                                id="min_players"
                                                v-model="form.min_players"
                                                name="min_players"
                                                :min="1"
                                                :max="12"
                                                required
                                                :error="form.errors.min_players"
                                                class="mt-1"
                                            />
                                            <p v-if="form.errors.min_players" class="mt-1 text-sm text-error">
                                                {{ form.errors.min_players }}
                                            </p>
                                        </div>
                                        <div>
                                            <label for="max_players" class="block text-sm font-medium text-base-secondary">
                                                {{ t('campaigns.create.fields.maxPlayers') }}
                                                <span class="text-error">*</span>
                                            </label>
                                            <FormNumberInput
                                                id="max_players"
                                                v-model="form.max_players"
                                                name="max_players"
                                                :min="1"
                                                :max="12"
                                                required
                                                :error="form.errors.max_players"
                                                class="mt-1"
                                            />
                                            <p v-if="form.errors.max_players" class="mt-1 text-sm text-error">
                                                {{ form.errors.max_players }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Language -->
                                <div>
                                    <label for="language" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.language') }}
                                    </label>
                                    <FormSelect
                                        id="language"
                                        name="language"
                                        v-model="form.language"
                                        :options="objectToOptions(props.formData.languages)"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.language" class="mt-1 text-sm text-error">
                                        {{ form.errors.language }}
                                    </p>
                                </div>

                                <!-- Experience Level -->
                                <div>
                                    <label for="experience_level" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.experienceLevel') }}
                                    </label>
                                    <FormSelect
                                        id="experience_level"
                                        name="experience_level"
                                        v-model="form.experience_level"
                                        :options="objectToOptions(props.formData.experience_levels)"
                                        :placeholder="t('campaigns.create.fields.selectExperienceLevel')"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.experience_level" class="mt-1 text-sm text-error">
                                        {{ form.errors.experience_level }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Tab 2: Content -->
                        <template #content>
                            <div class="space-y-6 p-6">
                                <!-- Synopsis -->
                                <div>
                                    <label for="synopsis" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.synopsis') }}
                                    </label>
                                    <textarea
                                        id="synopsis"
                                        v-model="form.synopsis"
                                        name="synopsis"
                                        rows="6"
                                        :aria-invalid="!!form.errors.synopsis"
                                        :placeholder="t('campaigns.create.fields.synopsisPlaceholder')"
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    />
                                    <p v-if="form.errors.synopsis" class="mt-1 text-sm text-error">
                                        {{ form.errors.synopsis }}
                                    </p>
                                </div>

                                <!-- Tone -->
                                <div>
                                    <label for="tone" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.tone') }}
                                    </label>
                                    <FormSelect
                                        id="tone"
                                        v-model="form.tone"
                                        :options="objectToOptions(props.formData.tones)"
                                        :placeholder="t('campaigns.create.fields.selectTone')"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.tone" class="mt-1 text-sm text-error">
                                        {{ form.errors.tone }}
                                    </p>
                                </div>

                                <!-- Character Creation -->
                                <div>
                                    <label for="character_creation" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.characterCreation') }}
                                    </label>
                                    <FormSelect
                                        id="character_creation"
                                        name="character_creation"
                                        v-model="form.character_creation"
                                        :options="objectToOptions(props.formData.character_creation)"
                                        :placeholder="t('campaigns.create.fields.selectCharacterCreation')"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.character_creation" class="mt-1 text-sm text-error">
                                        {{ form.errors.character_creation }}
                                    </p>
                                </div>

                                <!-- Separator -->
                                <hr class="border-default" />

                                <!-- Safety Tools with tooltips -->
                                <div>
                                    <label class="block text-sm font-medium text-base-secondary mb-3">
                                        {{ t('campaigns.create.fields.safetyTools') }}
                                    </label>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <FormCheckboxWithTooltip
                                            v-for="tool in safetyToolsWithDescriptions"
                                            :key="tool.id"
                                            v-model="form.safety_tools"
                                            :value="tool.id"
                                            :label="tool.name"
                                            :description="tool.description"
                                            :id="`safety_tool_${tool.id}`"
                                        />
                                    </div>
                                    <p v-if="form.errors.safety_tools" class="mt-2 text-sm text-error">
                                        {{ form.errors.safety_tools }}
                                    </p>
                                </div>

                                <!-- Content Warnings with collapsible groups -->
                                <div>
                                    <label class="block text-sm font-medium text-base-secondary mb-3">
                                        {{ t('campaigns.create.fields.contentWarnings') }}
                                    </label>
                                    <FormCheckboxGroup
                                        v-model="form.content_warning_ids"
                                        :groups="contentWarningGroups"
                                        collapsible
                                        :error="form.errors.content_warning_ids"
                                    />
                                </div>

                                <!-- Custom Warnings -->
                                <div>
                                    <label class="block text-sm font-medium text-base-secondary mb-2">
                                        {{ t('campaigns.create.fields.customWarnings') }}
                                    </label>
                                    <p class="text-xs text-base-muted mb-3">
                                        {{ t('campaigns.create.fields.customWarningsHelp') }}
                                    </p>
                                    <FormTagsInput
                                        v-model="form.custom_warnings"
                                        :placeholder="t('campaigns.create.fields.customWarningsPlaceholder')"
                                        :max-tags="10"
                                        :max-length="200"
                                        :error="form.errors.custom_warnings"
                                    />
                                    <p v-if="form.errors.custom_warnings" class="mt-1 text-sm text-error">
                                        {{ form.errors.custom_warnings }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Tab 3: Location -->
                        <template #location>
                            <div class="space-y-6 p-6">
                                <!-- Location (for in-person/hybrid) -->
                                <div v-if="showLocation">
                                    <label for="location" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.location') }}
                                    </label>
                                    <input
                                        id="location"
                                        v-model="form.location"
                                        type="text"
                                        name="location"
                                        :aria-invalid="!!form.errors.location"
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    />
                                    <p class="mt-1 text-xs text-base-muted">
                                        {{ t('campaigns.create.fields.locationHelp') }}
                                    </p>
                                    <p v-if="form.errors.location" class="mt-1 text-sm text-error">
                                        {{ form.errors.location }}
                                    </p>
                                </div>

                                <!-- Online URL (for online/hybrid) -->
                                <div v-if="showOnlineUrl">
                                    <label for="online_url" class="block text-sm font-medium text-base-secondary">
                                        {{ t('campaigns.create.fields.onlineUrl') }}
                                    </label>
                                    <input
                                        id="online_url"
                                        v-model="form.online_url"
                                        type="url"
                                        name="online_url"
                                        :aria-invalid="!!form.errors.online_url"
                                        placeholder="https://..."
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    />
                                    <p class="mt-1 text-xs text-base-muted">
                                        {{ t('campaigns.create.fields.onlineUrlHelp') }}
                                    </p>
                                    <p v-if="form.errors.online_url" class="mt-1 text-sm text-error">
                                        {{ form.errors.online_url }}
                                    </p>
                                </div>

                                <!-- Message when no location fields shown -->
                                <div v-if="!showLocation && !showOnlineUrl" class="rounded-lg bg-surface-subtle p-4 text-center">
                                    <p class="text-sm text-base-muted">
                                        {{ t('campaigns.create.fields.noLocationNeeded') }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </FormTabs>
                </div>

                <!-- Submit Button -->
                <div class="mt-6 flex items-center justify-end gap-4">
                    <BaseButton
                        type="submit"
                        variant="primary"
                        :disabled="form.processing || !props.eligibility.eligible"
                        :loading="form.processing"
                    >
                        {{ t('campaigns.create.submit') }}
                    </BaseButton>
                </div>
            </form>
        </main>
    </DefaultLayout>
</template>
