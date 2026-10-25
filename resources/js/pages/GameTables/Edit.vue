<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import { FormCombobox, FormSelect, FormCheckboxGroup, FormCheckboxWithTooltip, FormRadioGroup, FormTagsInput, FormNumberInput } from '@/components/form';
import type { CheckboxGroup, RadioOption } from '@/components/form';
import type { EditableGameMaster, GameSystem, GameTableEditData, SessionCampaignOption, TableFormDefaults, TableFormLimits } from '../../types/gametables';
import GameMasterSection from '../../components/GameMasterSection.vue';
import SessionCampaignField from '../../components/SessionCampaignField.vue';
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

interface ContextOption {
    id: string;
    name: string;
}

interface FormData {
    game_systems: GameSystem[];
    table_types: Record<string, string>;
    table_formats: Record<string, string>;
    languages: Record<string, string>;
    experience_levels: Record<string, string>;
    character_creation: Record<string, string>;
    tones: Record<string, string>;
    genres: Record<string, string>;
    safety_tools: Record<string, string>;
    content_warnings: ContentWarning[];
    events: ContextOption[];
    // The campaigns the author may create sessions of, plus the one stored on the table
    campaigns: SessionCampaignOption[];
    defaults: TableFormDefaults;
    limits: TableFormLimits;
}

interface TimeSlot {
    label: string;
    start_time: string;
    end_time: string;
    max_tables: number | null;
    available_slots?: number | null;
}

interface EventContext {
    event_id: string;
    tables_enabled: boolean;
    is_slot_based: boolean;
    available_time_slots: TimeSlot[];
    requires_location_input: boolean;
    effective_location: string | null;
    event_start_date: string | null;
    event_end_date: string | null;
    has_eligibility_override: boolean;
    has_early_access: boolean;
    creation_opens_at: string | null;
    early_access_opens_at: string | null;
    early_access_days_before: number | null;
}

interface CurrentUser {
    id: string;
    name: string;
    email: string;
}

interface Props {
    table: GameTableEditData;
    /** The stored directors: the author first, then the co-directors, as the form edits them */
    gameMasters: EditableGameMaster[];
    formData: FormData;
    eventContext?: EventContext | null;
    currentUser: CurrentUser;
    /** The author may only create sessions of the campaigns they direct (as on the create form) */
    sessionOnly?: boolean;
}

const props = defineProps<Props>();

const { t } = useI18n();

// Active tab state
const activeTab = ref('basic');

// Tab definitions
const tabs = computed((): FormTab[] => [
    { id: 'basic', label: t('gameTables.create.tabs.basic') },
    { id: 'participants', label: t('gameTables.create.tabs.participants') },
    { id: 'content', label: t('gameTables.create.tabs.content') },
    { id: 'location', label: t('gameTables.create.tabs.location') },
]);

// Field to tab mapping for error navigation (an error key is matched by its first segment, so
// `game_masters.1.email` or `genres.0` land on the tab of their field)
const fieldToTab: Record<string, string> = {
    // Basic tab
    game_system_id: 'basic',
    title: 'basic',
    starts_at: 'basic',
    duration_minutes: 'basic',
    table_type: 'basic',
    table_format: 'basic',
    language: 'basic',
    experience_level: 'basic',
    event_id: 'basic',
    campaign_id: 'basic',
    // Participants tab
    game_masters: 'participants',
    gm_gdpr_consent: 'participants',
    min_players: 'participants',
    max_players: 'participants',
    max_spectators: 'participants',
    minimum_age: 'participants',
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
    notes: 'location',
};

