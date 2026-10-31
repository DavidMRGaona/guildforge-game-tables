<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { SubjectSurveyLinks } from '../types/gametables';

interface Props {
    links: SubjectSurveyLinks;
    /** The link to the subject's surveys page, for whoever manages it */
    manageLabel: string;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <!-- Plain links: the surveys' pages belong to another module -->
    <a
        v-if="links.manageUrl"
        :href="links.manageUrl"
        class="inline-flex min-h-[44px] items-center justify-center rounded-md border border-default px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
    >
        {{ manageLabel }}
    </a>
    <a
        v-for="survey in links.surveys"
        :key="survey.url"
        :href="survey.url"
        class="inline-flex min-h-[44px] items-center justify-center rounded-md px-4 py-2 text-center text-sm font-medium break-words transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
        :class="
            survey.action === 'respond'
                ? 'bg-primary text-white hover:bg-primary-700'
                : 'border border-default text-base-primary hover:bg-muted'
        "
    >
        {{
            survey.action === 'respond'
                ? t('gameTables.surveys.respond', { title: survey.title })
                : t('gameTables.surveys.results', { title: survey.title })
        }}
    </a>
</template>
