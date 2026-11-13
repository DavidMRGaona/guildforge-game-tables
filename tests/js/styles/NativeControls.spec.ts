import { describe, expect, it } from 'vitest';

/**
 * The site does not load `@tailwindcss/forms`, so a bare checkbox, select, number or date
 * field shows the browser's own control, with the system colours in dark mode. The module
 * uses the core's form components (`@/components/form`) instead; a native input may stay
 * only as the hidden half of a custom control (`sr-only`).
 */
const SOURCES = import.meta.glob<string>('../../../resources/js/**/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
});

const NATIVE_TYPE = /\btype="(?:checkbox|radio|file|number|date|time|datetime-local|month|week)"/;

/** Each opening `<input …>` or `<select …>` tag of a template, attributes included. */
function controlTags(source: string): string[] {
    return source.match(/<(?:input|select)\b[^>]*>/g) ?? [];
}

function nativeControls(source: string): string[] {
    return controlTags(source).filter(
        (tag) => !/\bsr-only\b/.test(tag) && (tag.startsWith('<select') || NATIVE_TYPE.test(tag))
    );
}

describe('native form controls', () => {
    it('reads the module templates', () => {
        expect(Object.keys(SOURCES).length).toBeGreaterThan(10);
    });

    it('never shows a browser control the core has a component for', () => {
        const offenders = Object.entries(SOURCES)
            .filter(([, source]) => nativeControls(source).length > 0)
            .map(([path]) => path);

        expect(offenders).toEqual([]);
    });

    it('spots the controls it looks for', () => {
        expect(nativeControls('<input id="a" v-model="b" type="checkbox" />')).toHaveLength(1);
        expect(
            nativeControls('<input\n    type="datetime-local"\n    name="starts_at"\n/>')
        ).toHaveLength(1);
        expect(nativeControls('<select id="format-filter" v-model="x">')).toHaveLength(1);
        expect(nativeControls('<input type="number" :class="inputClass" />')).toHaveLength(1);
    });

    it('lets text fields and hidden halves of custom controls through', () => {
        expect(nativeControls('<input type="text" />')).toEqual([]);
        expect(nativeControls('<input type="checkbox" class="peer sr-only" />')).toEqual([]);
    });
});
