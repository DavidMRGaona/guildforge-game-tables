import { computed, nextTick, useTemplateRef } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormTab } from '../components/FormTabs.vue';

/** One error of a failed save, as the summary above the tabs lists it */
export interface FormErrorItem {
    key: string;
    tab: string;
    tabLabel: string;
    label: string;
    message: string;
}

interface FormErrorSummary {
    tabErrors: ComputedRef<Record<string, number>>;
    gameMasterErrors: ComputedRef<Record<string, string>>;
    errorItems: ComputedRef<FormErrorItem[]>;
    revealErrors: () => void;
    focusError: (item: FormErrorItem) => Promise<void>;
    /** A director row was removed (its position in the full list): drop its errors, move the later rows' up */
    removeGameMasterRowErrors: (index: number) => void;
}

// An element of the page (an HTMLElement)
type PageElement = NonNullable<ReturnType<typeof document.getElementById>>;

interface GameMasterRow {
    role: string;
}

interface Options {
    /** The form's errors (`form.errors`): keys may be nested (`game_masters.1.email`, `genres.0`) */
    errors: () => Partial<Record<string, string>>;
    tabs: ComputedRef<FormTab[]>;
    activeTab: Ref<string>;
    /** Field (first segment of an error key) => its tab; any other key goes to 'basic' */
    fieldToTab: Record<string, string>;
    /** Field => the i18n key of the label the form shows for it; any other key reads «Otros datos» */
    fieldLabels: Record<string, string>;
    /** Error key or field => id of the element to focus when the page has no element with its own id or name */
    fieldTargets?: Record<string, string>;
    /** The directors of the form, to name the row of a `game_masters.<i>.<field>` error */
    gameMasters?: () => GameMasterRow[];
    /** Replaces every error of the form (`form.clearErrors().setErrors(...)`), for a removed director row */
    replaceErrors?: (errors: Record<string, string>) => void;
}

const GAME_MASTER_ERROR = /^game_masters\.(\d+)\.(.+)$/;

/**
 * The errors once director row `removed` (its position in the full list) is gone. They are keyed by
 * row position (`game_masters.<i>.<field>`): the removed row's errors go, each later row's move one
 * position up, and every other error stays as it is, in the same order. Adding a row (at the end)
 * moves nothing, so it needs no call.
 */
export function shiftGameMasterErrors(
    errors: Partial<Record<string, string>>,
    removed: number
): Record<string, string> {
    const shifted: Record<string, string> = {};

    for (const [key, message] of Object.entries(errors)) {
        if (typeof message !== 'string') continue;

        const director = GAME_MASTER_ERROR.exec(key);
        const row = director !== null ? Number(director[1]) : -1;

        if (director === null || row < removed) {
            shifted[key] = message;
        } else if (row > removed) {
            shifted[`game_masters.${row - 1}.${director[2] ?? ''}`] = message;
        }
    }

    return shifted;
}

// Labels of each director's fields, the ones GameMasterSection shows
const GAME_MASTER_FIELD_LABELS: Record<string, string> = {
    first_name: 'gameTables.create.gameMasters.firstName',
    last_name: 'gameTables.create.gameMasters.lastName',
    email: 'gameTables.create.gameMasters.email',
    phone: 'gameTables.create.gameMasters.phone',
    custom_title: 'gameTables.create.gameMasters.customTitle',
};

