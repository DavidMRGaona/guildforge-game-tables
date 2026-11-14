import { afterEach, describe, expect, it, vi } from 'vitest';

/**
 * The display zone is read once, when `@/utils/datetime` is imported, so each case
 * announces its zone and loads every module again from scratch.
 */
async function mountCalendar(
    zone: string,
    startsAt: string
): Promise<{ day: string; month: string }> {
    vi.resetModules();

    const meta = document.createElement('meta');
    meta.name = 'display-timezone';
    meta.content = zone;
    document.head.appendChild(meta);

    const { config, mount } = await import('@vue/test-utils');
    const { createI18n } = await import('vue-i18n');
    const { default: coreEs } = await import('@/locales/es');
    const { default: moduleLocales } = await import('../../resources/js/locales');
    const { default: Calendar } = await import('../../resources/js/pages/GameTables/Calendar.vue');

    const i18n = createI18n({
        legacy: false,
        locale: 'es',
        fallbackLocale: 'es',
        messages: { es: { ...coreEs, ...moduleLocales.es } },
    });

    // Replaces the setup's plugin instead of adding a second i18n to the same app
    config.global.plugins = [i18n];

    const wrapper = mount(Calendar, {
        props: {
            tables: [{ id: 'table-1', startsAt }] as never,
            gameSystems: [],
            currentMonth: '2026-11',
        },
        global: {
            stubs: { GameTableCard: true, TablesViewToggle: true, HelpLink: true },
        },
    });

    return { day: wrapper.find('h3').text(), month: wrapper.find('h2').text() };
}

afterEach(() => {
    document.head
        .querySelectorAll('meta[name="display-timezone"]')
        .forEach((node) => node.remove());
});

describe('Calendar day headings', () => {
    // Thursday 19 November 2026 at 20:00 in the venue, expressed in UTC
    it.each([
        ['Europe/Madrid', '2026-11-19T19:00:00Z'],
        ['Pacific/Auckland', '2026-11-19T07:00:00Z'],
        ['Pacific/Kiritimati', '2026-11-19T06:00:00Z'],
        ['America/Los_Angeles', '2026-11-20T04:00:00Z'],
    ])(
        'names the weekday and day of the group in %s',
        async (zone, startsAt) => {
            const { day, month } = await mountCalendar(zone, startsAt);

            expect(day).toBe('jueves, 19');
            expect(month).toBe('Noviembre de 2026');
        },
        60_000
    );
});
