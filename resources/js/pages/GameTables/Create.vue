<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import type { GameSystem } from '../../types/gametables';

interface FormData {
    game_systems: GameSystem[];
    table_types: Array<{ value: string; label: string }>;
    table_formats: Array<{ value: string; label: string }>;
    languages: Array<{ value: string; label: string }>;
    experience_levels: Array<{ value: string; label: string }>;
    character_creation_options: Array<{ value: string; label: string }>;
    tones: Array<{ value: string; label: string }>;
    genres: string[];
    safety_tools: string[];
    content_warnings: string[];
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
    title: t('gameTables.create.title'),
});

const form = useForm({
    game_system_id: '',
    title: '',
    starts_at: '',
    duration_minutes: 240,
    table_type: 'one_shot',
    table_format: 'in_person',
    min_players: 3,
    max_players: 5,
    max_spectators: 0,
    synopsis: '',
    location: '',
    online_url: '',
    language: 'es',
    experience_level: '',
    character_creation: '',
    genres: [] as string[],
    tone: '',
    safety_tools: [] as string[],
    content_warnings: [] as string[],
    custom_warnings: [] as string[],
    minimum_age: null as number | null,
    notes: '',
});

const isOnline = computed(() => form.table_format === 'online');
const isInPerson = computed(() => form.table_format === 'in_person');
const isHybrid = computed(() => form.table_format === 'hybrid');

const showLocation = computed(() => isInPerson.value || isHybrid.value);
const showOnlineUrl = computed(() => isOnline.value || isHybrid.value);

