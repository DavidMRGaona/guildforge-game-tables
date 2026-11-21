import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import GameTableFilters from '../../resources/js/components/GameTableFilters.vue';
import Calendar from '../../resources/js/pages/GameTables/Calendar.vue';
import TablesViewToggle from '../../resources/js/components/TablesViewToggle.vue';
import { tableFilterQuery } from '../../resources/js/utils/tableFilterQuery';
import type { GameTableFilters as Filters } from '../../resources/js/types/gametables';

/**
 * The list and the calendar of tables share their filters (#314): switching views, changing the
 * month or changing a filter keeps the rest.
 */
const FILTERS: Filters = {
    systems: ['system-a', 'system-b'],
    format: 'online',
    event: 'jornadas',
    campaign: 'campaign-1',
    genre: 'horror',
};

describe('tableFilterQuery', () => {
    it('turns the current filters into the query the list and the calendar read', () => {
        expect(tableFilterQuery(FILTERS)).toEqual({
            systems: 'system-a,system-b',
            format: 'online',
            event: 'jornadas',
            campaign: 'campaign-1',
            genre: 'horror',
        });
    });

    it('leaves out what is not chosen', () => {
        expect(tableFilterQuery({ systems: [], genre: null, format: '' })).toEqual({});
    });
});

describe('TablesViewToggle', () => {
    function hrefs(props: Record<string, unknown>): string[] {
        return mount(TablesViewToggle, { props: { current: 'list', ...props } })
            .findAll('a')
            .map((link) => link.attributes('href') ?? '');
    }

    it('carries the filters to both views and the month only to the calendar', () => {
        const [list, calendar] = hrefs({
            filters: { genre: 'horror', systems: ['a', 'b'] },
            month: '2026-12',
        });

        expect(list).toBe('/mesas?systems=a%2Cb&genre=horror');
        expect(calendar).toBe('/mesas/calendario?systems=a%2Cb&genre=horror&month=2026-12');
    });

    it('links the bare views without filters', () => {
        expect(hrefs({})).toEqual(['/mesas', '/mesas/calendario']);
    });
});

describe('GameTableFilters on the calendar', () => {
    it('keeps the month shown when a filter changes', async () => {
        const wrapper = mount(GameTableFilters, {
            props: {
                gameSystems: [],
                genres: [{ value: 'horror', label: 'Terror' }],
                currentFilters: {},
                basePath: '/mesas/calendario',
                keepParams: { month: '2026-12' },
            },
        });
        vi.mocked(router.visit).mockClear();

        wrapper
            .findAllComponents({ name: 'FormSelect' })
            .find((component) => component.props('id') === 'genre-filter')
            ?.vm.$emit('update:modelValue', 'horror');
        await nextTick();

        expect(router.visit).toHaveBeenCalledWith(
            '/mesas/calendario',
            expect.objectContaining({ data: { month: '2026-12', genre: 'horror' } })
        );
    });
});

describe('Calendar month navigation', () => {
    it('keeps the filters when moving to another month', async () => {
        const wrapper = mount(Calendar, {
            props: {
                tables: [],
                gameSystems: [],
                currentFilters: { genre: 'horror', campaign: 'campaign-1' },
                currentMonth: '2026-11',
            },
            global: { stubs: { GameTableFilters: true, TablesViewToggle: true, HelpLink: true } },
        });
        vi.mocked(router.visit).mockClear();

        const next = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Siguiente'));
        expect(next).toBeDefined();
        await next?.trigger('click');

        expect(router.visit).toHaveBeenCalledWith('/mesas/calendario', {
            data: { genre: 'horror', campaign: 'campaign-1', month: '2026-12' },
        });
    });
});