// Field => the i18n key of its label on the form, for the error summary
const fieldLabels: Record<string, string> = {
    // Basic tab
    game_system_id: 'gameTables.create.fields.gameSystem',
    title: 'gameTables.create.fields.title',
    starts_at: 'gameTables.create.fields.startsAt',
    duration_minutes: 'gameTables.create.fields.duration',
    table_type: 'gameTables.create.fields.tableType',
    table_format: 'gameTables.create.fields.tableFormat',
    language: 'gameTables.create.fields.language',
    experience_level: 'gameTables.create.fields.experienceLevel',
    event_id: 'gameTables.create.fields.event',
    campaign_id: 'gameTables.create.fields.campaign',
    // Participants tab
    game_masters: 'gameTables.create.gameMasters.title',
    gm_gdpr_consent: 'gameTables.create.gameMasters.gdprConsentLabel',
    min_players: 'gameTables.create.fields.minPlayers',
    max_players: 'gameTables.create.fields.maxPlayers',
    max_spectators: 'gameTables.create.fields.maxSpectators',
    minimum_age: 'gameTables.create.fields.minimumAge',
    // Content tab
    synopsis: 'gameTables.create.fields.synopsis',
    tone: 'gameTables.create.fields.tone',
    genres: 'gameTables.genres',
    character_creation: 'gameTables.create.fields.characterCreation',
    safety_tools: 'gameTables.create.fields.safetyTools',
    content_warning_ids: 'gameTables.create.fields.contentWarnings',
    custom_warnings: 'gameTables.create.fields.customWarnings',
    // Location tab
    location: 'gameTables.create.fields.location',
    online_url: 'gameTables.create.fields.onlineUrl',
    notes: 'gameTables.create.fields.notes',
};

// Where to focus a field whose element has another id: the time slot of a slot-based event
const fieldTargets: Record<string, string> = {
    starts_at: 'time_slot',
};

// The stored safety tools and genres come as {value, label} objects: the request validates the values
function extractOptionValues(options: unknown): string[] {
    if (!Array.isArray(options)) return [];
    return options.map((o) => (typeof o === 'string' ? o : o.value));
}

// Pre-populate form with existing table data (using snake_case keys from DTO::toArray())
const form = useForm({
    game_system_id: props.table.game_system_id as string | null,
    title: props.table.title,
    starts_at: props.table.starts_at?.substring(0, 16) ?? '', // Format for datetime-local input
    duration_minutes: props.table.duration_minutes,
    table_type: props.table.table_type,
    table_format: props.table.table_format,
    min_players: props.table.min_players,
    max_players: props.table.max_players,
    max_spectators: props.table.max_spectators,
    event_id: props.table.event_id,
    campaign_id: props.table.campaign_id,
    synopsis: props.table.synopsis ?? '',
    location: props.table.location ?? '',
    online_url: props.table.online_url ?? '',
    language: props.table.language,
    experience_level: (props.table.experience_level ?? '') as string | null,
    character_creation: (props.table.character_creation ?? '') as string | null,
    genres: extractOptionValues(props.table.genres),
    tone: (props.table.tone ?? '') as string | null,
    safety_tools: extractOptionValues(props.table.safety_tools),
    content_warning_ids: props.table.content_warnings,
    custom_warnings: props.table.custom_warnings,
    minimum_age: props.table.minimum_age,
    notes: props.table.notes ?? '',
    // Each co-director keeps its id so saving updates the same record instead of replacing it
    game_masters: props.gameMasters.map((gm) => ({
        ...(gm.id !== null ? { id: gm.id } : {}),
        user_id: gm.user_id,
        first_name: gm.first_name,
        last_name: gm.last_name,
        email: gm.email,
        phone: gm.phone,
        custom_title: gm.custom_title,
        is_name_public: gm.is_name_public,
        role: gm.role,
    })),
    gm_gdpr_consent: false,
});

// Errors per tab (badges), the directors' field errors and the summary shown after a failed save
// A removed director row takes only its own errors: the later rows' move up with them (adding a
// row, at the end, keeps every error where it is)
const { tabErrors, gameMasterErrors, errorItems, revealErrors, focusError, removeGameMasterRowErrors } = useFormErrorSummary({
    errors: () => form.errors,
    tabs,
    activeTab,
    fieldToTab,
    fieldLabels,
    fieldTargets,
    gameMasters: () => form.game_masters,
    replaceErrors: (errors) => form.clearErrors().setError(errors as Parameters<typeof form.setError>[0]),
});


// Context type for the event association (the campaign goes with the session type)
type ContextType = 'independent' | 'event';

