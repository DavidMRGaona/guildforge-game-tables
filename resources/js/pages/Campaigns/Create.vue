<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import { FormCombobox, FormSelect, FormCheckboxGroup, FormCheckboxWithTooltip, FormTagsInput, FormNumberInput } from '@/components/form';
import type { CheckboxGroup } from '@/components/form';
import type { GameSystem } from '../../types/gametables';
import FormTabs from '../../components/FormTabs.vue';
import type { FormTab } from '../../components/FormTabs.vue';
import FormErrorSummary from '../../components/FormErrorSummary.vue';
import { useFormErrorSummary } from '../../composables/useFormErrorSummary';
import { fieldStateClass } from '../../utils/fieldStateClass';

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

// Active tab state
const activeTab = ref('basic');

// Tab definitions
const tabs = computed((): FormTab[] => [
    { id: 'basic', label: t('campaigns.create.tabs.basic') },
    { id: 'content', label: t('campaigns.create.tabs.content') },
    { id: 'location', label: t('campaigns.create.tabs.location') },
]);

// Field to tab mapping for error navigation (an error key is matched by its first segment, so
// `genres.0` lands on the tab of its field)
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

// Field => the i18n key of its label on the form, for the error summary
const fieldLabels: Record<string, string> = {
    // Basic tab
    game_system_id: 'campaigns.create.fields.gameSystem',
    title: 'campaigns.create.fields.title',
    frequency: 'campaigns.create.fields.frequency',
    schedule_notes: 'campaigns.create.fields.scheduleNotes',
    min_players: 'campaigns.create.fields.minPlayers',
    max_players: 'campaigns.create.fields.maxPlayers',
    table_format: 'campaigns.create.fields.tableFormat',
    language: 'campaigns.create.fields.language',
    experience_level: 'campaigns.create.fields.experienceLevel',
    // Content tab
    synopsis: 'campaigns.create.fields.synopsis',
    tone: 'campaigns.create.fields.tone',
    genres: 'gameTables.genres',
    character_creation: 'campaigns.create.fields.characterCreation',
    safety_tools: 'campaigns.create.fields.safetyTools',
    content_warning_ids: 'campaigns.create.fields.contentWarnings',
    custom_warnings: 'campaigns.create.fields.customWarnings',
    // Location tab
    location: 'campaigns.create.fields.location',
    online_url: 'campaigns.create.fields.onlineUrl',
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

// Errors per tab (badges) and the summary shown after a failed save
const { tabErrors, errorItems, revealErrors, focusError } = useFormErrorSummary({
    errors: () => form.errors,
    tabs,
    activeTab,
    fieldToTab,
    fieldLabels,
});

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

function submit(): void {
    form.post('/campanas/crear', {
        preserveScroll: true,
        // Opens the first tab with errors and focuses the summary
        onError: () => revealErrors(),
    });
}
</script>

<template>
    <div class="bg-surface shadow">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                {{ t('campaigns.create.title') }}
            </h1>
            <p class="mt-2 text-lg text-base-secondary">
                {{ t('campaigns.create.subtitle') }}
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Eligibility check -->
        <div v-if="!props.eligibility.eligible" class="mb-6 rounded-lg border border-warning/30 bg-warning-bg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <TriangleAlert class="h-5 w-5 text-warning" aria-hidden="true" />
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

        <form novalidate @submit.prevent="submit">
            <FormErrorSummary ref="errorSummary" :items="errorItems" @select="focusError" />

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
                                    :class="fieldStateClass(!!form.errors.title)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary placeholder-base-muted
                                           focus:outline-none focus:ring-2"
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
                                    :error="form.errors.frequency ?? ''"
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
                                    :class="fieldStateClass(!!form.errors.schedule_notes)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary placeholder-base-muted
                                           focus:outline-none focus:ring-2"
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
                                    :error="form.errors.table_format ?? ''"
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
                                    :error="form.errors.language ?? ''"
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
                                    :error="form.errors.experience_level ?? ''"
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
                                    :class="fieldStateClass(!!form.errors.synopsis)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary placeholder-base-muted
                                           focus:outline-none focus:ring-2"
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
                                    :error="form.errors.tone ?? ''"
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
                                    :error="form.errors.character_creation ?? ''"
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
                            <div id="safety_tools">
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
                            <div id="content_warning_ids">
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
                            <div id="custom_warnings">
                                <label
                                    for="custom_warnings_input"
                                    class="block text-sm font-medium text-base-secondary mb-2"
                                >
                                    {{ t('campaigns.create.fields.customWarnings') }}
                                </label>
                                <p class="text-xs text-base-muted mb-3">
                                    {{ t('campaigns.create.fields.customWarningsHelp') }}
                                </p>
                                <FormTagsInput
                                    id="custom_warnings_input"
                                    name="custom_warnings"
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
                                    :class="fieldStateClass(!!form.errors.location)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary placeholder-base-muted
                                           focus:outline-none focus:ring-2"
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
                                    :class="fieldStateClass(!!form.errors.online_url)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary placeholder-base-muted
                                           focus:outline-none focus:ring-2"
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
    </div>
</template>
