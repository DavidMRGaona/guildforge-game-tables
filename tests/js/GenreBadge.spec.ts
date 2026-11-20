import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import GenreBadge from '../../resources/js/components/GenreBadge.vue';

describe('GenreBadge', () => {
    it('shows the name of the genre as its text', () => {
        const wrapper = mount(GenreBadge, { props: { label: 'Ciencia ficción', color: 'info' } });

        expect(wrapper.text()).toBe('Ciencia ficción');
    });

    // The site's semantic tokens, which hold their contrast in light and dark mode: a module
    // never brings its own colour scale (its sheet goes below the core's)
    it.each([
        ['primary', ['bg-primary-light', 'text-base-primary']],
        ['info', ['bg-info-light', 'text-base-primary']],
        ['success', ['bg-success-light', 'text-base-primary']],
        ['warning', ['bg-warning-light', 'text-base-primary']],
        ['danger', ['bg-error-light', 'text-base-primary']],
        ['gray', ['bg-muted', 'text-base-secondary']],
    ])('draws the %s colour with the theme tokens', (color, classes) => {
        const wrapper = mount(GenreBadge, { props: { label: 'Terror', color } });

        expect(wrapper.classes()).toEqual(expect.arrayContaining(classes));
    });

    it('draws a colour it does not know in grey', () => {
        const wrapper = mount(GenreBadge, { props: { label: 'Terror', color: 'purple' } });

        expect(wrapper.classes()).toEqual(
            expect.arrayContaining(['bg-muted', 'text-base-secondary'])
        );
        expect(wrapper.classes().join(' ')).not.toContain('purple');
    });
});
