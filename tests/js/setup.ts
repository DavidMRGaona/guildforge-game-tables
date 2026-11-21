import { afterEach, beforeEach, vi } from 'vitest';
import type { VNode } from 'vue';
import { config, enableAutoUnmount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import coreEs from '@/locales/es';
import moduleLocales from '../../resources/js/locales';
import { page } from './support/page';

// Inertia is the core's: its router and links are replaced by spies and plain anchors
vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');
    const { page: currentPage } = await import('./support/page');

    return {
        usePage: (): typeof currentPage => currentPage,
        router: { visit: vi.fn(), reload: vi.fn(), delete: vi.fn(), post: vi.fn(), get: vi.fn() },
        Link: defineComponent({
            // Inertia's own name, which the stand-in keeps
            // eslint-disable-next-line vue/multi-word-component-names, vue/no-reserved-component-names
            name: 'Link',
            props: { href: { type: String, required: true } },
            setup(props, { slots }) {
                return (): VNode => h('a', { href: props.href }, slots.default?.());
            },
        }),
    };
});

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

// No test reaches the network: a request nobody stubbed fails the test that made it, naming
// the address, instead of trying localhost and printing the refusal (#318). A spec that needs
// one stubs `fetch` itself, which replaces this one
const unexpectedRequests: string[] = [];

beforeEach(() => {
    page.props = {};
    unexpectedRequests.length = 0;
    vi.stubGlobal(
        'fetch',
        vi.fn((input: RequestInfo | URL): Promise<Response> => {
            const address =
                typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
            unexpectedRequests.push(address);

            return Promise.reject(new TypeError(`Unexpected network request to ${address}`));
        })
    );
});

afterEach(() => {
    vi.unstubAllGlobals();

    if (unexpectedRequests.length > 0) {
        throw new Error(
            `The test made network requests nobody stubbed: ${unexpectedRequests.join(', ')}`
        );
    }
});
