const LIMA_TIME_ZONE = 'America/Lima';

function parts(value: Date = new Date()): Record<string, string> {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: LIMA_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(value).reduce<Record<string, string>>((acc, part) => {
        if (part.type !== 'literal') {
            acc[part.type] = part.value;
        }

        return acc;
    }, {});
}

export function todayLimaDate(reference: Date = new Date()): string {
    const p = parts(reference);
    return `${p.year}-${p.month}-${p.day}`;
}

export function firstDayOfCurrentMonthLima(reference: Date = new Date()): string {
    const p = parts(reference);
    return `${p.year}-${p.month}-01`;
}

export function formatDateLima(value: string | Date | null | undefined): string {
    if (!value) return '—';

    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split('-');
        return `${day}/${month}/${year}`;
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('es-PE', {
        timeZone: LIMA_TIME_ZONE,
        dateStyle: 'short',
    }).format(date);
}

export function formatDateTimeLima(value: string | Date | null | undefined): string {
    if (!value) return '—';

    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return formatDateLima(value);
    }

    if (typeof value === 'string') {
        const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (match) {
            const [, year, month, day, hour, minute, second = '0'] = match;
            const asUtc = Date.UTC(
                Number(year),
                Number(month) - 1,
                Number(day),
                Number(hour) + 5,
                Number(minute),
                Number(second),
            );
            const date = new Date(asUtc);

            return new Intl.DateTimeFormat('es-PE', {
                timeZone: LIMA_TIME_ZONE,
                dateStyle: 'short',
                timeStyle: 'short',
            }).format(date);
        }
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('es-PE', {
        timeZone: LIMA_TIME_ZONE,
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
}