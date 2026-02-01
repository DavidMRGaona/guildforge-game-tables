<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { FormCombobox, FormSelect, FormCheckboxGroup, FormCheckboxWithTooltip, FormRadioGroup, FormTagsInput, FormNumberInput } from '@/components/form';
import type { CheckboxGroup, RadioOption } from '@/components/form';
import { useSeo } from '@/composables/useSeo';
import type { GameSystem } from '../../types/gametables';
import GameMasterSection from '../../components/GameMasterSection.vue';
import FormTabs from '../../components/FormTabs.vue';
import type { FormTab } from '../../components/FormTabs.vue';

type OptionsInput = Record<string, string> | Array<{ value: string; label: string }>;

// Helper to transform {value: label} objects to [{value, label}] arrays
function objectToOptions(
    obj: OptionsInput
): Array<{ value: string; label: string }> {
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
    campaigns: ContextOption[];
}

interface Eligibility {
    eligible: boolean;
    reason: string | null;
    canCreateAt: string | null;
    userTier: string | null;
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
    formData: FormData;
    eligibility: Eligibility;
    eventContext?: EventContext | null;
    currentUser: CurrentUser;
}

const props = defineProps<Props>();

const { t } = useI18n();

useSeo({
    title: t('gameTables.create.title'),
});

// Active tab state
const activeTab = ref('basic');

// Tab definitions
const tabs = computed((): FormTab[] => [
    { id: 'basic', label: t('gameTables.create.tabs.basic') },
    { id: 'participants', label: t('gameTables.create.tabs.participants') },
    { id: 'content', label: t('gameTables.create.tabs.content') },
    { id: 'location', label: t('gameTables.create.tabs.location') },
]);

// Field to tab mapping for error navigation
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
    content_warnings: 'content',
    custom_warnings: 'content',
    // Location tab
    location: 'location',
    online_url: 'location',
    notes: 'location',
};

