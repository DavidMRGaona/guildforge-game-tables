import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CampaignJoinBox from '../../resources/js/components/campaigns/CampaignJoinBox.vue';
import { signIn } from './support/page';

describe('CampaignJoinBox', () => {
    it('names the configured role on a members-only roster', () => {
        signIn();

        const text = mount(CampaignJoinBox, {
            props: {
                joinBox: {
                    state: 'members_only',
                    requested_at: null,
                    roster_url: null,
                    pending_requests: 0,
                },
                campaignTitle: 'La cripta olvidada',
                campaignSlug: 'la-cripta-olvidada',
                associationRole: 'Miembros',
            },
        }).text();

        expect(text).toContain('Solo para el rol Miembros');
        expect(text).toContain('Esta campaña solo admite solicitudes del rol Miembros.');
        expect(text).not.toContain('socios');
    });
});
