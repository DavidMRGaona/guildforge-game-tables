import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import StartsAtField from '../../resources/js/components/StartsAtField.vue';
import { joinWallDateTime, splitWallDateTime } from '../../resources/js/utils/wallDateTime';

function field(modelValue: string, error = ''): VueWrapper {
    return mount(StartsAtField, { props: { modelValue, error }, attachTo: document.body });
}

function dateInput(wrapper: VueWrapper): HTMLInputElement {
    return wrapper.get('#starts_at').element as HTMLInputElement;
}

function timeInput(wrapper: VueWrapper): HTMLInputElement {
    return wrapper.get('#starts_at_time').element as HTMLInputElement;
}

function emitted(wrapper: VueWrapper): string[] {
    return (wrapper.emitted('update:modelValue') ?? []).map(([value]) => value as string);
}

describe('wall date and time', () => {
    it('splits the wall time the server sends', () => {
        expect(splitWallDateTime('2026-12-05T18:30')).toEqual({
            date: '2026-12-05',
            time: '18:30',
        });
    });

    it('reads a time slot start as the wall time it names, offset aside', () => {
        expect(splitWallDateTime('2026-12-05T18:30:00+01:00')).toEqual({
            date: '2026-12-05',
            time: '18:30',
        });
    });

    it('splits nothing into two empty fields', () => {
        expect(splitWallDateTime('')).toEqual({ date: '', time: '' });
    });

    it('joins a day and a time into the wall time the server reads', () => {
        expect(joinWallDateTime('2026-12-05', '09:00')).toBe('2026-12-05T09:00');
    });

    it('sends nothing while the day or the time is missing', () => {
        expect(joinWallDateTime('2026-12-05', '')).toBe('');
        expect(joinWallDateTime('', '18:30')).toBe('');
    });

    it('sends what was typed when it is not a day, for the server to reject', () => {
        expect(joinWallDateTime('31/02', '18:30')).toBe('31/02T18:30');
    });
});

describe('StartsAtField', () => {
    it('shows the table start as a day and a time, both labelled', () => {
        const wrapper = field('2026-12-05T18:30');

        expect(dateInput(wrapper).value).toBe('05/12/2026');
        expect(timeInput(wrapper).value).toBe('18:30');
        expect(wrapper.get('label[for="starts_at"]').text()).toContain('Fecha de inicio');
        expect(wrapper.get('label[for="starts_at_time"]').text()).toContain('Hora de inicio');
    });

    it('sends the day and the time together as the wall time', async () => {
        const wrapper = field('');

        await wrapper.get('#starts_at').setValue('05/12/2026');
        expect(emitted(wrapper).at(-1)).toBe('');

        await wrapper.get('#starts_at_time').setValue('18:30');
        expect(emitted(wrapper).at(-1)).toBe('2026-12-05T18:30');
    });

    it('keeps the time when the day changes', async () => {
        const wrapper = field('2026-12-05T18:30');

        await wrapper.get('#starts_at').setValue('06/12/2026');

        expect(emitted(wrapper).at(-1)).toBe('2026-12-06T18:30');
        expect(timeInput(wrapper).value).toBe('18:30');
    });

    it('follows a start set from outside, such as a time slot', async () => {
        const wrapper = field('');

        await wrapper.setProps({ modelValue: '2026-12-07T10:00:00+01:00' });

        expect(dateInput(wrapper).value).toBe('07/12/2026');
        expect(timeInput(wrapper).value).toBe('10:00');
    });

    it('ties the server error to both fields', () => {
        const wrapper = field('', 'La fecha de inicio es obligatoria.');
        const error = wrapper.get('[data-testid="starts-at-error"]');

        expect(error.text()).toBe('La fecha de inicio es obligatoria.');
        for (const input of [dateInput(wrapper), timeInput(wrapper)]) {
            expect(input.getAttribute('aria-invalid')).toBe('true');
            expect(input.getAttribute('aria-describedby')).toContain(error.attributes('id'));
        }
    });
});
