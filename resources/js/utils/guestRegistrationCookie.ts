/**
 * Browser hint of the tables the visitor signed up to as a guest: entries `slug:startsAtEpoch`
 * (seconds) joined by commas. It only warns before a second sign-up; the server is the authority.
 * It is a preferences cookie: nothing is read or written without the visitor's consent.
 */
export const GUEST_REGISTRATIONS_COOKIE = 'gt_guest_registrations';

const MAX_ENTRIES = 20;
const MAX_AGE_SECONDS = 90 * 24 * 60 * 60;

interface Entry {
    slug: string;
    startsAt: number;
}

export function canUseGuestCookie(): boolean {
    return globalThis.GuildForgeCookieConsent?.hasConsent('preferences') === true;
}

function readRaw(): string {
    try {
        const prefix = `${GUEST_REGISTRATIONS_COOKIE}=`;
        const part = document.cookie.split('; ').find((chunk) => chunk.startsWith(prefix));

        return part === undefined ? '' : part.slice(prefix.length);
    } catch {
        return '';
    }
}

function parse(raw: string): Entry[] {
    const entries: Entry[] = [];

    for (const chunk of raw.split(',')) {
        const separator = chunk.lastIndexOf(':');
        if (separator < 1) continue;

        const slug = chunk.slice(0, separator);
        const startsAt = Number(chunk.slice(separator + 1));
        if (!Number.isFinite(startsAt)) continue;

        entries.push({ slug, startsAt });
    }

    return entries;
}

export function hasGuestRegistration(slug: string): boolean {
    if (!canUseGuestCookie()) return false;

    return parse(readRaw()).some((entry) => entry.slug === slug);
}

export function rememberGuestRegistration(slug: string, startsAt: string): void {
    if (!canUseGuestCookie()) return;

    const startsAtEpoch = Math.floor(new Date(startsAt).getTime() / 1000);
    if (!Number.isFinite(startsAtEpoch)) return;

    const nowEpoch = Math.floor(Date.now() / 1000);
    const entries = parse(readRaw())
        .filter((entry) => entry.slug !== slug && entry.startsAt > nowEpoch)
        .concat({ slug, startsAt: startsAtEpoch })
        .slice(-MAX_ENTRIES);
    const value = entries.map((entry) => `${entry.slug}:${entry.startsAt}`).join(',');

    try {
        document.cookie = `${GUEST_REGISTRATIONS_COOKIE}=${value}; Max-Age=${MAX_AGE_SECONDS}; Path=/; SameSite=Lax`;
    } catch {
        // A browser that refuses the cookie only loses the hint
    }
}
