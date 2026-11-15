import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import TablesProfileSection from '../../resources/js/components/profileSections/TablesProfileSection.vue';

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
    startsAt: '2026-11-20T17:00:00+00:00',
    gameSystem: 'Sistema de ejemplo',
});

describe('TablesProfileSection', () => {
    it('lists the tables the owner runs and plays in, each linking to its page', () => {
        const wrapper = mount(TablesProfileSection, {
            props: {
                directing: [table('la-torre', 'La torre')],
                playing: [table('el-faro', 'El faro')],
            },
        });

        const directing = wrapper.get('[data-group="directing"]');
        const playing = wrapper.get('[data-group="playing"]');
        expect(directing.get('h3').text()).toBe('Dirige');
        expect(directing.get('a').attributes('href')).toBe('/mesas/la-torre');
        expect(directing.text()).toContain('La torre');
        expect(directing.text()).toContain('Sistema de ejemplo');
        expect(directing.get('time').attributes('datetime')).toBe('2026-11-20T17:00:00+00:00');
        expect(playing.get('h3').text()).toBe('Juega');
        expect(playing.get('a').attributes('href')).toBe('/mesas/el-faro');
    });

    it('leaves out an empty group', () => {
        const wrapper = mount(TablesProfileSection, {
            props: { directing: [], playing: [table('el-faro', 'El faro')] },
        });

        expect(wrapper.find('[data-group="directing"]').exists()).toBe(false);
        expect(wrapper.findAll('h3').map((heading) => heading.text())).toEqual(['Juega']);
    });

    it('draws titles as text, never as HTML', () => {
        const wrapper = mount(TablesProfileSection, {
            props: { directing: [table('t', '<img src=x onerror=alert(1)>')], playing: [] },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
    });

    it('tells the owner how many tables they run stay out, and nobody else', () => {
        const owner = mount(TablesProfileSection, {
            props: { directing: [], playing: [], hiddenDirecting: 2 },
        });
        const other = mount(TablesProfileSection, {
            props: { directing: [table('la-torre', 'La torre')], playing: [] },
        });

        expect(owner.get('[data-note="hidden-directing"]').text()).toBe(
            '2 mesas que diriges no salen aquí porque tu nombre de director no es público.'
        );
        expect(other.find('[data-note="hidden-directing"]').exists()).toBe(false);
    });
});
