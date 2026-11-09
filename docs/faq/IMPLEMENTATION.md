# FAQ content automation

## Approved scope and acceptance

The owner approved twelve source-backed FAQs in EN/SK/ES/DE/CS, repeatable preview/import, locale/link completeness checks, source-change detection, and Czech legacy compatibility. No AI provider, production database operation, publication, billing change or external repository change is part of implementation. Existing unrelated primary-checkout work is preserved; implementation uses `feat/faq-automation` from owned master `f7118b10`.

## Integration contract

- `docs/faq/catalog.json`: `version: 1`, `categories` entries with `slug`, `order`, localized `name` and `description`; `items` entries with stable `slug`, `category` slug, `order`, localized `question` and `answer`, and `sources` as repository-relative documentation file paths. Exactly EN/SK/ES/DE/CS are required. Answers use plain text, optional bold/emphasis and local Markdown links to `/documentation/<slug>`.
- `docs/faq/sources.lock.json`: repository-relative source paths mapped to SHA256. A changed/missing source fails validation and requires review of the corresponding FAQ and all translations before explicitly refreshing its fingerprint. English is canonical. Translated source files are tracked when available; missing translated articles retain the existing documented English fallback.
- `php artisan faq:sync --check`: offline validation only, no database access. Default `faq:sync` previews database changes. `--apply --user=<local numeric user ID>` imports drafts with existing staff attribution; explicit support/admin actor is required. No automatic publishing, deletion or force overwrite. Repeated imports are idempotent; existing unowned slug collisions and manual edits are conflicts, and any conflict aborts the entire batch. Existing published state, counters and unrelated locales are preserved. Database writes and synchronization metadata are transactional.
- Legacy Czech `cz` remains preserved and usable as a fallback; canonical `cs` wins when nonempty. Editor must retain legacy/extra language data while presenting the five supported application locales. No destructive migration of conflicting text.
- FAQ links are rendered only after sanitization and restricted to local documentation routes; keyboard state is exposed on accordion controls. Existing auth and admin middleware are unchanged.

## Ownership and dependencies

| Task | Role | Writable ownership | Dependencies and evidence |
| --- | --- | --- | --- |
| Import and backend Czech compatibility | backend | New FAQ sync command/service/migration/tests; FAQ models/request/category controller if necessary | Catalog contract above; validate dry-run, rollback, conflicts, counters, actor and legacy text |
| Editor and public rendering | frontend | MultilanguageEditor.vue, FaqAccordion.vue, related new frontend tests only | Five locale UI, preserved legacy data, safe docs links and keyboard tests |
| Translated catalog | designer | docs/faq/catalog.json only | Read actual docs, twelve concise questions in five languages, cited sources and no unsupported provider/pricing claims |
| Integration and checks | main | Source lock, validator script, CI/package wiring if needed, runbook, shared project state | Integrate independently owned changes, run checks and record evidence |
| Independent reviews | security, qa | Read-only code; private bounded test artifacts only | Exact final changed-file scope, adversarial/regression checks and explicit acceptance |

## Delivery

One Satflux branch; no BTCPay or WooCommerce dependency. Migration and application code deploy together after separate owner approval. Reverting application code can leave nullable sync metadata in place; do not drop imported merchant-edited FAQ rows during rollback. Imported drafts can be reviewed and published using the existing administration. No deployment result is inferred from source or tests.
