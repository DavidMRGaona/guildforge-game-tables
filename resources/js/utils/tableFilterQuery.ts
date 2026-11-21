import type { GameTableFilters } from '../types/gametables';

/**
 * The query the tables list and calendar read for their filters (#314), from the filters a page
 * got: what is not chosen is left out. The filters component, the month arrows and the view
 * toggle build their links with it, so switching views or months keeps the rest.
 */
export function tableFilterQuery(filters: GameTableFilters): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.systems && filters.systems.length > 0) {
        query.systems = filters.systems.join(',');
    }

    for (const key of ['format', 'status', 'event', 'campaign', 'genre'] as const) {
        const value = filters[key];

        if (value) {
            query[key] = value;
        }
    }

    return query;
}
