import type { CompanyId } from "./schema";

export type BankDedupeRow = {
    bookedAt: string;
    amount: number;
    currency: string;
    direction: string;
    variableSymbol?: string | null;
    reference?: string | null;
    bankTransactionId?: string | null;
};

export async function bankTransactionDedupeHash(
    companyId: CompanyId,
    row: BankDedupeRow,
): Promise<string> {
    const parts = [
        companyId,
        row.bookedAt,
        row.amount.toFixed(2),
        row.currency.toUpperCase(),
        row.direction,
        row.variableSymbol ?? "",
        row.reference ?? "",
        row.bankTransactionId ?? "",
    ];

    const payload = new TextEncoder().encode(parts.join("|"));
    const digest = await crypto.subtle.digest("SHA-256", payload);
    return Array.from(new Uint8Array(digest))
        .map((b) => b.toString(16).padStart(2, "0"))
        .join("");
}
