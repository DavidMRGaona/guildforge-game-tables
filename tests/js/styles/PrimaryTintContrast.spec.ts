import { describe, expect, it } from 'vitest';

/**
 * `text-primary` (the core's link colour, also written `text-[color:var(--color-primary-link)]`)
 * keeps 4.5:1 on the page, surfaces and the muted background, but not on a primary tint: on
 * `bg-primary-light` it gives 4.4:1 in light mode with the default palette, and on
 * `bg-primary-500/10` or `/20` between 4.0:1 and 4.6:1, under WCAG AA for small text. Text on a
 * tint uses `text-base-primary` (15.7:1 light, 13.7:1 dark on `bg-primary-light`), also in the
 * state that paints it (`hover:bg-primary-light` needs `hover:text-base-primary`). Icons may keep
 * the link colour: non-text needs 3:1.
 */
const SOURCES = import.meta.glob<string>('../../../resources/js/**/*.{vue,ts}', {
    query: '?raw',
    import: 'default',
    eager: true,
});

/** Every quoted string of a source, where class lists live. */
function quotedStrings(source: string): string[] {
    return [...source.matchAll(/(["'`])((?:(?!\1)[\s\S])*?)\1/g)].map((match) => match[2] ?? '');
}

const LINK_COLOURS = ['text-primary', 'text-[color:var(--color-primary-link)]'];

const TINT = /^((?:[\w-]+:)*)bg-primary-(?:light|\d+\/\d+)$/;

/** Whether a class list paints the link colour on a primary tint, in any state. */
function paintsLinkColourOnTint(classes: string): boolean {
    const tokens = classes.split(/\s+/);
    const linkIn = (variant: string): boolean =>
        LINK_COLOURS.some((colour) => tokens.includes(`${variant}${colour}`));
    const baseTextIn = (variant: string): boolean =>
        tokens.some((token) => token.startsWith(`${variant}text-base-`));

    return tokens
        .map((token) => TINT.exec(token)?.[1])
        .filter((variant): variant is string => variant !== undefined)
        .some(
            (variant) => linkIn(variant) || (variant !== '' && linkIn('') && !baseTextIn(variant))
        );
}

describe('text on the primary tint', () => {
    it('reads the module sources', () => {
        expect(Object.keys(SOURCES).length).toBeGreaterThan(10);
    });

    it('recognises the pairs it guards', () => {
        expect(paintsLinkColourOnTint('bg-primary-light text-primary')).toBe(true);
        expect(paintsLinkColourOnTint('text-primary hover:bg-primary-light')).toBe(true);
        expect(paintsLinkColourOnTint('bg-primary-light text-base-primary')).toBe(false);
        expect(
            paintsLinkColourOnTint('text-primary hover:bg-primary-light hover:text-base-primary')
        ).toBe(false);
        expect(paintsLinkColourOnTint('bg-primary-light')).toBe(false);
        expect(paintsLinkColourOnTint('bg-primary-500/10 text-sm text-primary')).toBe(true);
        expect(
            paintsLinkColourOnTint('bg-primary-500/5 text-[color:var(--color-primary-link)]')
        ).toBe(true);
        expect(
            paintsLinkColourOnTint(
                'text-base-secondary hover:bg-primary-500/10 hover:text-[color:var(--color-primary-link)]'
            )
        ).toBe(true);
        expect(paintsLinkColourOnTint('bg-primary-500/10 text-base-primary')).toBe(false);
        expect(paintsLinkColourOnTint('text-primary hover:underline')).toBe(false);
    });

    it('never paints the link colour on the primary tint', () => {
        const offenders = Object.entries(SOURCES).flatMap(([path, source]) =>
            quotedStrings(source)
                .filter(paintsLinkColourOnTint)
                .map((classes) => `${path}: ${classes.trim()}`)
        );

        expect(offenders).toEqual([]);
    });
});
