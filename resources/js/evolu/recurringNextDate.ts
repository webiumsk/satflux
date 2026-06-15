import type { RecurringInterval } from "./schema";
import type { EvoluRecurringProfileRow } from "./recurringMap";

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
    return next.toISOString().slice(0, 10);
}

export function isRecurringProfileDue(
    isActive: boolean,
    nextIssueDate: string | null,
    repeatIndefinitely: boolean,
    endsAt: string | null,
    today = new Date().toISOString().slice(0, 10),
): boolean {
    if (!isActive || !nextIssueDate) return false;
    if (nextIssueDate > today) return false;
    if (!repeatIndefinitely && endsAt && endsAt < today) return false;
    return true;
}

export function listDueRecurringProfiles(
    rows: EvoluRecurringProfileRow[],
    today = new Date().toISOString().slice(0, 10),
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