// Determine initial context type from existing data (using snake_case keys)
function getInitialContextType(): ContextType {
    return props.table.event_id ? 'event' : 'independent';
}

const contextType = ref<ContextType>(getInitialContextType());

// A session needs its campaign: the field shows right under the type, and the form is not sent without it
const isCampaignSession = computed(() => form.table_type === 'campaign_session');
const sessionWithoutCampaign = computed(() => isCampaignSession.value && !form.campaign_id);

// Another type takes no campaign: clear it (the field hides)
watch(() => form.table_type, (type) => {
    if (type !== 'campaign_session') form.campaign_id = null;
});

// Choosing the campaign fills what the session takes from it
function applyCampaign(values: { gameSystemId: string; maxPlayers: number }): void {
    form.game_system_id = values.gameSystemId;
    form.max_players = values.maxPlayers;
}

// Selected time slot for slot-based scheduling
const selectedTimeSlot = ref<string | null>(null);

// Whether we're editing a table associated with an event (has event context)
const isEventContextMode = computed(() => props.eventContext !== null && props.eventContext !== undefined);

// Find the initial time slot index based on the table's starts_at
function findInitialTimeSlotIndex(): string | null {
    if (!props.eventContext?.available_time_slots || !props.table.starts_at) return null;

    const tableStartTime = props.table.starts_at;
    const index = props.eventContext.available_time_slots.findIndex(
        (slot) => slot.start_time === tableStartTime
    );

    return index >= 0 ? String(index) : null;
}

// Initialize time slot on mount
onMounted(() => {
    if (props.eventContext?.is_slot_based) {
        selectedTimeSlot.value = findInitialTimeSlotIndex();
    }
});

// When a time slot is selected, update the starts_at field
watch(selectedTimeSlot, (slotIndex) => {
    if (slotIndex !== null && props.eventContext?.available_time_slots) {
        const slot = props.eventContext.available_time_slots[Number(slotIndex)];
        if (slot) {
            form.starts_at = slot.start_time;
        }
    }
});

// Clear the event when the table leaves it (only when not in event context mode)
watch(contextType, (newType) => {
    if (isEventContextMode.value) return; // Don't change event association in event context mode
    if (newType !== 'event') form.event_id = null;
});

// Get selected event name for display
const selectedEventName = computed(() => {
    if (!props.eventContext) return null;
    const event = props.formData.events.find(e => e.id === props.eventContext!.event_id);
    return event?.name ?? props.table.event_title ?? null;
});

// Transform time slots for select component
const timeSlotOptions = computed(() => {
    if (!props.eventContext?.available_time_slots) return [];
    return props.eventContext.available_time_slots.map((slot, index) => ({
        value: String(index),
        label: slot.label,
    }));
});

// Whether location field should be disabled (fixed by event config)
const isLocationDisabled = computed(() => {
    return props.eventContext !== null && props.eventContext !== undefined && !props.eventContext.requires_location_input;
});

// Filter context options based on available data
const contextOptions = computed((): RadioOption[] => {
    const options: RadioOption[] = [
        { value: 'independent', label: t('gameTables.create.context.independent') },
    ];
    if (props.formData.events.length > 0) {
        options.push({
            value: 'event',
            label: t('gameTables.create.context.event'),
            description: t('gameTables.create.context.eventDescription'),
        });
    }
    return options;
});

