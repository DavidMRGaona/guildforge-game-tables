import { describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import GameTableFilters from '../../resources/js/components/GameTableFilters.vue';
import type {
    FilterOption,
    GameTableFilters as Filters,
} from '../../resources/js/types/gametables';

const genres: FilterOption[] = [
    { value: 'horror', label: 'Terror' },
    { value: 'mystery', label: 'Misterio' },
];

function filters(currentFilters: Filters = {}, offered: FilterOption[] = genres): VueWrapper {
    return mount(GameTableFilters, {
        props: { gameSystems: [], genres: offered, currentFilters, basePath: '/mesas' },
    });
}

describe('GameTableFilters genre filter', () => {
    it('offers the genres under their label', () => {
        const wrapper = filters();

        expect(wrapper.find('label[for="genre-filter"]').text()).toBe('Género');
        expect(wrapper.find('#genre-filter').exists()).toBe(true);
    });

    it('hides the filter when the list has no genres', () => {
        expect(filters({}, []).find('#genre-filter').exists()).toBe(false);
    });

    it('sends the chosen genre as the genre parameter', async () => {
        const wrapper = filters();

        const select = wrapper
            .findAllComponents({ name: 'FormSelect' })
            .find((component) => component.props('id') === 'genre-filter');
        expect(select).toBeDefined();

        select?.vm.$emit('update:modelValue', 'horror');
        await nextTick();

        expect(router.visit).toHaveBeenCalledWith(
            '/mesas',
            expect.objectContaining({ data: expect.objectContaining({ genre: 'horror' }) })
        );
    });

    it('shows the chosen genre as a pill that removes it', async () => {
        const wrapper = filters({ genre: 'mystery' });

        // The select shows «Misterio» too: the pill is the one with the button that removes it
        const remove = wrapper.find('button[aria-label="Quitar filtro: Misterio"]');
        expect(remove.exists()).toBe(true);

        vi.mocked(router.visit).mockClear();
        await remove.trigger('click');
        await nextTick();

        expect(router.visit).toHaveBeenCalledWith(
            '/mesas',
            expect.objectContaining({
                data: expect.not.objectContaining({ genre: expect.anything() }),
            })
        );
    });
});
