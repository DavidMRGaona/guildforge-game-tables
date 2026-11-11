import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import CampaignShow from '../../resources/js/pages/Campaigns/Show.vue';
import GameTableShow from '../../resources/js/pages/GameTables/Show.vue';
import type { Campaign, GameTable, JoinBox } from '../../resources/js/types/gametables';

// What a forum module registers in `subject-discussion`, or nothing at all
const slot = vi.hoisted(() => ({ rendersContent: false, registered: true }));

vi.mock('@/composables/useModuleSlots', async () => {
    const { defineComponent: define, h: render } = await import('vue');

    const Conversation = define({
        setup: () => (): ReturnType<typeof render> | null =>
            slot.rendersContent
                ? render('section', { 'data-testid': 'conversation' }, 'Hilo')
                : null,
    });

    return {
        useModuleSlots: (): unknown => ({
            getSlotComponents: (name: string) =>
                name === 'subject-discussion' && slot.registered
                    ? [{ key: 'forum:discussion', component: Conversation, props: {} }]
                    : [],
            hasSlotComponents: (name: string) => name === 'subject-discussion' && slot.registered,
        }),
    };
});

const joinBox: JoinBox = {
    state: 'members_only',
    requested_at: null,
    roster_url: null,
    pending_requests: 0,
};

const campaign = {
    id: 'campaign-1',
    title: 'La cripta olvidada',
    slug: 'la-cripta-olvidada',
    themes: [],
    safetyTools: [],
    contentWarnings: [],
    customWarnings: [],
    genres: [],
    gameMasters: [],
    gameTables: [],
    currentPlayers: 0,
    currentSession: 1,
    totalSessions: 0,
    sessionCount: null,
    maxPlayers: null,
} as unknown as Campaign;

const table = {
    id: 'table-1',
    title: 'Una noche en la cripta',
    slug: 'una-noche-en-la-cripta',
    campaignId: null,
    startsAt: '2030-01-01T18:00:00Z',
    durationMinutes: 120,
    registrationOpensAt: null,
    effectiveRegistrationClosesAt: null,
    membersEarlyAccessDays: 0,
    genres: [],
    safetyTools: [],
    contentWarnings: [],
    customWarnings: [],
    gameMasters: [],
    currentPlayers: 0,
    currentSpectators: 0,
    waitingListCount: 0,
    minPlayers: 2,
    maxPlayers: 5,
    maxSpectators: 0,
    spotsAvailable: 5,
    spectatorSpotsAvailable: 0,
} as unknown as GameTable;

const pages = {
    'game table': (): ReturnType<typeof mount> =>
        mount(GameTableShow, {
            props: { table },
        }),
    campaign: (): ReturnType<typeof mount> =>
        mount(CampaignShow, {
            props: { campaign, joinBox },
        }),
};

describe.each(Object.entries(pages))('Conversation on the %s page', (_name, render) => {
    beforeEach(() => {
        slot.rendersContent = true;
        slot.registered = true;
    });

    it('sits below the card, as a block of its own', async () => {
        const wrapper = render();
        await flushPromises();
        const card = wrapper.find('article').element;
        const block = wrapper.find('[data-testid="subject-discussion"]').element;

        expect(card.contains(block)).toBe(false);
        expect(block.previousElementSibling).toBe(card);
        expect(block.querySelector('[data-testid="conversation"]')).not.toBeNull();
        expect(card.querySelector('[data-testid="conversation"]')).toBeNull();
    });

    it.each([
        ['has no module offering it', false, false],
        ['has a module that draws nothing', false, true],
    ])('leaves no gap when the page %s', (_label, rendersContent, registered) => {
        slot.rendersContent = rendersContent;
        slot.registered = registered;
        const block = render().find('[data-testid="subject-discussion"]');
        const classes = block.classes();

        // No margin of its own: it comes only while the slot's wrapper holds something
        expect(block.element.querySelector('[data-testid="conversation"]')).toBeNull();
        expect(classes).toEqual(['has-[[data-slot-key]:not(:empty)]:mt-8']);
    });
});

describe('Conversation on a campaign session', () => {
    it('is not drawn again: the campaign has the discussion', () => {
        slot.rendersContent = true;
        slot.registered = true;
        const session = { ...table, campaignId: 'campaign-1' } as GameTable;
        const wrapper = mount(GameTableShow, {
            props: { table: session },
        });

        expect(wrapper.find('[data-testid="subject-discussion"]').exists()).toBe(false);
    });
});
