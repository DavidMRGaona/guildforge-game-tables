import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import EventCreateTableButton from '../../resources/js/components/EventCreateTableButton.vue';
import { page, signIn } from './support/page';

const event = { id: 'event-1', slug: 'jornadas' };

/** Creation opens on the 20th for everyone, earlier for the early access tier. */
function waitingEligibility(userHasEarlyAccess: boolean): Record<string, unknown> {
    return {
        eligible: false,
        reason: null,
        can_create_at: userHasEarlyAccess ? '2026-12-17T09:00:00Z' : '2026-12-20T09:00:00Z',
        can_create_tables: false,
        can_create_campaigns: false,
        has_early_access: true,
        public_open_date: '2026-12-20T09:00:00Z',
        requires_authentication: false,
        user_has_early_access: userHasEarlyAccess,
    };
}

describe('EventCreateTableButton', () => {
    it('tells someone in the early access tier when their early access starts', () => {
        signIn();
        page.props.eventCreationEligibility = waitingEligibility(true);

        const text = mount(EventCreateTableButton, { props: { event } }).text();

        expect(text).toContain('Acceso anticipado activo desde');
        expect(text).toContain('17');
    });

    it('does not show early access to someone outside the tier', () => {
        signIn();
        page.props.eventCreationEligibility = waitingEligibility(false);

        const text = mount(EventCreateTableButton, { props: { event } }).text();

        expect(text).not.toContain('Acceso anticipado');
        expect(text).toContain('Podrás crear a partir del');
        expect(text).toContain('20');
    });

    it('tells a guest when table creation opens, not registration', () => {
        page.props.eventCreationEligibility = {
            ...waitingEligibility(false),
            has_early_access: false,
        };

        const text = mount(EventCreateTableButton, { props: { event } }).text();

        expect(text).toContain('Apertura de la creación de partidas');
        expect(text).not.toContain('inscripciones');
    });
});
