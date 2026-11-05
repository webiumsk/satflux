/** Public documentation destinations and illustrative screenshots, never payment credentials. */
export const showcaseTools = [
  { key: 'lightning', anchor: 'how-it-works-ln-address', symbol: '@', docs: 'store-lightning-addresses' },
  { key: 'woocommerce', anchor: 'how-it-works-eshop', symbol: '↗', docs: 'https://github.com/webiumsk/satflux-woocommerce/blob/main/readme.txt', secondary: 'https://wordpress.org/plugins/satflux-for-woocommerce/' },
  { key: 'paybutton', anchor: 'how-it-works-pay-button', symbol: '⌘', docs: 'pay-button' },
  { key: 'bankqr', anchor: 'how-it-works-bank-qr', symbol: '▤', docs: 'sepa-instant-qr' },
  { key: 'crowdfund', anchor: 'how-it-works-crowdfund', symbol: '◷', docs: 'what-is-satflux' },
  { key: 'exports', anchor: 'how-it-works-exports', symbol: '↓', docs: 'csv-xlsx-exports' },
  { key: 'cashu', anchor: 'how-it-works-cashu', symbol: '◉', docs: 'cashu-lightning-settlement' },
  { key: 'stores', anchor: 'how-it-works-stores', symbol: '⊞', docs: 'creating-your-first-store' },
] as const;

export type ShowcaseToolKey = typeof showcaseTools[number]['key'];
export type ShowcaseStoryKey = 'payments' | 'invoicing' | 'events';
export type ShowcaseScreenshotKey = 'overview' | ShowcaseStoryKey;

export const showcaseScreenshots: Record<ShowcaseScreenshotKey, string> = {
  overview: '/img/landing-showcase/satflux-overview-v6.png',
  payments: '/img/landing-showcase/satflux-payments-v6.png',
  invoicing: '/img/landing-showcase/satflux-invoicing-hover-v6.png',
  events: '/img/landing-showcase/satflux-events-v6.png',
};

/** Only administrator-configured HTTPS demo pages may be embedded after an explicit click. */
export function readShowcaseDemoUrl(): string | null {
  const raw = document.getElementById('app')?.dataset.homepageDemoPosUrl;
  if (!raw) return null;
  try {
    const url = new URL(raw);
    return url.protocol === 'https:' && !url.username && !url.password ? url.href : null;
  } catch {
    return null;
  }
}
