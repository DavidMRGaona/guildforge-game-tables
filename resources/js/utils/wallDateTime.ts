/**
 * The start of a table as people read it: the site's wall time, 'YYYY-MM-DDTHH:MM', which the
 * server turns into an instant in the display zone. The form asks for the day and the time in
 * two fields and sends them joined.
 */
export interface WallDateTime {
    /** 'YYYY-MM-DD', or '' when there is none */
    date: string;
    /** 'HH:MM', or '' when there is none */
    time: string;
}

const WALL_TIME = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/;

/**
 * The day and the time of a wall time. A time slot start ('2026-12-05T18:30:00+01:00') comes
 * in the display zone already, so its offset is dropped, never applied.
 */
export function splitWallDateTime(value: string): WallDateTime {
    const match = WALL_TIME.exec(value);

    return match === null ? { date: '', time: '' } : { date: match[1] ?? '', time: match[2] ?? '' };
}

/**
 * The wall time the server reads, or '' while the day or the time is missing (the server
 * then says the start is required). A day typed in a way the picker could not read is sent
 * as it is, so the server rejects it on the field.
 */
export function joinWallDateTime(date: string, time: string): string {
    return date === '' || time === '' ? '' : `${date}T${time}`;
}
