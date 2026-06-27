import type { RecurringInterval } from "./schema";
import type { EvoluRecurringProfileRow } from "./recurringMap";

export function formatLocalDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

export function advanceRecurringNextDate(
    interval: RecurringInterval,
    fromDate: string,
    issueLastDayOfMonth: boolean,
): string {
    const from = new Date(`${fromDate.slice(0, 10)}T12:00:00`);
    const next = new Date(from);
    if (interval === "monthly") {
        next.setMonth(next.getMonth() + 1);
    } else {
        next.setFullYear(next.getFullYear() + 1);
    }
    if (issueLastDayOfMonth) {
        next.setMonth(next.getMonth() + 1, 0);
    }
    return formatLocalDate(next);
}

export function isRecurringProfileDue(
    isActive: boolean,
    nextIssueDate: string | null,
    repeatIndefinitely: boolean,
    endsAt: string | null,
    today = formatLocalDate(new Date()),
): boolean {
    if (!isActive || !nextIssueDate) return false;
    if (nextIssueDate > today) return false;
    if (!repeatIndefinitely && endsAt && endsAt < today) return false;
    return true;
}

export function listDueRecurringProfiles(
    rows: EvoluRecurringProfileRow[],
    today = formatLocalDate(new Date()),
): EvoluRecurringProfileRow[] {
    return rows
        .filter((row) =>
            isRecurringProfileDue(
                row.isActive === 1,
                row.nextIssueDate,
                row.repeatIndefinitely === 1,
                row.endsAt,
                today,
            ),
        )
        .sort((a, b) => (a.nextIssueDate ?? "").localeCompare(b.nextIssueDate ?? ""));
}
