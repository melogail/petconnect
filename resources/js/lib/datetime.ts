const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000_000],
    ['month', 2_592_000_000],
    ['week', 604_800_000],
    ['day', 86_400_000],
    ['hour', 3_600_000],
    ['minute', 60_000],
];

function parse(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

/** "12 Mar 2025" in the page's language. */
export function formatDate(
    value: string | null | undefined,
    locale: string,
): string {
    const date = parse(value);

    return date === null
        ? ''
        : new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(date);
}

/** "12 Mar 2025, 14:03" in the page's language. */
export function formatDateTime(
    value: string | null | undefined,
    locale: string,
): string {
    const date = parse(value);

    return date === null
        ? ''
        : new Intl.DateTimeFormat(locale, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}

/** "14:03" — the clock stamp a chat bubble carries. */
export function formatTime(
    value: string | null | undefined,
    locale: string,
): string {
    const date = parse(value);

    return date === null
        ? ''
        : new Intl.DateTimeFormat(locale, { timeStyle: 'short' }).format(date);
}

/** "3 hours ago", falling back to the absolute date past a week. */
export function formatRelative(
    value: string | null | undefined,
    locale: string,
): string {
    const date = parse(value);

    if (date === null) {
        return '';
    }

    const elapsed = date.getTime() - Date.now();
    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const [unit, milliseconds] of RELATIVE_UNITS) {
        if (Math.abs(elapsed) >= milliseconds) {
            return formatter.format(Math.round(elapsed / milliseconds), unit);
        }
    }

    return formatter.format(Math.round(elapsed / 1000), 'second');
}

/** The day heading a run of chat messages sits under. */
export function formatDayHeading(
    value: string | null | undefined,
    locale: string,
): string {
    const date = parse(value);

    if (date === null) {
        return '';
    }

    return new Intl.DateTimeFormat(locale, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(date);
}

/** The calendar day two timestamps are compared on to decide a new heading. */
export function toDayKey(value: string | null | undefined): string {
    const date = parse(value);

    return date === null ? '' : date.toISOString().slice(0, 10);
}