const form = useForm({
    game_system_id: '' as string | null,
    title: '',
    starts_at: '',
    duration_minutes: 240,
    table_type: 'one_shot',
    table_format: 'in_person',
    min_players: 3,
    max_players: 5,
    max_spectators: 0,
    event_id: null as string | null,
    campaign_id: null as string | null,
    synopsis: '',
    location: '',
    online_url: '',
    language: 'es',
    experience_level: '' as string | null,
    character_creation: '' as string | null,
    genres: [] as string[],
    tone: '' as string | null,
    safety_tools: [] as string[],
    content_warnings: [] as string[],
    custom_warnings: [] as string[],
    minimum_age: null as number | null,
    notes: '',
    game_masters: [] as Array<{
        id?: string;
        user_id?: string | null;
        first_name?: string;
        last_name?: string;
        email?: string;
        phone?: string;
        custom_title?: string;
        is_name_public: boolean;
        role: 'main' | 'co_gm';
    }>,
    gm_gdpr_consent: false,
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

// Context type for event/campaign association
type ContextType = 'independent' | 'event' | 'campaign';
const contextType = ref<ContextType>('independent');

// Selected time slot for slot-based scheduling
const selectedTimeSlot = ref<string | null>(null);

// Whether we're creating a table for a specific event (from event page)
const isEventContextMode = computed(() => props.eventContext !== null && props.eventContext !== undefined);

// Pre-fill form fields when event context is provided
onMounted(() => {
    if (props.eventContext) {
        // Set event association
        form.event_id = props.eventContext.event_id;
        contextType.value = 'event';

        // Pre-fill location if provided by event config
        if (props.eventContext.effective_location) {
            form.location = props.eventContext.effective_location;
        }
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

// Clear the other field when context type changes
watch(contextType, (newType) => {
    if (newType !== 'event') form.event_id = null;
    if (newType !== 'campaign') form.campaign_id = null;
});

// Get selected event name for display
const selectedEventName = computed(() => {
    if (!props.eventContext) return null;
    const event = props.formData.events.find(e => e.id === props.eventContext!.event_id);
    return event?.name ?? null;
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
    if (props.formData.campaigns.length > 0) {
        options.push({
            value: 'campaign',
            label: t('gameTables.create.context.campaign'),
            description: t('gameTables.create.context.campaignDescription'),
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

// Transform campaigns for combobox
const campaignOptions = computed(() =>
    props.formData.campaigns.map((campaign) => ({
        id: campaign.id,
        name: campaign.name,
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
    form.post('/mesas/crear', {
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
                    {{ t('gameTables.create.title') }}
                </h1>
                <p class="mt-2 text-lg text-base-secondary">
                    {{ t('gameTables.create.subtitle') }}
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
                            {{ t('gameTables.create.canCreateAt', { date: props.eligibility.canCreateAt }) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Event Context Banner -->
            <div
                v-if="isEventContextMode && selectedEventName"
                class="mb-6 rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800/40 dark:bg-primary-900/20"
            >
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-primary-700 dark:text-primary-300">
                            {{ t('gameTables.create.eventContext.creatingFor') }}
                        </p>
                        <p class="text-base font-semibold text-primary-900 dark:text-primary-100">
                            {{ selectedEventName }}
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
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
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
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
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
                                        :options="objectToOptions(props.formData.table_types)"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.table_type" class="mt-1 text-sm text-error">
                                        {{ form.errors.table_type }}
                                    </p>
                                </div>

                                <!-- Table Format -->
                                <div>
                                    <label for="table_format" class="block text-sm font-medium text-base-secondary">
                                        {{ t('gameTables.create.fields.tableFormat') }}
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
                                        :options="objectToOptions(props.formData.experience_levels)"
                                        :placeholder="t('gameTables.create.fields.selectExperienceLevel')"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.experience_level" class="mt-1 text-sm text-error">
                                        {{ form.errors.experience_level }}
                                    </p>
                                </div>

                                <!-- Context (Event/Campaign association) - hide when coming from event context -->
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

                                        <!-- Campaign selector -->
                                        <div v-if="contextType === 'campaign'">
                                            <label for="campaign_id" class="block text-sm font-medium text-base-secondary">
                                                {{ t('gameTables.create.fields.campaign') }}
                                            </label>
                                            <FormCombobox
                                                id="campaign_id"
                                                v-model="form.campaign_id"
                                                :options="campaignOptions"
                                                option-label="name"
                                                option-value="id"
                                                :placeholder="t('gameTables.create.fields.selectCampaign')"
                                                :error="form.errors.campaign_id"
                                                class="mt-1"
                                            />
                                            <p v-if="form.errors.campaign_id" class="mt-1 text-sm text-error">
                                                {{ form.errors.campaign_id }}
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
                                    v-model="form.game_masters"
                                    v-model:gdpr-consent="form.gm_gdpr_consent"
                                    :current-user="props.currentUser"
                                    :disabled="form.processing"
                                    :error="form.errors.game_masters"
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
                                        {{ t('gameTables.create.fields.tone') }}
                                    </label>
                                    <FormSelect
                                        id="tone"
                                        v-model="form.tone"
                                        :options="objectToOptions(props.formData.tones)"
                                        :placeholder="t('gameTables.create.fields.selectTone')"
                                        class="mt-1"
                                    />
                                    <p v-if="form.errors.tone" class="mt-1 text-sm text-error">
                                        {{ form.errors.tone }}
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
                                <div>
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
                                <div>
                                    <label class="block text-sm font-medium text-base-secondary mb-3">
                                        {{ t('gameTables.create.fields.contentWarnings') }}
                                    </label>
                                    <FormCheckboxGroup
                                        v-model="form.content_warnings"
                                        :groups="contentWarningGroups"
                                        collapsible
                                        :error="form.errors.content_warnings"
                                    />
                                </div>

                                <!-- Custom Warnings -->
                                <div>
                                    <label class="block text-sm font-medium text-base-secondary mb-2">
                                        {{ t('gameTables.create.fields.customWarnings') }}
                                    </label>
                                    <p class="text-xs text-base-muted mb-3">
                                        {{ t('gameTables.create.fields.customWarningsHelp') }}
                                    </p>
                                    <FormTagsInput
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
                                                ? 'border-default bg-neutral-100 text-base-secondary cursor-not-allowed dark:bg-neutral-800'
                                                : 'border-default bg-surface text-base-primary placeholder-base-muted hover:border-strong focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500'
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
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
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
                                        class="mt-1 block w-full rounded-lg border border-default bg-surface px-4 py-2.5
                                               text-base-primary placeholder-base-muted
                                               hover:border-strong
                                               focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
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

                <!-- Submit Button -->
                <div class="mt-6 flex items-center justify-end gap-4">
                    <BaseButton
                        type="submit"
                        variant="primary"
                        :disabled="form.processing || !props.eligibility.eligible"
                        :loading="form.processing"
                    >
                        {{ t('gameTables.create.submit') }}
                    </BaseButton>
                </div>
            </form>
        </main>
    </DefaultLayout>
</template>
