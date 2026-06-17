/** Dashboard Invoicing tab deep link (SPA + Inertia). */
export const dashboardInvoicingTabPath = '/dashboard?tab=invoicing';

export const dashboardInvoicingTabRoute = {
  path: '/dashboard',
  query: { tab: 'invoicing' as const },
};

export function isDashboardInvoicingTabActive(
  path: string,
  queryTab: unknown,
): boolean {
  return path === '/dashboard' && queryTab === 'invoicing';
}

export function isInvoicingNavActive(
  path: string,
  queryTab: unknown,
): boolean {
  return path.startsWith('/invoicing')
    || isDashboardInvoicingTabActive(path, queryTab);
}
