import type { CompanyId } from "./schema";

export type CompanyInvoiceCountRow = {
    companyId: CompanyId;
    documentType: string;
    status: string;
};

/**
 * Matches the default Faktúry list (documentType invoice, status filter "all").
 * Includes paid and issued rows; excludes drafts and other document types.
 */
export function countCompanyInvoicesForList(
    documents: ReadonlyArray<CompanyInvoiceCountRow>,
    companyId: CompanyId,
): number {
    return documents.filter(
        (doc) => doc.companyId === companyId && doc.documentType === "invoice",
    ).length;
}

export function countIssuedDocumentsForCompany(
    documents: ReadonlyArray<{ companyId: CompanyId; status: string }>,
    companyId: CompanyId,
): number {
    return documents.filter(
        (doc) => doc.companyId === companyId && doc.status === "issued",
    ).length;
}
