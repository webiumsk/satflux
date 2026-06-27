/** Local-first invoicing feature flags (Vite env). */
export function isInvoicingLocalFirst(): boolean {
    return import.meta.env.VITE_INVOICING_LOCAL_FIRST === "true";
}
