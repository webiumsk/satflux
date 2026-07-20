/**
 * Invoice templates - a saved document payload (header + lines, without number
 * or dates) that prefills a new draft. Pure serialization + shaping helpers;
 * the Evolu CRUD lives in invoiceTemplateCrud.ts and the apply path reuses
 * useInvoiceDocument's applyDocument.
 */
import type { InvoiceTemplateId, CompanyId } from './schema';

export const INVOICE_TEMPLATE_VERSION = 1;

/** Fields cleared from a saved template - assigned fresh on each new draft. */
const VOLATILE_FIELDS = [
    'issue_date',
    'due_date',
    'delivery_date',
    'variable_symbol',
] as const;

export type EvoluInvoiceTemplateRow = {
    id: InvoiceTemplateId;
    companyId: CompanyId;
    name: string;
    payloadJson: string;
};

export interface InvoiceTemplateSnapshot {
    version: number;
    /** A document payload shaped like useInvoiceDocument.payload() output,
     *  minus the volatile (date/number) fields. */
    document: Record<string, unknown>;
}

/** Build a template snapshot from a document payload, dropping volatile fields. */
export function buildTemplateSnapshot(payload: Record<string, unknown>): InvoiceTemplateSnapshot {
    const document: Record<string, unknown> = { ...payload };
    for (const field of VOLATILE_FIELDS) delete document[field];
    return { version: INVOICE_TEMPLATE_VERSION, document };
}

export function serializeTemplateSnapshot(snapshot: InvoiceTemplateSnapshot): string {
    return JSON.stringify(snapshot);
}

/** Parse a stored template payload; returns null on malformed/legacy JSON. */
export function parseTemplateSnapshot(json: string): InvoiceTemplateSnapshot | null {
    try {
        const parsed: unknown = JSON.parse(json);
        if (
            parsed
            && typeof parsed === 'object'
            && 'document' in parsed
            && typeof (parsed as { document: unknown }).document === 'object'
            && (parsed as { document: unknown }).document !== null
        ) {
            const obj = parsed as { version?: unknown; document: Record<string, unknown> };
            return {
                version: typeof obj.version === 'number' ? obj.version : INVOICE_TEMPLATE_VERSION,
                document: obj.document,
            };
        }
    } catch {
        // malformed JSON
    }
    return null;
}

/**
 * Turn a template snapshot into the document-shaped object applyDocument
 * expects for a fresh draft: force draft status and leave number/dates empty
 * so the form assigns the next number + today's date.
 */
export function templateToDraftDocument(snapshot: InvoiceTemplateSnapshot): Record<string, unknown> {
    return {
        ...snapshot.document,
        status: 'draft',
        number: '',
        issue_date: '',
        due_date: '',
        delivery_date: '',
    };
}