function submit(): void {
    form.post('/mesas', {
        preserveScroll: true,
    });
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                    {{ t('gameTables.create.title') }}
                </h1>
                <p class="mt-2 text-lg text-stone-600 dark:text-stone-400">
                    {{ t('gameTables.create.subtitle') }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Eligibility check -->
            <div v-if="!props.eligibility.eligible" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            {{ props.eligibility.reason }}
                        </p>
                        <p v-if="props.eligibility.canCreateAt" class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            {{ t('gameTables.create.canCreateAt', { date: props.eligibility.canCreateAt }) }}
                        </p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                <!-- Basic Information -->
                <div class="rounded-lg border border-stone-200 bg-white p-6 dark:border-stone-700 dark:bg-stone-800">
                    <h2 class="mb-4 text-xl font-semibold text-stone-900 dark:text-stone-100">
                        {{ t('gameTables.create.sections.basic') }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Game System -->
                        <div>
                            <label for="game_system_id" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.gameSystem') }}
                            </label>
                            <select
                                id="game_system_id"
                                v-model="form.game_system_id"
                                required
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            >
                                <option value="">{{ t('gameTables.create.fields.selectGameSystem') }}</option>
                                <option v-for="system in props.formData.game_systems" :key="system.id" :value="system.id">
                                    {{ system.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.game_system_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.game_system_id }}
                            </p>
                        </div>

                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.title') }}
                            </label>
                            <input
                                id="title"
                                v-model="form.title"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <!-- Starts At -->
                        <div>
                            <label for="starts_at" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.startsAt') }}
                            </label>
                            <input
                                id="starts_at"
                                v-model="form.starts_at"
                                type="datetime-local"
                                required
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p v-if="form.errors.starts_at" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.starts_at }}
                            </p>
                        </div>

                        <!-- Duration -->
                        <div>
                            <label for="duration_minutes" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.duration') }}
                            </label>
                            <input
                                id="duration_minutes"
                                v-model.number="form.duration_minutes"
                                type="number"
                                min="30"
                                step="30"
                                required
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                                {{ t('gameTables.create.fields.durationHelp') }}
                            </p>
                            <p v-if="form.errors.duration_minutes" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.duration_minutes }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="rounded-lg border border-stone-200 bg-white p-6 dark:border-stone-700 dark:bg-stone-800">
                    <h2 class="mb-4 text-xl font-semibold text-stone-900 dark:text-stone-100">
                        {{ t('gameTables.create.sections.details') }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Table Type -->
                        <div>
                            <label for="table_type" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.tableType') }}
                            </label>
                            <select
                                id="table_type"
                                v-model="form.table_type"
                                required
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            >
                                <option v-for="type in props.formData.table_types" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.table_type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.table_type }}
                            </p>
                        </div>

                        <!-- Table Format -->
                        <div>
                            <label for="table_format" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.tableFormat') }}
                            </label>
                            <select
                                id="table_format"
                                v-model="form.table_format"
                                required
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            >
                                <option v-for="format in props.formData.table_formats" :key="format.value" :value="format.value">
                                    {{ format.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.table_format" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.table_format }}
                            </p>
                        </div>

                        <!-- Players Range -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="min_players" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                    {{ t('gameTables.create.fields.minPlayers') }}
                                </label>
                                <input
                                    id="min_players"
                                    v-model.number="form.min_players"
                                    type="number"
                                    min="1"
                                    required
                                    class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                                />
                                <p v-if="form.errors.min_players" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.min_players }}
                                </p>
                            </div>
                            <div>
                                <label for="max_players" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                    {{ t('gameTables.create.fields.maxPlayers') }}
                                </label>
                                <input
                                    id="max_players"
                                    v-model.number="form.max_players"
                                    type="number"
                                    min="1"
                                    required
                                    class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                                />
                                <p v-if="form.errors.max_players" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.max_players }}
                                </p>
                            </div>
                        </div>

                        <!-- Max Spectators -->
                        <div>
                            <label for="max_spectators" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.maxSpectators') }}
                            </label>
                            <input
                                id="max_spectators"
                                v-model.number="form.max_spectators"
                                type="number"
                                min="0"
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p v-if="form.errors.max_spectators" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.max_spectators }}
                            </p>
                        </div>

                        <!-- Language -->
                        <div>
                            <label for="language" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.language') }}
                            </label>
                            <select
                                id="language"
                                v-model="form.language"
                                required
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            >
                                <option v-for="lang in props.formData.languages" :key="lang.value" :value="lang.value">
                                    {{ lang.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.language" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.language }}
                            </p>
                        </div>

                        <!-- Experience Level -->
                        <div>
                            <label for="experience_level" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.experienceLevel') }}
                            </label>
                            <select
                                id="experience_level"
                                v-model="form.experience_level"
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            >
                                <option value="">{{ t('gameTables.create.fields.selectExperienceLevel') }}</option>
                                <option v-for="level in props.formData.experience_levels" :key="level.value" :value="level.value">
                                    {{ level.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.experience_level" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.experience_level }}
                            </p>
                        </div>

                        <!-- Minimum Age -->
                        <div>
                            <label for="minimum_age" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.minimumAge') }}
                            </label>
                            <input
                                id="minimum_age"
                                v-model.number="form.minimum_age"
                                type="number"
                                min="0"
                                placeholder="18"
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p v-if="form.errors.minimum_age" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.minimum_age }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="rounded-lg border border-stone-200 bg-white p-6 dark:border-stone-700 dark:bg-stone-800">
                    <h2 class="mb-4 text-xl font-semibold text-stone-900 dark:text-stone-100">
                        {{ t('gameTables.create.sections.description') }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Synopsis -->
                        <div>
                            <label for="synopsis" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.synopsis') }}
                            </label>
                            <textarea
                                id="synopsis"
                                v-model="form.synopsis"
                                rows="6"
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p v-if="form.errors.synopsis" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.synopsis }}
                            </p>
                        </div>

                        <!-- Character Creation -->
                        <div>
                            <label for="character_creation" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.characterCreation') }}
                            </label>
                            <select
                                id="character_creation"
                                v-model="form.character_creation"
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            >
                                <option value="">{{ t('gameTables.create.fields.selectCharacterCreation') }}</option>
                                <option v-for="option in props.formData.character_creation_options" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.character_creation" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.character_creation }}
                            </p>
                        </div>

                        <!-- Tone -->
                        <div>
                            <label for="tone" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.tone') }}
                            </label>
                            <select
                                id="tone"
                                v-model="form.tone"
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            >
                                <option value="">{{ t('gameTables.create.fields.selectTone') }}</option>
                                <option v-for="tone in props.formData.tones" :key="tone.value" :value="tone.value">
                                    {{ tone.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.tone" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.tone }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="rounded-lg border border-stone-200 bg-white p-6 dark:border-stone-700 dark:bg-stone-800">
                    <h2 class="mb-4 text-xl font-semibold text-stone-900 dark:text-stone-100">
                        {{ t('gameTables.create.sections.location') }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Location (for in-person/hybrid) -->
                        <div v-if="showLocation">
                            <label for="location" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.location') }}
                            </label>
                            <input
                                id="location"
                                v-model="form.location"
                                type="text"
                                :required="isInPerson"
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p v-if="form.errors.location" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.location }}
                            </p>
                        </div>

                        <!-- Online URL (for online/hybrid) -->
                        <div v-if="showOnlineUrl">
                            <label for="online_url" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.onlineUrl') }}
                            </label>
                            <input
                                id="online_url"
                                v-model="form.online_url"
                                type="url"
                                :required="isOnline"
                                placeholder="https://..."
                                class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                            />
                            <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                                {{ t('gameTables.create.fields.onlineUrlHelp') }}
                            </p>
                            <p v-if="form.errors.online_url" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.online_url }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Safety & Content -->
                <div class="rounded-lg border border-stone-200 bg-white p-6 dark:border-stone-700 dark:bg-stone-800">
                    <h2 class="mb-4 text-xl font-semibold text-stone-900 dark:text-stone-100">
                        {{ t('gameTables.create.sections.safety') }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Safety Tools -->
                        <div>
                            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.safetyTools') }}
                            </label>
                            <div class="mt-2 space-y-2">
                                <div v-for="tool in props.formData.safety_tools" :key="tool" class="flex items-start">
                                    <input
                                        :id="`safety_tool_${tool}`"
                                        v-model="form.safety_tools"
                                        :value="tool"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-stone-300 dark:border-stone-600 text-amber-600 focus:ring-amber-500"
                                    />
                                    <label :for="`safety_tool_${tool}`" class="ml-2 text-sm text-stone-700 dark:text-stone-300">
                                        {{ tool }}
                                    </label>
                                </div>
                            </div>
                            <p v-if="form.errors.safety_tools" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.safety_tools }}
                            </p>
                        </div>

                        <!-- Content Warnings -->
                        <div>
                            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                                {{ t('gameTables.create.fields.contentWarnings') }}
                            </label>
                            <div class="mt-2 space-y-2">
                                <div v-for="warning in props.formData.content_warnings" :key="warning" class="flex items-start">
                                    <input
                                        :id="`content_warning_${warning}`"
                                        v-model="form.content_warnings"
                                        :value="warning"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-stone-300 dark:border-stone-600 text-amber-600 focus:ring-amber-500"
                                    />
                                    <label :for="`content_warning_${warning}`" class="ml-2 text-sm text-stone-700 dark:text-stone-300">
                                        {{ warning }}
                                    </label>
                                </div>
                            </div>
                            <p v-if="form.errors.content_warnings" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.content_warnings }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="rounded-lg border border-stone-200 bg-white p-6 dark:border-stone-700 dark:bg-stone-800">
                    <h2 class="mb-4 text-xl font-semibold text-stone-900 dark:text-stone-100">
                        {{ t('gameTables.create.sections.notes') }}
                    </h2>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-stone-700 dark:text-stone-300">
                            {{ t('gameTables.create.fields.notes') }}
                        </label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="4"
                            class="mt-1 block w-full rounded-md border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-amber-500"
                        />
                        <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                            {{ t('gameTables.create.fields.notesHelp') }}
                        </p>
                        <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.notes }}
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-4">
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
