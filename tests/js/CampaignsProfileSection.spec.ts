import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CampaignsProfileSection from '../../resources/js/components/profileSections/CampaignsProfileSection.vue';

describe('CampaignsProfileSection', () => {
    it('lists each campaign with its system, status and the owner role, linking to its page', () => {
        const wrapper = mount(CampaignsProfileSection, {
            props: {
                campaigns: [
                    {
                        id: '1',
                        title: 'Las crónicas',
                        url: '/campanas/las-cronicas',
                        gameSystem: 'Sistema de ejemplo',
                        status: 'Activa',
                        role: 'director' as const,
                    },
                    {
                        id: '2',
                        title: 'La plantilla',
                        url: '/campanas/la-plantilla',
                        gameSystem: '',
                        status: 'Reclutando',
                        role: 'player' as const,
                    },
                ],
            },
        });

        const items = wrapper.findAll('li');
        expect(items).toHaveLength(2);
        expect(items[0]?.get('a').attributes('href')).toBe('/campanas/las-cronicas');
        expect(items[0]?.text()).toContain('Las crónicas');
        expect(items[0]?.text()).toContain('Sistema de ejemplo');
        expect(items[0]?.text()).toContain('Activa');
        expect(items[0]?.text()).toContain('Dirige');
        expect(items[1]?.text()).toContain('Juega');
        expect(items[1]?.text()).not.toContain('·');
    });

    it('draws titles, systems and statuses as text, never as HTML', () => {
        const wrapper = mount(CampaignsProfileSection, {
            props: {
                campaigns: [
                    {
                        id: '1',
                        title: '<img src=x onerror=alert(1)>',
                        url: '/campanas/x',
                        gameSystem: '<b>Sistema</b>',
                        status: '<script>alert(1)</script>',
                        role: 'director' as const,
                    },
                ],
            },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('b').exists()).toBe(false);
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.text()).toContain('<b>Sistema</b>');
    });
});
