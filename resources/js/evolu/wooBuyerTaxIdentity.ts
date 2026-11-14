/** Empty shop company fields do not clear an existing contact's tax identity. */
export function preserveWooBuyerTaxIdentity(
    incoming: { registration_number: string; tax_id: string; vat_id: string },
    existing: { registrationNumber: string | null; taxId: string | null; vatId: string | null },
) {
    return {
        registration_number: incoming.registration_number.trim() || existing.registrationNumber || "",
        tax_id: incoming.tax_id.trim() || existing.taxId || "",
        vat_id: incoming.vat_id.trim() || existing.vatId || "",
    };
}
