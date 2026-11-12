import { createIdFromString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { DocumentImportMapping } from "@/composables/useDocumentImportFields";
import { allCompaniesDetailQuery, allContactsQuery, allDocumentLinesQuery, allNumberSeriesQuery, migrationDocumentTargetsQuery } from "./client";
import { withExpenseReconciliation, assertExpenseCompanyOwner, checkpointExpenseMutations } from "./expenseReconciliation";
import { awaitBankMutation, BankReconciliationUnknownError } from "./bankReconciliation";
import { ContactImportWriteError, resolveLocalContact } from "./documentImportLocal";
import { assertMigrationSchemaReady } from "./migrationSchema";
import { logDocumentEvent } from "./documentEventLog";
import { retainMigrationOriginal, validateMigrationOriginalFiles, type MigrationSource } from "./migrationArchive";
import { buildPreparedMigrationPreview, buildMigrationPreview, migrationFingerprint, migrationLineMatches, migrationNumberKey, sameMigrationPreview, type MigrationPreview } from "./migrationValidation";
import { migrationStructuredLinePatches, migrationStructuredLinesMatch } from "./migrationItemEvidence";
import { parseFakturoidMigrationExport } from "./migrationFakturoid";
import { parseIdokladSpreadsheet } from "./migrationIdoklad";
import { joinIdokladMigrationEvidence } from "./migrationIdokladEvidence";
import type { IdokladPdfInvoice } from "./migrationIdokladPdf";
import { parseZapriteMigrationExport } from "./migrationZaprite";
import type { MigrationStructuredInvoice } from "./migrationIsdoc";
import { DocumentId, DocumentLineId, type CompanyId, type InvoicingLocalSchema } from "./schema";
import type { EvoluDocumentLineRow } from "./documentMap";
import type { EvoluContactRow } from "./contactMap";
import { syncNumberSeriesCounterFromDocuments } from "./numberSeriesCrud";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { toAppRows } from "./queryLoad";
import type { ImportDateFormat } from "./importDateParse";

export type MigrationImportPhase = "preflight" | "archive" | "contact_document" | "lines" | "finalize" | "readback" | "series";
export class MigrationImportStorageError extends BankReconciliationUnknownError {
    constructor(readonly phase: MigrationImportPhase, error: BankReconciliationUnknownError) {
        super(error.reason);
        this.name = "MigrationImportStorageError";
    }
}

const running = new WeakSet<object>();
export type MigrationImportResult = { imported: number; skipped: number; source: MigrationSource; preview: MigrationPreview };

export async function importMigrationFile(evolu: Evolu<InvoicingLocalSchema>, input: {
    companyId: CompanyId; ownerId: string; sourceKind: string; file: File; bytes: Uint8Array;
    originals?: readonly { file: File; bytes: Uint8Array }[];
    rows: string[][]; mapping: DocumentImportMapping; defaultCurrency: string; dateFormat: ImportDateFormat;
    createContacts: boolean; lineName: string; lineDescription: string; noteFooter: string | null;
    expectedPreview?: MigrationPreview; allowSummary?: boolean; excludeUnsupportedCurrencies?: boolean;
    assertCurrent: () => void;
    onArchiveProgress?: (done: number, total: number) => void;
    onProgress?: (done: number, total: number) => void;
}): Promise<MigrationImportResult> {
    if (running.has(evolu)) throw new Error("import_in_progress");
    running.add(evolu);
    let phase: MigrationImportPhase = "preflight";
    try {
        if (["zaprite", "fakturoid", "idoklad"].includes(input.sourceKind)) {
            // Parsing and custody share one private byte snapshot across all awaits.
            if (input.bytes.byteLength !== input.file.size || input.bytes.byteLength > 10 * 1024 * 1024) throw new Error("invalid_original");
            input = { ...input, bytes: new Uint8Array(input.bytes) };
        }
        if (input.sourceKind === "idoklad") {
            // Snapshot every PDF before the first await and before caller callbacks.
            input = { ...input, originals: (input.originals ?? []).map(original => {
                if (original.bytes.byteLength !== original.file.size || original.bytes.byteLength > 10 * 1024 * 1024) throw new Error("invalid_original");
                return { file: original.file, bytes: new Uint8Array(original.bytes) };
            }) };
        }
        input.assertCurrent();
        await assertMigrationSchemaReady(evolu);
        input.assertCurrent();
        validateMigrationOriginalFiles(input.file, (input.originals ?? []).map(original => original.file));
        // Provider evidence is reconstructed from the exact bytes retained below.
        const zaprite = input.sourceKind === "zaprite"
            ? parseZapriteMigrationExport(input.bytes, { excludeUnsupportedCurrencies: input.excludeUnsupportedCurrencies }) : null;
        const idoklad = input.sourceKind === "idoklad"
            ? await parseIdokladSpreadsheet(input.bytes, input.file.name) : null;
        input.assertCurrent();
        const extractItems = input.sourceKind === "superfaktura" && Boolean(input.originals?.length);
        const structuredInvoices: MigrationStructuredInvoice[] = [];
        const originals = extractItems ? input.originals!.map(original => {
            if (original.bytes.byteLength !== original.file.size) throw new Error("invalid_original");
            // Evidence and custody must use the same bytes throughout the import.
            return { file: original.file, bytes: new Uint8Array(original.bytes) };
        }) : input.originals ?? [];
        const idokladOriginals: IdokladPdfInvoice[] = [];
        let idokladOriginalErrors = false;
        if (idoklad) {
            const { readIdokladPdfEvidence } = await import("./migrationIdokladPdf");
            for (const original of originals) {
                input.assertCurrent();
                try { idokladOriginals.push(...await readIdokladPdfEvidence(original.file.name, original.bytes)); }
                catch { idokladOriginalErrors = true; }
                input.assertCurrent();
            }
        }
        if (extractItems) {
            // Browser parsing does not hold the origin's financial mutation lock.
            const { readMigrationIsdocEvidence } = await import("./migrationIsdocPdf");
            for (const original of originals) {
                input.assertCurrent();
                let parsed: MigrationStructuredInvoice | null = null;
                try {
                    parsed = await readMigrationIsdocEvidence(original.file.name, original.bytes);
                } catch {
                    // The preview exposes unsupported originals. Reproduce its
                    // result below and require consent for any summary rows.
                }
                if (parsed) structuredInvoices.push(parsed);
            }
        }
        input.assertCurrent();
        return await withExpenseReconciliation(evolu, async scope => {
            const guarded = scope.evolu;
            input.assertCurrent(); assertExpenseCompanyOwner(scope, input.companyId, input.ownerId);
            const currentRegistration = () => {
                const companies = guarded.getQueryRows(allCompaniesDetailQuery)
                    .filter(company => company.id === input.companyId && Reflect.get(company, "ownerId") === input.ownerId);
                return companies.length === 1 ? companies[0].registrationNumber ?? null : null;
            };
            const requiresIssuer = structuredInvoices.length > 0 || input.sourceKind === "fakturoid" || input.sourceKind === "idoklad";
            const companyRegistrationNumber = requiresIssuer ? currentRegistration() : null;
            const assertTarget = () => {
                input.assertCurrent();
                assertExpenseCompanyOwner(scope, input.companyId, input.ownerId);
                if (requiresIssuer && currentRegistration() !== companyRegistrationNumber) throw new Error("preview_changed");
            };
            // Bind freshly parsed provider evidence to the current issuer in the
            // lease. Subsequent writes revalidate that binding through assertTarget.
            const providerRows = idoklad ? joinIdokladMigrationEvidence(idoklad, idokladOriginals, {
                companyRegistrationNumber, originalErrors: idokladOriginalErrors,
            }) : input.sourceKind === "fakturoid"
                ? parseFakturoidMigrationExport(input.bytes, { companyRegistrationNumber }).rows : zaprite?.rows;
            const freshPreview = () => {
                const options = { ...input, existing: scope.context.documents,
                    lines: toAppRows<EvoluDocumentLineRow & { ownerId?: string }>(guarded.getQueryRows(allDocumentLinesQuery)) };
                return providerRows ? buildPreparedMigrationPreview(providerRows, options)
                    : buildMigrationPreview(input.rows, input.mapping, { ...options, companyRegistrationNumber, structuredInvoices });
            };
            let preview = freshPreview();
            if (input.expectedPreview && !sameMigrationPreview(input.expectedPreview, preview)) throw new Error("preview_changed");
            if (preview.invalid || preview.conflict) throw new Error("preview_blocked");
            if (!preview.ready) throw new Error("nothing_to_import");
            const candidates = preview.rows.filter(row => row.kind === "ready" && row.invoice).map(row => row.invoice!);
            if (extractItems && !input.allowSummary && candidates.some(invoice => !invoice.structured)) throw new Error("summary_confirmation_required");
            phase = "archive";
            const source = await retainMigrationOriginal(scope, { ...input, assertCurrent: assertTarget });
            const archiveTotal = originals.length + 1;
            let archived = 1;
            input.onArchiveProgress?.(archived, archiveTotal);
            for (const original of originals) {
                await retainMigrationOriginal(scope, { ...input, sourceKind: "original", relatedExportId: source.id,
                    file: original.file, bytes: original.bytes, assertCurrent: assertTarget });
                input.onArchiveProgress?.(++archived, archiveTotal);
            }
            let imported = 0; let skipped = preview.duplicate;
            const progressTotal = candidates.length + skipped;
            for (const invoice of candidates) {
                await checkpointExpenseMutations(guarded);
                assertTarget();
                preview = freshPreview();
                const current = preview.rows.find(row => row.row === invoice.row);
                const fingerprint = migrationFingerprint(invoice);
                if (!current?.invoice || migrationFingerprint(current.invoice) !== fingerprint) throw new Error("preview_changed");
                if (current.kind === "duplicate") { skipped++; continue; }
                if (current.kind !== "ready") throw new Error("preview_changed");
                // Share/rekey copies preserve row ids while the admitted owner changes.
                // Continue the uniquely proven draft instead of deriving a second id from its new owner.
                const resumableDrafts = scope.context.documents.filter(doc => doc.companyId === input.companyId
                    && Reflect.get(doc, "ownerId") === input.ownerId && doc.documentType === "invoice"
                    && doc.number != null && migrationNumberKey(doc.number) === migrationNumberKey(invoice.invoice_number)
                    && doc.status === "draft" && Reflect.get(doc, "migrationFingerprint") === fingerprint
                    && !Reflect.get(doc, "migrationCompletedAt"));
                if (resumableDrafts.length > 1) throw new Error("preview_changed");
                const id = resumableDrafts.length === 1 ? resumableDrafts[0].id
                    : DocumentId.orThrow(createIdFromString(`satflux.migrated-invoice.v1.${input.ownerId}.${input.companyId}.${migrationNumberKey(invoice.invoice_number)}`));
                const targets = await guarded.loadQuery(migrationDocumentTargetsQuery);
                const target = targets.filter(doc => doc.id === id && doc.ownerId === input.ownerId);
                if (target.length > 1 || target.some(doc => doc.isDeleted === 1 || doc.companyId !== input.companyId
                    || doc.documentType !== "invoice" || doc.status !== "draft"
                    || doc.migrationFingerprint !== fingerprint || doc.migrationCompletedAt)) throw new Error("preview_changed");
                assertTarget();
                const original = invoice.structured;
                const title = invoice.invoice_number;
                const name = input.lineName.trim() || "Imported item";
                if ((!original && (name.length > 255 || input.lineDescription.length > 4000))
                    || (input.noteFooter?.length ?? 0) > 4000) throw new Error("invalid_line");
                const structuredPatches = original ? migrationStructuredLinePatches(id, original) : null;
                if (structuredPatches && !migrationStructuredLinesMatch(id, structuredPatches, toAppRows(guarded.getQueryRows(allDocumentLinesQuery)), input.ownerId, false)) {
                    throw new Error("preview_changed");
                }
                const linePatches = structuredPatches ?? [{
                    id: DocumentLineId.orThrow(createIdFromString(`satflux.migrated-line.v1.${id}`)),
                    documentId: id, sortOrder: "0", name, description: input.lineDescription.trim() || null,
                    quantity: "1.0000", unit: null, unitPrice: invoice.total, lineDiscountPercent: "0.00", taxRate: "0.00", lineTotal: invoice.total,
                    companyStockItemId: null, companyWarehouseId: null,
                }];
                const subtotal = original?.subtotal ?? invoice.total;
                const taxTotal = original?.taxTotal ?? "0.00";
                const status = invoice.state === "cancelled" ? "cancelled" : invoice.state === "paid" ? "paid" : "issued";
                phase = "contact_document";
                let contactFailed = false;
                const saved = await awaitBankMutation(guarded, onComplete => {
                    let contact: ReturnType<typeof resolveLocalContact>;
                    try {
                        contact = resolveLocalContact(guarded, input.companyId,
                            toAppRows<EvoluContactRow>(guarded.getQueryRows(allContactsQuery)).filter(row => Reflect.get(row, "ownerId") === input.ownerId),
                            invoice.contact_attributes, input.createContacts);
                    } catch (error) {
                        if (!(error instanceof ContactImportWriteError)) throw error;
                        contactFailed = true;
                        return { ok: false };
                    }
                    const document = guarded.upsert("document", {
                        id, companyId: input.companyId, contactId: contact.contactId, documentType: "invoice", status: "draft", quoteStatus: null,
                        title, number: invoice.invoice_number, sourceDocumentId: null, migrationSourceId: source.id,
                        migrationFingerprint: fingerprint, migrationCompletedAt: null, issueDate: invoice.issue_date,
                        deliveryDate: invoice.delivery_date, dueDate: invoice.due_date, variableSymbol: invoice.variable_symbol,
                        constantSymbol: invoice.constant_symbol, specificSymbol: invoice.specific_symbol, currency: invoice.currency,
                        subtotal, taxTotal, total: invoice.total, discountPercent: "0.00",
                        noteAboveLines: null, noteFooter: input.noteFooter,
                        internalNote: original
                            ? `Imported invoice items from ${input.sourceKind}; source ${source.id}; row ${invoice.row}.`
                            : `Imported summary from ${input.sourceKind}; source ${source.id}; row ${invoice.row}. Original line and VAT breakdown not reconstructed.`,
                        pdfLocale: "sk", pdfShowSignature: 1, pdfShowPaymentInfo: 1, paymentBankEnabled: 0, paymentBtcEnabled: 0,
                        storeId: null, tagsJson: '["import"]', paidAt: null, amountPaid: "0.00",
                    }, { ownerId: input.ownerId as never });
                    if (!document.ok) return document;
                    return guarded.update("document", { id, title }, { ownerId: input.ownerId as never, onComplete });
                });
                if (!saved) throw new Error(contactFailed ? "contact_write_failed" : "document_write_failed");
                await checkpointExpenseMutations(guarded);
                assertTarget();
                // Parent write is already durable. A single Evolu batch writes the
                // complete deterministic item set and proves it below before issuing.
                phase = "lines";
                const lineSaved = await awaitBankMutation(guarded, onComplete => {
                    for (const patch of linePatches.slice(0, -1)) {
                        const result = guarded.upsert("documentLine", patch, { ownerId: input.ownerId as never });
                        if (!result.ok) return result;
                    }
                    return guarded.upsert("documentLine", linePatches[linePatches.length - 1], { ownerId: input.ownerId as never, onComplete });
                });
                if (!lineSaved) throw new Error("document_write_failed");
                await checkpointExpenseMutations(guarded);
                assertTarget();
                phase = "readback";
                const pendingDocument = scope.context.documents.find(doc => doc.id === id && Reflect.get(doc, "ownerId") === input.ownerId);
                if (!pendingDocument || pendingDocument.status !== "draft" || pendingDocument.companyId !== input.companyId
                    || pendingDocument.total !== invoice.total || pendingDocument.subtotal !== subtotal || pendingDocument.taxTotal !== taxTotal
                    || Reflect.get(pendingDocument, "migrationFingerprint") !== fingerprint
                    || !migrationLineMatches(invoice, pendingDocument, toAppRows(guarded.getQueryRows(allDocumentLinesQuery)), input.ownerId)) throw new Error("document_readback_failed");
                phase = "finalize";
                const complete = await awaitBankMutation(guarded, onComplete => {
                    const event = logDocumentEvent(guarded, id, "business_document.imported", { source_id: source.id, source_sha256: source.checksum, source_row: invoice.row });
                    if (!event.ok) return event;
                    return guarded.update("document", { id, status, paidAt: invoice.paid_at, amountPaid: invoice.amount_paid,
                        migrationCompletedAt: new Date().toISOString() }, { ownerId: input.ownerId as never, onComplete });
                });
                if (!complete) throw new Error("document_write_failed");
                await checkpointExpenseMutations(guarded);
                assertTarget();
                phase = "readback";
                const readBack = scope.context.documents.find(doc => doc.id === id && Reflect.get(doc, "ownerId") === input.ownerId);
                if (!readBack || readBack.companyId !== input.companyId || readBack.total !== invoice.total
                    || readBack.subtotal !== subtotal || readBack.taxTotal !== taxTotal || Reflect.get(readBack, "migrationFingerprint") !== fingerprint
                    || !Reflect.get(readBack, "migrationCompletedAt")
                    || readBack.amountPaid !== invoice.amount_paid || readBack.status !== status || readBack.paidAt !== invoice.paid_at
                    || !migrationLineMatches(invoice, readBack, toAppRows(guarded.getQueryRows(allDocumentLinesQuery)), input.ownerId)) throw new Error("document_readback_failed");
                imported++;
                input.onProgress?.(imported + skipped, progressTotal);
            }
            phase = "series";
            syncNumberSeriesCounterFromDocuments(guarded, input.companyId, "invoice", scope.context.documents,
                toAppRows<EvoluNumberSeriesRow>(guarded.getQueryRows(allNumberSeriesQuery)).filter(row => Reflect.get(row, "ownerId") === input.ownerId));
            await checkpointExpenseMutations(guarded);
            return { imported, skipped, source, preview };
        }, undefined, ["allContactsQuery", "allNumberSeriesQuery", "allDocumentLinesQuery", "allDocumentEventsQuery"]);
    } catch (error) {
        if (error instanceof BankReconciliationUnknownError) throw new MigrationImportStorageError(phase, error);
        throw error;
    } finally { running.delete(evolu); }
}
