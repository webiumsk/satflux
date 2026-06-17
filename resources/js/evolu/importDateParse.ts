export type ImportDateFormat = "auto" | "dmy_dot" | "ymd_dash" | "mdy_slash";

const EXCEL_SERIAL_MIN = 25569;
const EXCEL_SERIAL_MAX = 60000;

function pad2(value: number): string {
    return String(value).padStart(2, "0");
}

export function toIsoDate(year: number, month: number, day: number): string | null {
    if (month < 1 || month > 12 || day < 1 || day > 31) {
        return null;
    }
    const date = new Date(Date.UTC(year, month - 1, day));
    if (
        date.getUTCFullYear() !== year
        || date.getUTCMonth() !== month - 1
        || date.getUTCDate() !== day
    ) {
        return null;
    }
    return `${year}-${pad2(month)}-${pad2(day)}`;
}

export function expandTwoDigitYear(year: number): number {
    if (year >= 100) return year;
    return year >= 70 ? 1900 + year : 2000 + year;
}

export function formatLocalDateParts(date: Date): string | null {
    return toIsoDate(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

export function formatExcelSerialNumber(serial: number): string | null {
    const whole = Math.floor(serial);
    if (whole < EXCEL_SERIAL_MIN || whole > EXCEL_SERIAL_MAX) {
        return null;
    }
    const utcMs = (whole - 25569) * 86400 * 1000;
    const date = new Date(utcMs);
    return toIsoDate(date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate());
}

export function isExcelDateSerial(value: number): boolean {
    if (!Number.isFinite(value)) return false;
    const whole = Math.floor(value);
    return whole >= EXCEL_SERIAL_MIN && whole <= EXCEL_SERIAL_MAX;
}

function normalizeDateInput(value: string): string {
    return value
        .trim()
        .replace(/\u00a0/g, " ")
        .replace(/\s+\d{1,2}:\d{2}(?::\d{2})?(?:\.\d+)?$/, "");
}

function parseExcelSerial(value: string): string | null {
    if (!/^\d+(\.\d+)?$/.test(value)) {
        return null;
    }
    return formatExcelSerialNumber(Number.parseFloat(value));
}

function parseDmyDot(value: string): string | null {
    const match = value.match(/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/);
    if (!match) return null;
    return toIsoDate(expandTwoDigitYear(Number(match[3])), Number(match[2]), Number(match[1]));
}

function parseYmdDash(value: string): string | null {
    const match = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (!match) return null;
    return toIsoDate(Number(match[1]), Number(match[2]), Number(match[3]));
}

function parseSlashDate(value: string, order: "mdy" | "dmy"): string | null {
    const match = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
    if (!match) return null;

    const first = Number(match[1]);
    const second = Number(match[2]);
    const year = expandTwoDigitYear(Number(match[3]));

    const month = order === "mdy" ? first : second;
    const day = order === "mdy" ? second : first;

    return toIsoDate(year, month, day);
}

const FORMAT_PARSERS: Record<ImportDateFormat, Array<(value: string) => string | null>> = {
    auto: [
        parseYmdDash,
        parseDmyDot,
        (value) => parseSlashDate(value, "mdy"),
        (value) => parseSlashDate(value, "dmy"),
    ],
    dmy_dot: [
        parseDmyDot,
        (value) => parseSlashDate(value, "dmy"),
    ],
    ymd_dash: [parseYmdDash],
    mdy_slash: [
        (value) => parseSlashDate(value, "mdy"),
    ],
};

export function parseImportDate(
    value: string | null | undefined,
    format: ImportDateFormat = "auto",
): string | null {
    if (value == null) return null;
    const trimmed = normalizeDateInput(value);
    if (trimmed === "") return null;

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        return trimmed;
    }

    const serial = parseExcelSerial(trimmed);
    if (serial) return serial;

    for (const parser of FORMAT_PARSERS[format]) {
        const iso = parser(trimmed);
        if (iso) return iso;
    }

    return null;
}
