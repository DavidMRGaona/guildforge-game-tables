import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import Invitation from '../../resources/js/pages/Campaigns/Invitation.vue';
import { page, signIn } from './support/page';

// The core's sign-out forgets this browser's push subscription before posting: the test decides
// when that ends, to see what happens in between
const forgetting = vi.hoisted(() => ({ finish: (): void => {} }));

vi.mock('@/utils/webPush', () => ({
    forgetThisDevice: vi.fn(
        () =>
            new Promise<void>((resolve) => {
                forgetting.finish = resolve;
            })
    ),
}));

const token = 'a'.repeat(64);

function mountMismatch(): ReturnType<typeof mount> {
    signIn();
    page.props = { ...page.props, webPush: { publicKey: 'public-key' } };

    return mount(Invitation, {
        props: {
            state: 'mismatch',
            invitation: {
                campaign_title: 'La cripta olvidada',
                campaign_slug: 'la-cripta-olvidada',
                inviter_name: 'Jane Doe',
            },
            token,
        },
    });
}

describe('Campaign invitation sign-out', () => {
    it('forgets this browser first, then signs out through the invitation route', async () => {
        const { forgetThisDevice } = await import('@/utils/webPush');
        const wrapper = mountMismatch();

        const button = wrapper.findAll('button').find((b) => b.text() === 'Cerrar sesión');
        expect(button).toBeDefined();
        await button?.trigger('click');
        await flushPromises();

        expect(forgetThisDevice).toHaveBeenCalledWith('user-1');
        expect(router.post).not.toHaveBeenCalled();

        forgetting.finish();
        await flushPromises();

        expect(router.post).toHaveBeenCalledTimes(1);
        expect(router.post).toHaveBeenCalledWith(`/campanas/invitaciones/${token}/cerrar-sesion`);
    });
});