// Transform events for combobox
const eventOptions = computed(() =>
    props.formData.events.map((event) => ({
        id: event.id,
        name: event.name,
    }))
);

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
// Group content warnings by severity
const contentWarningGroups = computed((): CheckboxGroup[] => {
    const warnings = props.formData.content_warnings;
    if (!Array.isArray(warnings) || warnings.length === 0) return [];

    // Group by severity
    const mildWarnings = warnings.filter((w) => w.severity === 'mild');
    const moderateWarnings = warnings.filter((w) => w.severity === 'moderate');
    const severeWarnings = warnings.filter((w) => w.severity === 'severe');

    const groups: CheckboxGroup[] = [];

    // If no severity info, put all in a single group
    if (moderateWarnings.length === 0 && severeWarnings.length === 0 && mildWarnings.length === 0) {
        groups.push({
            key: 'all',
            label: t('gameTables.create.contentWarnings.all'),
            options: warnings.map((w) => ({ id: w.id, name: w.name })),
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
    // The {gameTable} routes find the table by its slug, not its id
    form.put(`/mesas/mis-mesas/${props.table.slug}`, {
        preserveScroll: true,
        // Opens the first tab with errors and focuses the summary
        onError: () => revealErrors(),
    });
}

function cancel(): void {
    router.visit('/mesas/mis-mesas');
}
</script>

<template>
    <div class="bg-surface shadow">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                {{ t('gameTables.edit.title') }}
            </h1>
            <p class="mt-2 text-lg text-base-secondary">
                {{ t('gameTables.edit.subtitle') }}
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Event Context Banner -->
        <div
            v-if="isEventContextMode && selectedEventName"
            class="mb-6 rounded-lg border border-primary-500/30 bg-primary-500/10 p-4"
        >
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <Calendar class="h-5 w-5 text-primary" aria-hidden="true" />
                </div>
                <div>
                    <p class="text-sm font-medium text-primary">
                        {{ t('gameTables.edit.eventContext.associatedWith') }}
                    </p>
                    <p class="text-base font-semibold text-base-primary">
                        {{ selectedEventName }}
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
                                    {{ t('gameTables.create.fields.gameSystem') }}
                                </label>
                                <FormCombobox
                                    id="game_system_id"
                                    v-model="form.game_system_id"
                                    :options="gameSystemOptions"
                                    option-label="name"
                                    option-value="id"
                                    :placeholder="t('gameTables.create.fields.selectGameSystem')"
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
                                    {{ t('gameTables.create.fields.title') }}
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

                            <!-- Starts At - Time Slot selector for slot-based events -->
                            <div v-if="eventContext?.is_slot_based && timeSlotOptions.length > 0">
                                <label for="time_slot" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.timeSlot') }}
                                    <span class="text-error">*</span>
                                </label>
                                <FormSelect
                                    id="time_slot"
                                    v-model="selectedTimeSlot"
                                    :error="form.errors.starts_at ?? ''"
                                    :options="timeSlotOptions"
                                    :placeholder="t('gameTables.create.fields.selectTimeSlot')"
                                    class="mt-1"
                                />
                                <p class="mt-1 text-xs text-base-muted">
                                    {{ t('gameTables.create.fields.timeSlotHelp') }}
                                </p>
                                <p v-if="form.errors.starts_at" class="mt-1 text-sm text-error">
                                    {{ form.errors.starts_at }}
                                </p>
                            </div>

                            <!-- Starts At - Free datetime picker -->
                            <div v-else>
                                <label for="starts_at" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.startsAt') }}
                                    <span class="text-error">*</span>
                                </label>
                                <input
                                    id="starts_at"
                                    v-model="form.starts_at"
                                    type="datetime-local"
                                    name="starts_at"
                                    required
                                    :aria-invalid="!!form.errors.starts_at"
                                    :class="fieldStateClass(!!form.errors.starts_at)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary
                                           focus:outline-none focus:ring-2"
                                />
                                <p v-if="form.errors.starts_at" class="mt-1 text-sm text-error">
                                    {{ form.errors.starts_at }}
                                </p>
                            </div>

                            <!-- Duration -->
                            <div>
                                <label for="duration_minutes" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.duration') }}
                                    <span class="text-error">*</span>
                                </label>
                                <FormNumberInput
                                    id="duration_minutes"
                                    v-model="form.duration_minutes"
                                    name="duration_minutes"
                                    :min="30"
                                    :max="formData.limits.max_duration_minutes"
                                    :step="30"
                                    required
                                    :error="form.errors.duration_minutes"
                                    class="mt-1"
                                />
                                <p class="mt-1 text-xs text-base-muted">
                                    {{ t('gameTables.create.fields.durationHelp') }}
                                </p>
                                <p v-if="form.errors.duration_minutes" class="mt-1 text-sm text-error">
                                    {{ form.errors.duration_minutes }}
                                </p>
                            </div>

                            <!-- Table Type -->
                            <div>
                                <label for="table_type" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.tableType') }}
                                </label>
                                <FormSelect
                                    id="table_type"
                                    v-model="form.table_type"
                                    :error="form.errors.table_type ?? ''"
                                    :options="objectToOptions(props.formData.table_types)"
                                    :disabled="props.sessionOnly"
                                    class="mt-1"
                                />
                                <p v-if="props.sessionOnly" class="mt-1 text-xs text-base-muted">
                                    {{ t('gameTables.create.campaign.sessionOnly') }}
                                </p>
                                <p v-if="form.errors.table_type" class="mt-1 text-sm text-error">
                                    {{ form.errors.table_type }}
                                </p>
                            </div>

                            <!-- Campaign of the session -->
                            <SessionCampaignField
                                v-if="isCampaignSession"
                                v-model="form.campaign_id"
                                :campaigns="props.formData.campaigns"
                                :default-max-players="props.formData.defaults.max_players"
                                :error="form.errors.campaign_id"
                                @fill="applyCampaign"
                            />

                            <!-- Table Format -->
                            <div>
                                <label for="table_format" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.tableFormat') }}
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

                            <!-- Language -->
                            <div>
                                <label for="language" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.language') }}
                                    <span class="text-error">*</span>
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
                                    {{ t('gameTables.create.fields.experienceLevel') }}
                                    <span class="text-error">*</span>
                                </label>
                                <FormSelect
                                    id="experience_level"
                                    name="experience_level"
                                    v-model="form.experience_level"
                                    :error="form.errors.experience_level ?? ''"
                                    :options="objectToOptions(props.formData.experience_levels)"
                                    :placeholder="t('gameTables.create.fields.selectExperienceLevel')"
                                    class="mt-1"
                                />
                                <p v-if="form.errors.experience_level" class="mt-1 text-sm text-error">
                                    {{ form.errors.experience_level }}
                                </p>
                            </div>

                            <!-- Context (event association) - hide when coming from event context -->
                            <div
                                v-if="!isEventContextMode && contextOptions.length > 1"
                                class="rounded-lg border border-base-subtle bg-surface-subtle p-4"
                            >
                                <h3 class="mb-3 text-sm font-medium text-base-primary">
                                    {{ t('gameTables.create.sections.context') }}
                                </h3>

                                <div class="space-y-4">
                                    <FormRadioGroup
                                        v-model="contextType"
                                        :options="contextOptions"
                                        name="context_type"
                                    />

                                    <!-- Event selector -->
                                    <div v-if="contextType === 'event'">
                                        <label for="event_id" class="block text-sm font-medium text-base-secondary">
                                            {{ t('gameTables.create.fields.event') }}
                                        </label>
                                        <FormCombobox
                                            id="event_id"
                                            v-model="form.event_id"
                                            :options="eventOptions"
                                            option-label="name"
                                            option-value="id"
                                            :placeholder="t('gameTables.create.fields.selectEvent')"
                                            :error="form.errors.event_id"
                                            class="mt-1"
                                        />
                                        <p v-if="form.errors.event_id" class="mt-1 text-sm text-error">
                                            {{ form.errors.event_id }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Tab 2: Participants -->
                    <template #participants>
                        <div class="space-y-6 p-6">
                            <!-- Game Masters -->
                            <GameMasterSection
                                id="game_masters"
                                v-model="form.game_masters"
                                v-model:gdpr-consent="form.gm_gdpr_consent"
                                :current-user="props.currentUser"
                                :disabled="form.processing"
                                :error="form.errors.game_masters"
                                :errors="gameMasterErrors"
                                @remove="removeGameMasterRowErrors"
                            />

                            <!-- Separator -->
                            <hr class="border-default" />

                            <!-- Players Range -->
                            <div>
                                <h3 class="mb-4 text-base font-medium text-base-primary">
                                    {{ t('gameTables.create.sections.details') }}
                                </h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="min_players" class="block text-sm font-medium text-base-secondary">
                                            {{ t('gameTables.create.fields.minPlayers') }}
                                            <span class="text-error">*</span>
                                        </label>
                                        <FormNumberInput
                                            id="min_players"
                                            v-model="form.min_players"
                                            name="min_players"
                                            :min="1"
                                            :max="formData.limits.max_players"
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
                                            {{ t('gameTables.create.fields.maxPlayers') }}
                                            <span class="text-error">*</span>
                                        </label>
                                        <FormNumberInput
                                            id="max_players"
                                            v-model="form.max_players"
                                            name="max_players"
                                            :min="1"
                                            :max="formData.limits.max_players"
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

                            <!-- Max Spectators -->
                            <div>
                                <label for="max_spectators" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.maxSpectators') }}
                                </label>
                                <FormNumberInput
                                    id="max_spectators"
                                    v-model="form.max_spectators"
                                    name="max_spectators"
                                    :min="0"
                                    :max="formData.limits.max_spectators"
                                    :error="form.errors.max_spectators"
                                    class="mt-1"
                                />
                                <p v-if="form.errors.max_spectators" class="mt-1 text-sm text-error">
                                    {{ form.errors.max_spectators }}
                                </p>
                            </div>

                            <!-- Minimum Age -->
                            <div>
                                <label for="minimum_age" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.minimumAge') }}
                                </label>
                                <FormNumberInput
                                    id="minimum_age"
                                    v-model="form.minimum_age"
                                    name="minimum_age"
                                    :min="0"
                                    placeholder="18"
                                    :error="form.errors.minimum_age"
                                    class="mt-1"
                                />
                                <p v-if="form.errors.minimum_age" class="mt-1 text-sm text-error">
                                    {{ form.errors.minimum_age }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Tab 3: Content -->
                    <template #content>
                        <div class="space-y-6 p-6">
                            <!-- Synopsis -->
                            <div>
                                <label for="synopsis" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.synopsis') }}
                                </label>
                                <textarea
                                    id="synopsis"
                                    v-model="form.synopsis"
                                    name="synopsis"
                                    rows="6"
                                    :aria-invalid="!!form.errors.synopsis"
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
                                    {{ t('gameTables.create.fields.tone') }}
                                </label>
                                <FormSelect
                                    id="tone"
                                    v-model="form.tone"
                                    :error="form.errors.tone ?? ''"
                                    :options="objectToOptions(props.formData.tones)"
                                    :placeholder="t('gameTables.create.fields.selectTone')"
                                    class="mt-1"
                                />
                                <p v-if="form.errors.tone" class="mt-1 text-sm text-error">
                                    {{ form.errors.tone }}
                                </p>
                            </div>

                            <!-- Genres -->
                            <div id="genres">
                                <label class="block text-sm font-medium text-base-secondary mb-3">
                                    {{ t('gameTables.genres') }}
                                </label>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <FormCheckboxWithTooltip
                                        v-for="genre in objectToOptions(props.formData.genres)"
                                        :id="`genre_${genre.value}`"
                                        :key="genre.value"
                                        v-model="form.genres"
                                        :value="genre.value"
                                        :label="genre.label"
                                    />
                                </div>
                                <p v-if="form.errors.genres" class="mt-2 text-sm text-error">
                                    {{ form.errors.genres }}
                                </p>
                            </div>

                            <!-- Character Creation -->
                            <div>
                                <label for="character_creation" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.characterCreation') }}
                                    <span class="text-error">*</span>
                                </label>
                                <FormSelect
                                    id="character_creation"
                                    name="character_creation"
                                    v-model="form.character_creation"
                                    :error="form.errors.character_creation ?? ''"
                                    :options="objectToOptions(props.formData.character_creation)"
                                    :placeholder="t('gameTables.create.fields.selectCharacterCreation')"
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
                                    {{ t('gameTables.create.fields.safetyTools') }}
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
                                    {{ t('gameTables.create.fields.contentWarnings') }}
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
                                    {{ t('gameTables.create.fields.customWarnings') }}
                                </label>
                                <p class="text-xs text-base-muted mb-3">
                                    {{ t('gameTables.create.fields.customWarningsHelp') }}
                                </p>
                                <FormTagsInput
                                    id="custom_warnings_input"
                                    name="custom_warnings"
                                    v-model="form.custom_warnings"
                                    :placeholder="t('gameTables.create.fields.customWarningsPlaceholder')"
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

                    <!-- Tab 4: Location -->
                    <template #location>
                        <div class="space-y-6 p-6">
                            <!-- Location (for in-person/hybrid) -->
                            <div v-if="showLocation">
                                <label for="location" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.location') }}
                                    <span v-if="isInPerson && !isLocationDisabled" class="text-error">*</span>
                                </label>
                                <input
                                    id="location"
                                    v-model="form.location"
                                    type="text"
                                    name="location"
                                    :required="isInPerson && !isLocationDisabled"
                                    :disabled="isLocationDisabled"
                                    :aria-invalid="!!form.errors.location"
                                    :class="[
                                        'mt-1 block w-full rounded-lg border px-4 py-2.5',
                                        isLocationDisabled
                                            ? 'border-default bg-base-secondary/10 text-base-secondary cursor-not-allowed'
                                            : ['bg-surface text-base-primary placeholder-base-muted focus:outline-none focus:ring-2', fieldStateClass(!!form.errors.location)]
                                    ]"
                                />
                                <p v-if="isLocationDisabled" class="mt-1 text-xs text-base-muted">
                                    {{ t('gameTables.create.fields.locationFixedByEvent') }}
                                </p>
                                <p v-if="form.errors.location" class="mt-1 text-sm text-error">
                                    {{ form.errors.location }}
                                </p>
                            </div>

                            <!-- Online URL (for online/hybrid) -->
                            <div v-if="showOnlineUrl">
                                <label for="online_url" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.onlineUrl') }}
                                    <span v-if="isOnline" class="text-error">*</span>
                                </label>
                                <input
                                    id="online_url"
                                    v-model="form.online_url"
                                    type="url"
                                    name="online_url"
                                    :required="isOnline"
                                    :aria-invalid="!!form.errors.online_url"
                                    placeholder="https://..."
                                    :class="fieldStateClass(!!form.errors.online_url)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary placeholder-base-muted
                                           focus:outline-none focus:ring-2"
                                />
                                <p class="mt-1 text-xs text-base-muted">
                                    {{ t('gameTables.create.fields.onlineUrlHelp') }}
                                </p>
                                <p v-if="form.errors.online_url" class="mt-1 text-sm text-error">
                                    {{ form.errors.online_url }}
                                </p>
                            </div>

                            <!-- Message when no location fields shown -->
                            <div v-if="!showLocation && !showOnlineUrl" class="rounded-lg bg-surface-subtle p-4 text-center">
                                <p class="text-sm text-base-muted">
                                    {{ t('gameTables.create.fields.noLocationNeeded') }}
                                </p>
                            </div>

                            <!-- Separator -->
                            <hr class="border-default" />

                            <!-- Notes -->
                            <div>
                                <label for="notes" class="block text-sm font-medium text-base-secondary">
                                    {{ t('gameTables.create.fields.notes') }}
                                </label>
                                <textarea
                                    id="notes"
                                    v-model="form.notes"
                                    name="notes"
                                    rows="4"
                                    :aria-invalid="!!form.errors.notes"
                                    :class="fieldStateClass(!!form.errors.notes)"
                                    class="mt-1 block w-full rounded-lg border bg-surface px-4 py-2.5
                                           text-base-primary placeholder-base-muted
                                           focus:outline-none focus:ring-2"
                                />
                                <p class="mt-1 text-xs text-base-muted">
                                    {{ t('gameTables.create.fields.notesHelp') }}
                                </p>
                                <p v-if="form.errors.notes" class="mt-1 text-sm text-error">
                                    {{ form.errors.notes }}
                                </p>
                            </div>
                        </div>
                    </template>
                </FormTabs>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex items-center justify-end gap-4">
                <BaseButton
                    type="button"
                    variant="secondary"
                    @click="cancel"
                >
                    {{ t('gameTables.edit.cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    variant="primary"
                    :disabled="form.processing || sessionWithoutCampaign"
                    :loading="form.processing"
                >
                    {{ t('gameTables.edit.submit') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
