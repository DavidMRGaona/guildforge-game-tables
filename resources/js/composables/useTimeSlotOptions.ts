import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { VENUE_TIMEZONE, venueDayKey } from '@/utils/datetime';

/** A slot of the event, as its creation context lists it */
export interface EventTimeSlot {
    label: string;
    start_time: string;
    end_time: string;
    max_tables: number | null;
    // Tables the slot still takes; null without a limit
    available_slots: number | null;
}

export interface TimeSlotOption {
    value: string;
    label: string;
}

/**
 * Options of the slot select, valued by the slot's position. When the slots fall on more than one
 * day of the site's calendar each names its day («sáb 15 · Mañana»); a slot with a limit shows the
 * tables it still takes (the server refuses a full one).
 */
export function useTimeSlotOptions(
    slots: () => EventTimeSlot[] | undefined
): ComputedRef<TimeSlotOption[]> {
    const { t, locale } = useI18n();

    function dayName(slot: EventTimeSlot): string {
        return new Date(slot.start_time).toLocaleDateString(locale.value, {
            timeZone: VENUE_TIMEZONE,
            weekday: 'short',
            day: 'numeric',
        });
    }

    function optionLabel(slot: EventTimeSlot, withDay: boolean): string {
        const label = withDay
            ? t('gameTables.create.fields.timeSlotWithDay', {
                  day: dayName(slot),
                  label: slot.label,
              })
            : slot.label;
        const left = slot.available_slots;
        if (left === null) return label;
        if (left === 0) return t('gameTables.create.fields.timeSlotFull', { label });
        return t('gameTables.create.fields.timeSlotTablesLeft', { label, count: left }, left);
    }

    return computed(() => {
        const list = slots() ?? [];
        const days = new Set(list.map((slot) => venueDayKey(new Date(slot.start_time))));
        const withDay = days.size > 1;

        return list.map((slot, index) => ({
            value: String(index),
            label: optionLabel(slot, withDay),
        }));
    });
}
