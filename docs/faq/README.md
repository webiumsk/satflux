# Reviewed multilingual FAQ

FAQ remains database-backed and editable through the existing administration. This directory is the versioned source for an initial catalog of twelve questions in English, Slovak, Spanish, German and Czech. English is canonical. No external AI/translation API or scheduled content publication is configured.

## Validate content and source freshness

```bash
npm run test:faq
php artisan faq:sync --check
```

Both checks validate the catalog, five-language completeness, safe documentation links and reviewed source fingerprints. The Node check runs in existing CI and does not bootstrap the application. The Artisan `--check` performs no database reads or writes. Each declared English source must include every available EN/SK/ES/DE/CS counterpart in that same FAQ item. Those files are fingerprinted; unavailable translations retain the existing per-article English fallback.

When a source changes, the check fails and identifies the affected content. Review every affected answer and all five translations against the changed source. Review current deployed functionality separately: repository documentation is not evidence that an unreleased feature is deployed. Once the corresponding content changes are ready, explicitly refresh the fingerprints and include both content and lock changes in review:

```bash
node scripts/faq/check.mjs --refresh-sources
npm run test:faq
```

Refreshing is an editorial acknowledgement, not evidence of semantic correctness. CI never refreshes fingerprints automatically. Adding or editing a question requires all five nonempty question/answer translations and stable unique slugs. Category labels/descriptions also require all five languages. Only local Markdown links such as `[Guide](/documentation/backup-and-recovery)` are supported; every target must be a declared English source.

## Preview and import

Deploy the reviewed migration and code through the normal approval process before operating on a deployed database. The migration adds nullable synchronization metadata; it does not import or publish FAQ. Use the normal backup/recovery process before an authorized import.

```bash
# Read-only database preview; no changes or attribution needed.
php artisan faq:sync

# Explicit database write, attributed to an existing support/admin user.
# Replace USER_ID with the local numeric staff account ID.
php artisan faq:sync --apply --user=USER_ID
```

New questions are unpublished drafts. Review and publish them through the existing FAQ administration. Repeated imports do not duplicate records. Existing publication state, usage counters, creator attribution and languages outside the managed five are preserved. The immutable import key and baseline hash identify imported rows even if an administrator renames a slug.

An existing unmanaged slug or a manual change to imported text, category assignment or order is a conflict. Any conflict aborts the entire write batch. No `--force`, delete or automatic adoption is provided. Inspect the preview and reconcile the intended change explicitly in source and administration; do not clear synchronization metadata or overwrite database rows to bypass a conflict. Unrelated FAQ entries remain untouched.

Existing published questions may overlap semantically with a new stable catalog slug. The importer cannot infer equivalence from prose: review existing entries alongside the preview before publishing drafts, and resolve duplication editorially. Do not copy merchant data into tracked files or test fixtures.

## Czech compatibility

The application uses `cs`. Legacy `cz` values remain stored and act as a fallback when canonical Czech text is empty. Editing Czech writes `cs` while preserving `cz` and other legacy language fields. A nonempty `cs` value takes precedence; conflicting texts are not silently deleted or merged. Backend request validation accepts both keys for compatibility.

## Rollback and limits

Application rollback may leave nullable synchronization metadata in place. Imported drafts and manually edited rows are content, not disposable migration fixtures; migration rollback never deletes FAQ rows. Synchronization does not remove catalog entries deleted from the source, publish content, translate at runtime, modify billing or contact external services. Source checks detect changed files, not upstream provider changes that have not yet reached repository documentation.
