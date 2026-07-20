import { computed, type Ref } from 'vue';
import { useQuery } from '@evolu/vue';
import { allInvoiceTemplatesQuery, useInvoicingEvolu } from '@/evolu/client';
import { toAppRows } from '@/evolu/queryLoad';
import type { EvoluInvoiceTemplateRow } from '@/evolu/invoiceTemplate';
import {
    deleteLocalInvoiceTemplate,
    saveLocalInvoiceTemplate,
} from '@/evolu/invoiceTemplateCrud';
import type { CompanyId, InvoiceTemplateId } from '@/evolu/schema';

/** Local-first invoice templates for one company (list + save + delete). */
export function useInvoicingInvoiceTemplates(companyId: Ref<string>) {
    const evolu = useInvoicingEvolu();
    const rows = useQuery(allInvoiceTemplatesQuery, {
        promise: evolu.loadQuery(allInvoiceTemplatesQuery),
    });

    const templates = computed<EvoluInvoiceTemplateRow[]>(() =>
        toAppRows<EvoluInvoiceTemplateRow>(rows.value).filter(
            (row) => row.companyId === (companyId.value as CompanyId),
        ),
    );

    function save(name: string, payloadJson: string, templateId?: InvoiceTemplateId) {
        return saveLocalInvoiceTemplate(evolu, companyId.value as CompanyId, {
            name,
            payloadJson,
            templateId,
        });
    }

    function remove(templateId: InvoiceTemplateId) {
        return deleteLocalInvoiceTemplate(evolu, templateId);
    }

    async function refresh(): Promise<void> {
        await evolu.loadQuery(allInvoiceTemplatesQuery);
    }

    return { templates, save, remove, refresh };
}
