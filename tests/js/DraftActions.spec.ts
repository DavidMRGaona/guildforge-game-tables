import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import DraftActions from '../../resources/js/components/DraftActions.vue';
import { signIn } from './support/page';

function draftActions(kind: 'table' | 'campaign', publishesWithoutReview?: boolean): VueWrapper {
    signIn();

    return mount(DraftActions, {
        props: { kind, routeKey: 'borrador', title: 'Borrador', publishesWithoutReview },
        global: { stubs: { teleport: true } },
    });
}

async function openSendDialog(wrapper: VueWrapper): Promise<string> {
    const send = wrapper
        .findAll('button')
        .find((button) => /Enviar a revisión|Publicar/.test(button.text()));
    expect(send).toBeDefined();
    await send!.trigger('click');

    return wrapper.text();
}

describe('DraftActions', () => {
    it('says a moderator will review a table that goes to review', async () => {
        const wrapper = draftActions('table');

        expect(wrapper.text()).toContain('Enviar a revisión');
        expect(await openSendDialog(wrapper)).toContain('revisada por un moderador');
    });

    it('says a table will be published when its author publishes without review', async () => {
        const wrapper = draftActions('table', true);

        expect(wrapper.text()).toContain('Publicar');
        expect(wrapper.text()).not.toContain('revisión');
        const dialog = await openSendDialog(wrapper);
        expect(dialog).toContain('Publicar mesa');
        expect(dialog).toContain('se publicará');
        expect(dialog).not.toContain('moderador');
    });

    it('says a campaign will be published when its author publishes without review', async () => {
        const dialog = await openSendDialog(draftActions('campaign', true));

        expect(dialog).toContain('Publicar campaña');
        expect(dialog).not.toContain('moderador');
    });
});
