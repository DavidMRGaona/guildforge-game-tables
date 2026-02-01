<script setup lang="ts">
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useSeo } from '@/composables/useSeo';
import type { GameSystem, GameTable } from '../../types/gametables';

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

interface Props {
    table: GameTable;
    formData: FormData;
}

const props = defineProps<Props>();

const { t } = useI18n();

useSeo({
    title: t('gameTables.edit.title'),
});

// Pre-populate form with existing table data
const form = useForm({
    game_system_id: props.table.gameSystemId,
    title: props.table.title,
    starts_at: props.table.startsAt.substring(0, 16), // Format for datetime-local input
    duration_minutes: props.table.durationMinutes,
    table_type: props.table.tableType,
    table_format: props.table.tableFormat,
    min_players: props.table.minPlayers,
    max_players: props.table.maxPlayers,
    max_spectators: props.table.maxSpectators,
    synopsis: props.table.synopsis ?? '',
    location: props.table.location ?? '',
    online_url: props.table.onlineUrl ?? '',
    language: props.table.language,
    experience_level: props.table.experienceLevel ?? '',
    character_creation: props.table.characterCreation ?? '',
    genres: props.table.genres,
    tone: props.table.tone ?? '',
    safety_tools: props.table.safetyTools,
    content_warnings: props.table.contentWarnings,
    custom_warnings: props.table.customWarnings,
    minimum_age: props.table.minimumAge,
    notes: props.table.notes ?? '',
});

const isOnline = computed(() => form.table_format === 'online');
const isInPerson = computed(() => form.table_format === 'in_person');
const isHybrid = computed(() => form.table_format === 'hybrid');

const showLocation = computed(() => isInPerson.value || isHybrid.value);
const showOnlineUrl = computed(() => isOnline.value || isHybrid.value);

function submit(): void {
    form.put(`/mesas/mis-mesas/${props.table.id}`, {
        preserveScroll: true,
    });
}

function cancel(): void {
    router.visit('/mesas/mis-mesas');
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-white shadow dark:bg-stone-800 dark:shadow-stone-900/50">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                    {{ t('gameTables.edit.title') }}
                </h1>
                <p class="mt-2 text-lg text-stone-600 dark:text-stone-400">
                    {{ t('gameTables.edit.subtitle') }}
                </p>
            </div>
        </div>

        <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
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

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-4">
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
                        :disabled="form.processing"
                        :loading="form.processing"
                    >
                        {{ t('gameTables.edit.submit') }}
                    </BaseButton>
                </div>
            </form>
        </main>
    </DefaultLayout>
</template>
