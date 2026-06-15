import type { ContactStats } from "@/composables/useCompanyContact";
import type { EvoluDocumentRow } from "./documentMap";

export function emptyContactStats(): ContactStats {
    return {
        invoiced_total: 0,
        invoiced_count: 0,
        overdue_total: 0,
        overdue_count: 0,
        avg_payment_days: null,
    };
}

function isOverdue(doc: EvoluDocumentRow): boolean {
    if (doc.status !== "issued" || !doc.dueDate) return false;
    const due = new Date(`${doc.dueDate}T23:59:59`);
    return !Number.isNaN(due.getTime()) && due < new Date();
}

function diffDays(issueDate: string, paidAt: string): number {
    const issue = new Date(`${issueDate}T00:00:00`);
    const paid = new Date(`${paidAt}T00:00:00`);
    if (Number.isNaN(issue.getTime()) || Number.isNaN(paid.getTime())) return 0;
    return Math.round((paid.getTime() - issue.getTime()) / (1000 * 60 * 60 * 24));
}

export function computeContactStats(
    contactId: string,
    allDocuments: EvoluDocumentRow[],
): ContactStats {
    const stats = emptyContactStats();
    let paymentDaysSum = 0;
    let paymentDaysCount = 0;

    for (const doc of allDocuments) {
        if (doc.contactId !== contactId) continue;
        if (doc.documentType !== "invoice") continue;
        if (doc.status !== "issued" && doc.status !== "paid") continue;

        const total = Number(doc.total) || 0;
        stats.invoiced_total += total;
        stats.invoiced_count++;

        if (isOverdue(doc)) {
            stats.overdue_total += total;
            stats.overdue_count++;
        }

        if (doc.status === "paid" && doc.paidAt && doc.issueDate) {
            paymentDaysSum += diffDays(doc.issueDate, doc.paidAt);
            paymentDaysCount++;
        }
    }

    if (paymentDaysCount > 0) {
        stats.avg_payment_days = Math.round(paymentDaysSum / paymentDaysCount);
    }

    return stats;
}
