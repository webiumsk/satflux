import type { RouteLocationNormalized } from 'vue-router';
import i18n from '../i18n';

const APP_NAME = 'satflux.io';

/**
 * Updates document title and meta description based on route.
 * Call from router.afterEach or when route/dynamic content changes.
 */
export function updatePageMeta(
  route: RouteLocationNormalized,
  overrides?: { title?: string; description?: string }
): void {
  const title = overrides?.title ?? (route.meta.titleKey as string | undefined);
  const description =
    overrides?.description ?? (route.meta.descriptionKey as string | undefined);

  const t = i18n.global.t.bind(i18n.global);
  const resolve = (val: string) =>
    val.includes('.') ? t(val) : val;

  document.title = title
    ? `${resolve(String(title))} - ${APP_NAME}`
    : APP_NAME;

  const metaDesc = document.querySelector('meta[name="description"]');
  const descContent = description ? resolve(String(description)) : '';
  if (descContent) {
    if (metaDesc) {
      metaDesc.setAttribute('content', descContent);
    } else {
      const meta = document.createElement('meta');
      meta.name = 'description';
      meta.content = descContent;
      document.head.appendChild(meta);
    }
  }

  // Update og:url, twitter:url, and canonical on client-side navigation
  const url = new URL(window.location.href);
  const isGuide = route.name === 'documentation-show';
  if (isGuide || route.name === 'documentation') {
    url.search = '';
    url.hash = '';
    url.pathname = url.pathname.replace(/\/$/, '');
  }
  const currentUrl = url.href;
  const ogUrl = document.querySelector('meta[property="og:url"]');
  if (ogUrl) ogUrl.setAttribute('content', currentUrl);
  const twitterUrl = document.querySelector('meta[name="twitter:url"]');
  if (twitterUrl) twitterUrl.setAttribute('content', currentUrl);
  let canonical = document.querySelector('link[rel="canonical"]');
  if (canonical) {
    canonical.setAttribute('href', currentUrl);
  } else {
    canonical = document.createElement('link');
    canonical.rel = 'canonical';
    canonical.href = currentUrl;
    document.head.appendChild(canonical);
  }

  const markdownHref = isGuide ? `${currentUrl}.md` : null;
  const markdownLink = document.head.querySelector<HTMLLinkElement>('link[data-documentation-markdown]');
  if (markdownHref && overrides && typeof route.params.slug === 'string' && /^[a-z0-9][a-z0-9-]*$/.test(route.params.slug)) {
    const link = markdownLink ?? document.createElement('link');
    link.rel = 'alternate';
    link.type = 'text/markdown';
    link.href = markdownHref;
    link.setAttribute('data-documentation-markdown', '');
    if (!markdownLink) document.head.appendChild(link);
  } else if (markdownLink && markdownLink.href !== markdownHref) {
    markdownLink.remove();
  }
  if (!isGuide || overrides) {
    document.head.querySelector('meta[data-documentation-noindex]')?.remove();
  }
}
