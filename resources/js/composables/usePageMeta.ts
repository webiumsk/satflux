import type { RouteLocationNormalized } from 'vue-router';
import i18n from '../i18n';

const APP_NAME = 'satflux.io';

function documentationUrl(path: string): string {
  let origin = window.location.origin;
  const appUrl = document.head.querySelector<HTMLMetaElement>('meta[name="satflux-app-url"]')?.content;
  if (appUrl) {
    try {
      const configured = new URL(appUrl);
      if (configured.protocol === 'https:' || configured.protocol === 'http:') {
        origin = configured.origin;
      }
    } catch {
      // Keep the browser origin if the deployment URL is invalid.
    }
  }
  const url = new URL(origin);
  url.pathname = path.replace(/\/$/, '');
  return url.href;
}

/**
 * Updates document and social metadata based on the current route.
 * Call from router.afterEach or when route/dynamic content changes.
 */
export function updatePageMeta(
  route: RouteLocationNormalized,
  overrides?: { title?: string; description?: string }
): void {
  const isGuide = route.name === 'documentation-show';
  const currentUrl = isGuide || route.name === 'documentation'
    ? documentationUrl(route.path)
    : window.location.href;
  const markdownHref = isGuide ? `${currentUrl}.md` : null;
  const markdownLink = document.head.querySelector<HTMLLinkElement>('link[data-documentation-markdown]');

  // Keep the server-rendered text until the current guide's localized data arrives.
  if (!isGuide || overrides || markdownLink?.href !== markdownHref) {
    const translate = (key: unknown) => typeof key === 'string' ? i18n.global.t(key) : '';
    const title = overrides?.title ?? translate(route.meta.titleKey);
    const description = overrides?.description ?? translate(route.meta.descriptionKey);

    document.title = title ? `${title} - ${APP_NAME}` : APP_NAME;
    document.head.querySelectorAll('meta[property="og:title"], meta[name="twitter:title"]')
      .forEach(meta => meta.setAttribute('content', document.title));

    if (description) {
      let metaDesc = document.head.querySelector<HTMLMetaElement>('meta[name="description"]');
      if (!metaDesc) {
        metaDesc = document.createElement('meta');
        metaDesc.name = 'description';
        document.head.appendChild(metaDesc);
      }
      metaDesc.content = description;
      document.head.querySelectorAll('meta[property="og:description"], meta[name="twitter:description"]')
        .forEach(meta => meta.setAttribute('content', description));
    }
  }

  // Update og:url, twitter:url, and canonical on client-side navigation
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
