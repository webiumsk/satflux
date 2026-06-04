import type { InvoicingDocumentKind } from './useInvoicingLayout';

export interface InvoicingDocumentRoutes {
  list: string;
  new: string;
  show: string;
  edit: string;
}

const ROUTES_BY_KIND: Partial<Record<InvoicingDocumentKind, InvoicingDocumentRoutes>> = {
  invoice: {
    list: 'invoicing-invoices',
    new: 'invoicing-invoice-new',
    show: 'invoicing-invoice-show',
    edit: 'invoicing-invoice-edit',
  },
  proforma: {
    list: 'invoicing-proformas',
    new: 'invoicing-proforma-new',
    show: 'invoicing-proforma-show',
    edit: 'invoicing-proforma-edit',
  },
  quote: {
    list: 'invoicing-quotes',
    new: 'invoicing-quote-new',
    show: 'invoicing-quote-show',
    edit: 'invoicing-quote-edit',
  },
  credit_note: {
    list: 'invoicing-credit-notes',
    new: 'invoicing-credit-note-new',
    show: 'invoicing-credit-note-show',
    edit: 'invoicing-credit-note-edit',
  },
};

const DEFAULT_ROUTES = ROUTES_BY_KIND.invoice!;

export function invoicingDocumentRoutes(kind: InvoicingDocumentKind | string | undefined): InvoicingDocumentRoutes {
  if (kind && kind in ROUTES_BY_KIND) {
    return ROUTES_BY_KIND[kind as InvoicingDocumentKind] ?? DEFAULT_ROUTES;
  }

  return DEFAULT_ROUTES;
}

export function invoicingDocumentRoutesForType(apiType: string | undefined): InvoicingDocumentRoutes {
  return invoicingDocumentRoutes((apiType as InvoicingDocumentKind) || 'invoice');
}
