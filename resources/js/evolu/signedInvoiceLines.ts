export type SignedInvoiceLine = {
    quantity: number; unitPrice: number; discount: number; taxRate: number; lineTotal?: number;
    stockItemId?: string | null; warehouseId?: string | null;
};

export function isSignedInvoiceLine(quantity: unknown, unitPrice: unknown): boolean {
    return Number(quantity) < 0 || Number(unitPrice) < 0;
}

export function supportsNegativeQuantity(type: string): boolean {
    return type === "invoice" || type === "credit_note";
}
type CompanyContext = { jurisdiction?: unknown; app_settings?: unknown } | null;

export function signedMoneyRound(value: number): number {
    return Math.sign(value) * Math.round((Math.abs(value) + Number.EPSILON) * 100) / 100;
}

/** CanonicalInvoiceLine serializes net, tax and gross separately to two decimals. */
export function signedLineAmounts(line: SignedInvoiceLine, roundingMethod: string) {
    const rawNet = line.quantity * line.unitPrice * (1 - line.discount / 100);
    const rawTax = rawNet * line.taxRate / 100;
    const net = signedMoneyRound(rawNet);
    const tax = signedMoneyRound(rawTax);
    const gross = signedMoneyRound(roundingMethod === "per_line" ? net + tax : rawNet + rawTax);
    return { net, tax, gross };
}

export function signedInvoiceError(
    type: string,
    lines: SignedInvoiceLine[],
    company: CompanyContext,
    amounts?: { subtotal: unknown; taxTotal: unknown; total: unknown },
    documentDiscount = 0,
): "unsupported_negative_lines" | "positive_total_required" | "deduction_stock_not_supported" | null {
    if (!lines.some(line => isSignedInvoiceLine(line.quantity, line.unitPrice))) return null;
    if (lines.some(line => line.quantity < 0 && (line.stockItemId || line.warehouseId))) return "deduction_stock_not_supported";
    const settings = (company?.app_settings ?? {}) as Record<string, unknown>;
    if (!supportsNegativeQuantity(type)
        || (type !== "invoice" && lines.some(line => line.unitPrice < 0))
        || (company?.jurisdiction && !["eu_sk", "eu_cz", "us"].includes(String(company.jurisdiction)))
        || (company?.jurisdiction === "us" && !["manual", "none"].includes(String(settings.us_sales_tax_provider ?? "manual")))
        || !hasPrecision(documentDiscount, 2)
        || lines.some(line => !hasPrecision(line.unitPrice, 2) || !hasPrecision(line.quantity, 4)
            || !hasPrecision(line.discount, 2) || !hasPrecision(line.taxRate, 2)
            || line.quantity === 0 || Math.abs(line.quantity) > 9999999999.9999
            || (line.quantity < 0 && line.unitPrice <= 0)
            || Math.abs(line.unitPrice) > 999999999999.99
            || Math.abs(line.quantity * line.unitPrice) > 999999999999.99)) {
        return "unsupported_negative_lines";
    }
    if (!amounts) return null;
    if (![amounts.subtotal, amounts.taxTotal, amounts.total].every(value => Number.isFinite(Number(value)))
        || Number(amounts.total) <= 0 || Number(amounts.total) > 999999999999.99
        || Number(amounts.subtotal) <= 0 || Number(amounts.taxTotal) < 0) return "positive_total_required";
    const buckets = new Map<number, { net: number; tax: number }>();
    for (const line of lines) {
        const { net, tax, gross } = signedLineAmounts(line, String(settings.rounding_method ?? "per_line"));
        if (line.lineTotal !== undefined && (!Number.isFinite(line.lineTotal) || Math.abs(line.lineTotal - gross) > 0.000001)) return "unsupported_negative_lines";
        const bucket = buckets.get(line.taxRate) ?? { net: 0, tax: 0 };
        bucket.net += net;
        bucket.tax += tax;
        buckets.set(line.taxRate, bucket);
    }
    if ([...buckets.values()].some(bucket => signedMoneyRound(bucket.net) < 0 || signedMoneyRound(bucket.tax) < 0)) return "positive_total_required";
    const subtotal = [...buckets.values()].reduce((sum, bucket) => sum + bucket.net, 0);
    const tax = [...buckets.values()].reduce((sum, bucket) => sum + bucket.tax, 0);
    const ratio = 1 - documentDiscount / 100;
    const expected = [subtotal * ratio, tax * ratio, (subtotal + tax) * ratio].map(signedMoneyRound);
    const recorded = [amounts.subtotal, amounts.taxTotal, amounts.total].map(Number);
    return expected.some((value, index) => Math.abs(value - recorded[index]) > 0.000001)
        ? "unsupported_negative_lines" : null;
}

function hasPrecision(value: number, places: number): boolean {
    const scaled = value * 10 ** places;
    return Number.isFinite(value) && (value === 0 || Math.abs(value) >= 10 ** -places)
        && Math.abs(scaled - Math.round(scaled)) <= 0.000001;
}

/** Only migrated source documents retain historical display amounts while editing. */
export function hasHistoricalSignedSource(document: object | null, signed: boolean): boolean {
    return signed && document !== null && Boolean(Reflect.get(document, "migrationFingerprint"));
}

/** Manual authoring may inherit a default warehouse without linking stock. */
export function clearManualDeductionWarehouse(line: {
    company_stock_item_id?: string | null;
    company_warehouse_id?: string | null;
    stock_quantity_hint?: number | null;
    warehouse_deduct_on_issue?: boolean | null;
}, quantity: number): void {
    if (quantity < 0 && !line.company_stock_item_id) {
        line.company_warehouse_id = null;
        line.stock_quantity_hint = null;
        line.warehouse_deduct_on_issue = null;
    }
}
