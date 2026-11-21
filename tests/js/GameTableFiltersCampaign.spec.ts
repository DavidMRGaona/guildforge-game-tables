import { describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import GameTableFilters from '../../resources/js/components/GameTableFilters.vue';
import type { GameTableFilters as Filters } from '../../resources/js/types/gametables';

const CAMPAIGN = { id: '0193a8f2-4c1e-7d3a-9b2f-1a2b3c4d5e6f', title: 'La maldición de Strahd' };

function filters(currentFilters: Filters = { campaign: CAMPAIGN.id }): VueWrapper {
    return mount(GameTableFilters, {
        props: {
            gameSystems: [],
            statuses: [{ value: 'scheduled', label: 'Programada' }],
            currentFilters,
            campaign: currentFilters.campaign ? CAMPAIGN : null,
            basePath: '/mesas',
        },
    });
}

async function chooseStatus(wrapper: VueWrapper): Promise<void> {
    const select = wrapper
        .findAllComponents({ name: 'FormSelect' })
        .find((component) => component.props('id') === 'status-filter');
    expect(select).toBeDefined();

    select?.vm.$emit('update:modelValue', 'scheduled');
    await nextTick();
}

describe('GameTableFilters campaign filter', () => {
    it('keeps the campaign when another filter changes', async () => {
        const wrapper = filters();
        vi.mocked(router.visit).mockClear();

        await chooseStatus(wrapper);

        expect(router.visit).toHaveBeenCalledWith(
            '/mesas',
            expect.objectContaining({
                data: expect.objectContaining({ campaign: CAMPAIGN.id, status: 'scheduled' }),
            })
        );
    });

    it('names the campaign in a pill that removes it', async () => {
        const wrapper = filters();

        const remove = wrapper.find(
            `button[aria-label="Quitar filtro: Campaña: ${CAMPAIGN.title}"]`
        );
        expect(remove.exists()).toBe(true);

        vi.mocked(router.visit).mockClear();
        await remove.trigger('click');
        await nextTick();

        expect(router.visit).toHaveBeenCalledWith(
            '/mesas',
            expect.objectContaining({
                data: expect.not.objectContaining({ campaign: expect.anything() }),
            })
        );
    });

    it('sends no campaign when the list has none', async () => {
        const wrapper = filters({});
        vi.mocked(router.visit).mockClear();

        await chooseStatus(wrapper);

        expect(router.visit).toHaveBeenCalledWith(
            '/mesas',
            expect.objectContaining({
                data: expect.not.objectContaining({ campaign: expect.anything() }),
            })
        );
        expect(wrapper.text()).not.toContain('Campaña:');
    });
});
