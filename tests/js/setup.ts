import { afterEach } from 'vitest';
import { config, enableAutoUnmount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import coreEs from '@/locales/es';
import moduleLocales from '../../resources/js/locales';

// The core's texts with the module's on top, in Spanish, as the site loads them
config.global.plugins = [
    createI18n({
        legacy: false,
        locale: 'es',
        fallbackLocale: 'es',
        messages: { es: { ...coreEs, ...moduleLocales.es } },
    }),
];

// Every mounted component is unmounted after its test, attached ones included
enableAutoUnmount(afterEach);
