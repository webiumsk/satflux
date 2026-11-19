# User documentation (docs-as-code)

The public documentation at **/documentation** is served from the Markdown
files in this directory. There is no database CMS: docs change in the same PR
as the feature they describe, get reviewed, and are versioned with the code.

## Layout

```text
docs/user/
  categories.yaml          # category metadata (slug, order, localized name/description)
  <locale>/<slug>.md       # one article per file, per locale
```

- Locales: `en, sk, es, de, cs`. **`en` is the canonical set and the fallback** -
  every article must exist in `en/`. Other locales are optional; a missing
  translation falls back to the `en` file automatically.
- The **slug** is the filename without `.md` and must match across locales
  (e.g. `en/create-account.md` and `sk/create-account.md`). Use
  `lowercase-with-dashes`.
- The article URL is `/documentation/<slug>`. The initial HTML uses the
  canonical English guides. The interactive site keeps its locale selection.
- The clean Markdown body is available at `/documentation/<slug>.md`.
  Add `?locale=sk` (or `en`, `es`, `de`, `cs`) to request a translation.
  The response reports its actual language, including English fallback, in
  the `Content-Language` header.
- `/llms.txt` is a short index of selected public guides, generated from their
  canonical titles and URLs. `/documentation` lists the full public catalog.
- Googlebot and OAI-SearchBot can fetch the public documentation JSON used by
  the interactive site. The JSON responses carry `X-Robots-Tag: noindex` so the
  article HTML can be the search result.

## Article front-matter

Each `.md` file starts with YAML front-matter, then Markdown body:

```markdown
---
title: Create your Satflux account
category: getting-started      # a slug from categories.yaml
order: 2                       # sort order within the category
meta_description: Short summary for SEO and the article list.  # optional
updated: 2026-08-27            # optional; shown as "updated" and used in the sitemap
---

# Heading

Body in **Markdown**. Tables, code blocks and safe links are supported.
```

## Rendering & safety

Markdown is rendered server-side with `league/commonmark` (GitHub-flavored:
tables, strikethrough, task lists). Raw HTML in Markdown is escaped, and the
frontend additionally sanitizes the output with DOMPurify, so only a safe
subset of tags/attributes is ever shown. Only YouTube iframes are allowed.

## Adding a category

Add an entry to `categories.yaml` (slug, order, localized `name`/`description`),
then reference its slug from an article's `category:`.

## Implementation

- Reader: `app/Services/Documentation/UserDocsRepository.php`
- API: `app/Http/Controllers/DocumentationController.php` (`/api/documentation`, `/api/documentation/{slug}`)
- Public HTML, Markdown and text index: `app/Http/Controllers/PublicDocumentationController.php`
- Interactive frontend (consumes the same API): `resources/js/pages/documentation/`
