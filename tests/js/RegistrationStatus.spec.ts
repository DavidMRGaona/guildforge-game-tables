import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RegistrationStatus from '../../resources/js/components/RegistrationStatus.vue';
import type { ParticipantData } from '../../resources/js/types/registration';

function waitingRegistration(): ParticipantData {
    return {
        id: 'participant-1',
        gameTableId: 'table-1',
        userId: 'user-1',
        userName: 'John Doe',
        role: 'player',
        roleLabel: 'Jugador',
        status: 'waiting_list',
        statusLabel: 'En lista de espera',
        statusColor: 'blue',
        waitingListPosition: 2,
        notes: null,
        confirmedAt: null,
        createdAt: '2026-11-01T10:00:00Z',
    };
}

describe('RegistrationStatus', () => {
    it('names the waiting list above the position, not its translation key', () => {
        const wrapper = mount(RegistrationStatus, {
            props: { registration: waitingRegistration() },
        });

        expect(wrapper.text()).toContain('Lista de espera');
        expect(wrapper.text()).toContain('#2');
        expect(wrapper.text()).not.toContain('gameTables.');
    });
});
