/**
 * Where a table or campaign is played, marked as the server requires it (TableFormat on the
 * server): the location in person, the link online and at least one of the two when hybrid.
 */
export function needsLocation(format: string | null, onlineUrl: string | null): boolean {
    return format === 'in_person' || (format !== 'online' && (onlineUrl ?? '').trim() === '');
}

export function needsOnlineUrl(format: string | null): boolean {
    return format === 'online';
}
