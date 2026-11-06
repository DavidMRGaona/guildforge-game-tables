import { describe, expect, it } from 'vitest';
import locales from '../../resources/js/locales';

type Messages = { [key: string]: string | Messages };

/** Every key path of a message tree, objects marked with a trailing dot. */
function keyPaths(messages: Messages, prefix = ''): string[] {
    return Object.entries(messages).flatMap(([key, value]) =>
        typeof value === 'string'
            ? [prefix + key]
            : [`${prefix}${key}.`, ...keyPaths(value, `${prefix}${key}.`)]
    );
}

describe('module locales', () => {
    it('has the same keys in English as in Spanish', () => {
        const spanish = keyPaths(locales.es as Messages);
        const english = new Set(keyPaths(locales.en as Messages));

        expect(spanish.filter((key) => !english.has(key))).toEqual([]);
    });

    it('has no English key that Spanish lacks', () => {
        const spanish = new Set(keyPaths(locales.es as Messages));
        const english = keyPaths(locales.en as Messages);

        expect(english.filter((key) => !spanish.has(key))).toEqual([]);
    });
});
