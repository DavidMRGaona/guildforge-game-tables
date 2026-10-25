<script setup lang="ts">
import { Calendar, List } from '@lucide/vue';
// Switches the tables between the list (/mesas) and the calendar (/mesas/calendario), on every
// screen size: two links, the current one marked with aria-current
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface Props {
    current: 'list' | 'calendar';
}

defineProps<Props>();

const { t } = useI18n();

const baseClass =
    'inline-flex min-h-[44px] min-w-[44px] items-center justify-center border px-3 py-2 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface';
const activeClass = 'border-primary-600 bg-primary text-white';
const inactiveClass =
    'border-default bg-transparent text-base-secondary hover:bg-base-secondary/10';
</script>

<template>
    <nav :aria-label="t('gameTables.filters.viewSwitch')" class="flex shrink-0 items-center">
        <Link
            href="/mesas"
            :aria-current="current === 'list' ? 'page' : undefined"
            :aria-label="t('gameTables.filters.listView')"
            :class="[baseClass, 'rounded-l-lg', current === 'list' ? activeClass : inactiveClass]"
        >
            <List class="h-5 w-5" aria-hidden="true" />
        </Link>
        <Link
            href="/mesas/calendario"
            :aria-current="current === 'calendar' ? 'page' : undefined"
            :aria-label="t('gameTables.filters.calendarView')"
            :class="[
                baseClass,
                'rounded-r-lg',
                current === 'calendar' ? activeClass : inactiveClass,
            ]"
        >
            <Calendar class="h-5 w-5" aria-hidden="true" />
        </Link>
    </nav>
</template>
