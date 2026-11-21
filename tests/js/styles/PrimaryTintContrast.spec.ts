import { describe, expect, it } from 'vitest';

/**
 * `text-primary` (the core's link colour) keeps 4.5:1 on the page, surfaces and the muted
 * background, but not on its own light tint: on `bg-primary-light` it gives 4.4:1 in light mode
 * with the default palette, under WCAG AA for the small text of badges and pills. Text on that
 * tint uses `text-base-primary` (15.7:1 light, 13.7:1 dark), also in the state that paints it
 * (`hover:bg-primary-light` needs `hover:text-base-primary`).
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

/** Whether a class list paints `text-primary` on the primary tint without the base text. */
function paintsLinkColourOnTint(classes: string): boolean {
    const tokens = classes.split(/\s+/);
    if (!tokens.includes('text-primary')) {
        return false;
    }

    const tintVariants = tokens
        .map((token) => /^((?:[\w-]+:)*)bg-primary-light$/.exec(token)?.[1])
        .filter((variant): variant is string => variant !== undefined);

    return tintVariants.some(
        (variant) => variant === '' || !tokens.includes(`${variant}text-base-primary`)
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
