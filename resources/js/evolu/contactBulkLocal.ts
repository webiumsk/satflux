import type { Evolu } from "@evolu/common/local-first";
import { deleteLocalContact } from "./contactCrud";
import { evoluContactToApi, filterContacts, type EvoluContactRow } from "./contactMap";
import type { BulkResult } from "./documentBulkLocal";
import { downloadCsvBlob } from "./documentBulkLocal";
import type { EvoluDocumentRow } from "./documentMap";
import type { CompanyId, ContactId, InvoicingLocalSchema } from "./schema";

export { downloadCsvBlob };

export type ResolveBulkContactTargetsFilter = {
    q?: string;
    letter?: string;
};

export function resolveBulkContactTargets(
    companyId: CompanyId,
    selectAll: boolean,
    selectedIds: Iterable<string>,
    allContacts: EvoluContactRow[],
    filter: ResolveBulkContactTargetsFilter,
): EvoluContactRow[] {
    const companyContacts = allContacts.filter((row) => row.companyId === companyId);

    if (selectAll) {
        const filtered = filterContacts(
            companyContacts.map(evoluContactToApi),
            filter,
        );
        const idSet = new Set(filtered.map((c) => c.id));
        return companyContacts.filter((row) => idSet.has(row.id));
    }

    const idSet = new Set(selectedIds);
    return companyContacts.filter((row) => idSet.has(row.id));
}

export function isContactReferencedByIssuedOrPaid(
    contactId: string,
    allDocuments: EvoluDocumentRow[],
): boolean {
    return allDocuments.some(
        (doc) =>
            doc.contactId === contactId
            && (doc.status === "issued" || doc.status === "paid"),
    );
}

export function bulkDeleteLocalContacts(
    evolu: Evolu<InvoicingLocalSchema>,
    contacts: EvoluContactRow[],
    allDocuments: EvoluDocumentRow[],
): BulkResult {
    let processed = 0;
    let skipped = 0;

    for (const contact of contacts) {
        if (isContactReferencedByIssuedOrPaid(contact.id, allDocuments)) {
            skipped++;
            continue;
        }
        deleteLocalContact(evolu, contact.id as ContactId);
        processed++;
    }

    return { processed, skipped };
}

export type ContactCsvExportRow = {
    name: string;
    email: string;
    registrationNumber: string;
    taxId: string;
    vatId: string;
    city: string;
    country: string;
};

export function buildContactsCsvBlob(rows: ContactCsvExportRow[]): Blob {
    const header = [
        "Name",
        "Email",
        "Registration number",
        "Tax ID",
        "VAT ID",
        "City",
        "Country",
    ];

    const escape = (value: string) => {
        const v = value.replace(/"/g, '""');
        return `"${v}"`;
    };

    const lines = [
        header.map(escape).join(","),
        ...rows.map((row) =>
            [
                row.name,
                row.email,
                row.registrationNumber,
                row.taxId,
                row.vatId,
                row.city,
                row.country,
            ]
                .map((cell) => escape(String(cell ?? "")))
                .join(","),
        ),
    ];

    const bom = "\uFEFF";
    return new Blob([bom + lines.join("\r\n")], { type: "text/csv;charset=utf-8" });
}
