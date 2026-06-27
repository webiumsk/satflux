import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

/** Shared content width for all invoicing pages. */
export const INVOICING_CONTAINER_CLASS = 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full';

export type InvoicingMainSection = 'documents' | 'expenses' | 'contacts' | 'stock' | 'payments' | 'tools';

export type InvoicingDocumentKind =
  | 'invoice'
  | 'proforma'
  | 'delivery_note'
  | 'order_received'
  | 'quote'
  | 'recurring'
  | 'credit_note'
  | 'drafts';

export interface InvoicingDocumentNavItem {
  kind: InvoicingDocumentKind;
  routeName: string;
  labelKey: string;
  apiType?: string;
  mvpEnabled: boolean;
}

export type InvoicingToolsSection = 'profile' | 'app' | 'subscription' | 'import';

export interface InvoicingToolsNavItem {
  section: InvoicingToolsSection;
  routeName: string;
  labelKey: string;
}

const LAST_COMPANY_KEY = 'invoicing:lastCompanyId';

export function useInvoicingLayout() {
  const route = useRoute();
  const router = useRouter();
  const { t } = useI18n();

  const companyId = computed(() => (route.params.companyId as string) || '');

  const documentNavItems = computed<InvoicingDocumentNavItem[]>(() => [
    { kind: 'invoice', routeName: 'invoicing-invoices', labelKey: 'invoicing.doc_nav_invoice', apiType: 'invoice', mvpEnabled: true },
    { kind: 'proforma', routeName: 'invoicing-proformas', labelKey: 'invoicing.doc_nav_proforma', apiType: 'proforma', mvpEnabled: true },
    { kind: 'delivery_note', routeName: 'invoicing-delivery-notes', labelKey: 'invoicing.doc_nav_delivery', apiType: 'delivery_note', mvpEnabled: true },
    { kind: 'order_received', routeName: 'invoicing-orders', labelKey: 'invoicing.doc_nav_order', apiType: 'order_received', mvpEnabled: true },
    { kind: 'quote', routeName: 'invoicing-quotes', labelKey: 'invoicing.doc_nav_quote', apiType: 'quote', mvpEnabled: true },
    { kind: 'recurring', routeName: 'invoicing-recurring', labelKey: 'invoicing.doc_nav_recurring', mvpEnabled: true },
    { kind: 'credit_note', routeName: 'invoicing-credit-notes', labelKey: 'invoicing.doc_nav_credit', apiType: 'credit_note', mvpEnabled: true },
    { kind: 'drafts', routeName: 'invoicing-drafts', labelKey: 'invoicing.doc_nav_drafts', mvpEnabled: true },
  ]);

  const activeDocumentKind = computed<InvoicingDocumentKind>(() => {
    const meta = route.meta.documentKind as InvoicingDocumentKind | undefined;
    if (meta) return meta;
    if (route.name === 'invoicing-drafts') return 'drafts';
    return 'invoice';
  });

  const activeMainSection = computed<InvoicingMainSection>(() => {
    const meta = route.meta.invoicingSection as InvoicingMainSection | undefined;
    if (meta) return meta;

    const path = route.path;
    const name = String(route.name ?? '');

    if (path.includes('/contacts') || name.includes('contact')) {
      return 'contacts';
    }
    if (path.includes('/stock') || path.includes('/warehouses') || name.includes('stock') || name.includes('warehouse')) {
      return 'stock';
    }
    if (path.includes('/payments') || name.includes('payment')) {
      return 'payments';
    }
    if (path.includes('/expenses') || name.includes('expense')) {
      return 'expenses';
    }
    if (
      name === 'invoicing-company'
      || name === 'invoicing-company-app'
      || name === 'invoicing-company-app-emails'
      || name === 'invoicing-company-app-series'
      || name === 'invoicing-company-import'
      || name === 'invoicing-company-new'
      || path.includes('/import')
      || /\/invoicing\/companies\/[^/]+$/.test(path)
      || /\/app(\/|$)/.test(path)
    ) {
      return 'tools';
    }
    if (
      path.includes('/invoices')
      || path.includes('/proformas')
      || path.includes('/delivery-notes')
      || path.includes('/orders')
      || path.includes('/quotes')
      || path.includes('/recurring')
      || path.includes('/credit-notes')
      || path.includes('/drafts')
      || name.includes('invoice')
      || name.includes('proforma')
      || name.includes('delivery')
      || name.includes('order')
      || name.includes('quote')
      || name.includes('recurring')
      || name.includes('credit')
      || name.includes('draft')
    ) {
      return 'documents';
    }

    return 'documents';
  });

  const activeDocumentNav = computed(() =>
    documentNavItems.value.find((i) => i.kind === activeDocumentKind.value) ?? documentNavItems.value[0]
  );

  const toolsNavItems = computed<InvoicingToolsNavItem[]>(() => [
    { section: 'profile', routeName: 'invoicing-company', labelKey: 'invoicing.settings_nav_profile' },
    { section: 'app', routeName: 'invoicing-company-app', labelKey: 'invoicing.settings_nav_application' },
    { section: 'import', routeName: 'invoicing-company-import', labelKey: 'invoicing.settings_nav_import' },
    { section: 'subscription', routeName: 'account', labelKey: 'invoicing.settings_nav_subscription' },
  ]);

  const activeToolsSection = computed<InvoicingToolsSection>(() => {
    const name = String(route.name ?? '');
    if (
      name === 'invoicing-company-app'
      || name === 'invoicing-company-app-emails'
      || name === 'invoicing-company-app-series'
    ) {
      return 'app';
    }
    if (name === 'invoicing-company-import') {
      return 'import';
    }
    if (name === 'account') {
      return 'subscription';
    }
    return 'profile';
  });

  function rememberCompany(id: string) {
    if (id) {
      try {
        sessionStorage.setItem(LAST_COMPANY_KEY, id);
      } catch {
        /* ignore */
      }
    }
  }

  function resolvedCompanyId(): string | null {
    if (companyId.value) return companyId.value;
    try {
      return sessionStorage.getItem(LAST_COMPANY_KEY);
    } catch {
      return null;
    }
  }

  function navigateMain(section: InvoicingMainSection) {
    const cid = resolvedCompanyId();
    switch (section) {
      case 'documents':
        if (cid) {
          router.push({ name: 'invoicing-invoices', params: { companyId: cid } });
        } else {
          router.push({ name: 'invoicing' });
        }
        break;
      case 'contacts':
        if (cid) {
          router.push({ name: 'invoicing-contacts', params: { companyId: cid } });
        } else {
          router.push({ name: 'invoicing' });
        }
        break;
      case 'stock':
        if (cid) {
          router.push({ name: 'invoicing-stock', params: { companyId: cid } });
        } else {
          router.push({ name: 'invoicing' });
        }
        break;
      case 'payments':
        if (cid) {
          router.push({ name: 'invoicing-payments', params: { companyId: cid } });
        } else {
          router.push({ name: 'invoicing' });
        }
        break;
      case 'expenses':
        if (cid) {
          router.push({ name: 'invoicing-expenses', params: { companyId: cid } });
        } else {
          router.push({ name: 'invoicing' });
        }
        break;
      case 'tools':
        if (cid) {
          router.push({ name: 'invoicing-company', params: { companyId: cid } });
        } else {
          router.push({ name: 'invoicing' });
        }
        break;
    }
  }

  function navigateToolsSection(item: InvoicingToolsNavItem) {
    const cid = resolvedCompanyId();
    if (item.section === 'subscription') {
      router.push({ name: item.routeName });
      return;
    }
    if (!cid) {
      router.push({ name: 'invoicing' });
      return;
    }
    router.push({ name: item.routeName, params: { companyId: cid } });
  }

  function navigateDocumentKind(item: InvoicingDocumentNavItem) {
    const cid = resolvedCompanyId();
    if (!cid) {
      router.push({ name: 'invoicing' });
      return;
    }
    if (!item.mvpEnabled) {
      return;
    }
    router.push({ name: item.routeName, params: { companyId: cid } });
  }

  function newDocumentRouteName(kind: InvoicingDocumentKind): string {
    const map: Record<InvoicingDocumentKind, string> = {
      invoice: 'invoicing-invoice-new',
      proforma: 'invoicing-proforma-new',
      delivery_note: 'invoicing-delivery-note-new',
      order_received: 'invoicing-order-new',
      quote: 'invoicing-quote-new',
      recurring: 'invoicing-recurring-new',
      credit_note: 'invoicing-credit-note-new',
      drafts: 'invoicing-invoice-new',
    };
    return map[kind] ?? 'invoicing-invoice-new';
  }

  function newDocumentLabel(): string {
    const fallbacks: Record<InvoicingDocumentKind, string> = {
      invoice: 'invoicing.new_invoice',
      proforma: 'invoicing.new_proforma',
      delivery_note: 'invoicing.new_delivery_note',
      order_received: 'invoicing.new_order',
      quote: 'invoicing.new_quote',
      recurring: 'invoicing.new_recurring',
      credit_note: 'invoicing.new_credit_note',
      drafts: 'invoicing.new_invoice',
    };
    return t(fallbacks[activeDocumentKind.value] ?? 'invoicing.new_invoice');
  }

  return {
    companyId,
    documentNavItems,
    toolsNavItems,
    activeDocumentKind,
    activeMainSection,
    activeToolsSection,
    activeDocumentNav,
    rememberCompany,
    navigateMain,
    navigateToolsSection,
    navigateDocumentKind,
    newDocumentRouteName,
    newDocumentLabel,
    INVOICING_CONTAINER_CLASS,
  };
}
