import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PlayedTablesProfileSection from '../../resources/js/components/profileSections/PlayedTablesProfileSection.vue';

const table = (
    id: string,
    title: string
): {
    id: string;
    title: string;
    url: string;
    startsAt: string;
    gameSystem: string;
} => ({
    id,
    title,
    url: `/mesas/${id}`,
    startsAt: '2026-11-10T17:00:00+00:00',
    gameSystem: 'Sistema de ejemplo',
});

describe('PlayedTablesProfileSection', () => {
    it('lists the tables in the order given, each linking to its page', () => {
        const wrapper = mount(PlayedTablesProfileSection, {
            props: { tables: [table('el-faro', 'El faro'), table('la-torre', 'La torre')] },
        });

        const links = wrapper.findAll('a');
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/mesas/el-faro',
            '/mesas/la-torre',
        ]);
        expect(links[0]?.text()).toContain('El faro');
        expect(links[0]?.text()).toContain('Sistema de ejemplo');
        expect(wrapper.get('time').attributes('datetime')).toBe('2026-11-10T17:00:00+00:00');
        expect(wrapper.get('time').text()).toContain('2026');
    });

    it('leaves out a missing date and game system', () => {
        const wrapper = mount(PlayedTablesProfileSection, {
            props: { tables: [{ ...table('t', 'Sin fecha'), startsAt: null, gameSystem: '' }] },
        });

        expect(wrapper.find('time').exists()).toBe(false);
        expect(wrapper.find('.text-primary').exists()).toBe(false);
    });

    it('draws titles as text, never as HTML', () => {
        const wrapper = mount(PlayedTablesProfileSection, {
            props: { tables: [table('t', '<img src=x onerror=alert(1)>')] },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
    });
});