const FOCUSABLE = [
    'input:not([type="hidden"]):not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'button:not([disabled])',
    'a[href]',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

/**
 * Errors of a form with tabs: the count per tab (badges), the directors' field errors and the
 * items of the summary shown after a failed save. Every error key becomes an item, so no error is
 * ever hidden, even one no field renders.
 */
export function useFormErrorSummary(options: Options): FormErrorSummary {
    const { t } = useI18n();

    // The page's summary component (`<FormErrorSummary ref="errorSummary">`), focused after a failed save
    const summaryRef = useTemplateRef<{ focus: () => void }>('errorSummary');

    // An error key belongs to the field of its first segment: `game_masters.1.email` or `genres.0`
    // land on the tab of their field
    function fieldOf(key: string): string {
        return key.split('.')[0] ?? key;
    }

    function tabOf(key: string): string {
        return options.fieldToTab[fieldOf(key)] ?? 'basic';
    }

    const errorEntries = computed((): Array<[string, string]> =>
        Object.entries(options.errors()).filter((entry): entry is [string, string] => {
            const message = entry[1];

            return typeof message === 'string' && message !== '';
        })
    );

    // Errors per tab, for the badges
    const tabErrors = computed(() => {
        const counts: Record<string, number> = {};

        for (const [key] of errorEntries.value) {
            const tab = tabOf(key);
            counts[tab] = (counts[tab] ?? 0) + 1;
        }

        return counts;
    });

    // Errors of the directors' fields (`game_masters.<i>.<field>`) and of their consent, shown under each input
    const gameMasterErrors = computed((): Record<string, string> => {
        const errors: Record<string, string> = {};

        for (const [key, message] of errorEntries.value) {
            if (key.startsWith('game_masters.') || key === 'gm_gdpr_consent') {
                errors[key] = message;
            }
        }

        return errors;
    });

    // «Director principal» or «Co-director #<n>», n counting only the co-directors; a row no longer in
    // the form (removed after the failed save) reads as the section
    function gameMasterRowLabel(index: number): string {
        const rows = options.gameMasters?.() ?? [];
        const row = rows[index];

        if (row === undefined) {
            return t('gameTables.create.gameMasters.title');
        }
        if (row.role === 'main') {
            return t('gameTables.create.gameMasters.roleMain');
        }

        const n = rows.slice(0, index).filter((other) => other.role !== 'main').length + 1;

        return t('gameTables.create.gameMasters.cogmNumber', { n });
    }

    function labelOf(key: string): string {
        const director = options.gameMasters ? GAME_MASTER_ERROR.exec(key) : null;
        if (director !== null) {
            const fieldLabel = GAME_MASTER_FIELD_LABELS[director[2] ?? ''];

            return `${gameMasterRowLabel(Number(director[1]))} › ${t(fieldLabel ?? 'gameTables.formErrors.otherData')}`;
        }

        const labelKey = options.fieldLabels[fieldOf(key)];

        return labelKey !== undefined ? t(labelKey) : t('gameTables.formErrors.otherData');
    }

    // Every error, in the order of the tabs (and of the server within a tab)
    const errorItems = computed((): FormErrorItem[] => {
        const tabs = options.tabs.value;
        const rank = (tab: string): number => {
            const index = tabs.findIndex((candidate) => candidate.id === tab);

            return index < 0 ? tabs.length : index;
        };

        return errorEntries.value
            .map(([key, message]): FormErrorItem => {
                const tab = tabOf(key);

                return {
                    key,
                    tab,
                    tabLabel: tabs.find((candidate) => candidate.id === tab)?.label ?? tab,
                    label: labelOf(key),
                    message,
                };
            })
            .sort((a, b) => rank(a.tab) - rank(b.tab));
    });

    function findFirstTabWithErrors(): string | null {
        for (const tab of options.tabs.value) {
            if ((tabErrors.value[tab.id] ?? 0) > 0) {
                return tab.id;
            }
        }

        return null;
    }

    // After a failed save: open the first tab with errors and move the focus to the summary
    function revealErrors(): void {
        nextTick(() => {
            const first = findFirstTabWithErrors();
            if (first !== null) {
                options.activeTab.value = first;
            }
            nextTick(() => summaryRef.value?.focus());
        });
    }

    function findElement(target: string): PageElement | null {
        return (
            document.getElementById(target) ??
            document.querySelector<PageElement>(
                `[name="${window.CSS.escape(target)}"]:not([type="hidden"])`
            )
        );
    }

    // Focus the element, or the first control inside it (a list of checkboxes, the directors' section)
    function focusWithin(element: PageElement): boolean {
        const control = element.matches(FOCUSABLE)
            ? element
            : element.querySelector<PageElement>(FOCUSABLE);
        if (control === null) {
            return false;
        }

        control.scrollIntoView({ behavior: 'smooth', block: 'center' });
        control.focus({ preventScroll: true });

        return true;
    }

    // An item of the summary: open its tab, then focus its field (or the tab when the field is not on the page)
    async function focusError(item: FormErrorItem): Promise<void> {
        options.activeTab.value = item.tab;
        await nextTick();

        const field = fieldOf(item.key);
        const targets = options.fieldTargets ?? {};
        const candidates = [item.key, targets[item.key], field, targets[field], `tab-${item.tab}`];

        for (const candidate of candidates) {
            if (candidate === undefined) continue;

            const element = findElement(candidate);
            if (element !== null && focusWithin(element)) {
                return;
            }
        }
    }

    function removeGameMasterRowErrors(index: number): void {
        options.replaceErrors?.(shiftGameMasterErrors(options.errors(), index));
    }

    return {
        tabErrors,
        gameMasterErrors,
        errorItems,
        revealErrors,
        focusError,
        removeGameMasterRowErrors,
    };
}
