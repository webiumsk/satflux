import { maxLength, NonEmptyString, sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { CompanyId, InvoiceTemplateId, InvoicingLocalSchema } from "./schema";

const NameType = maxLength(255)(NonEmptyString);
const PayloadType = maxLength(262144)(NonEmptyString);

export interface InvoiceTemplateSavePayload {
    name: string;
    payloadJson: string;
    templateId?: InvoiceTemplateId;
}

/** Insert or update an invoice template (soft-delete-aware upsert). */
export function saveLocalInvoiceTemplate(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    payload: InvoiceTemplateSavePayload,
) {
    const name = NameType.from(payload.name.trim());
    if (!name.ok) return name;
    const payloadJson = PayloadType.from(payload.payloadJson);
    if (!payloadJson.ok) return payloadJson;

    const fields = { companyId, name: name.value, payloadJson: payloadJson.value };

    if (payload.templateId) {
        const result = evolu.update("invoiceTemplate", { id: payload.templateId, ...fields });
        if (!result.ok) return result;
        return { ok: true as const, value: { id: payload.templateId } };
    }

    const result = evolu.insert("invoiceTemplate", fields);
    if (!result.ok) return result;
    return { ok: true as const, value: { id: result.value.id } };
}

/** Soft-delete an invoice template. */
export function deleteLocalInvoiceTemplate(
    evolu: Evolu<InvoicingLocalSchema>,
    templateId: InvoiceTemplateId,
) {
    return evolu.update("invoiceTemplate", { id: templateId, isDeleted: sqliteTrue });
}
