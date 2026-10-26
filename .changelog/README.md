# Reviewed user-facing changes

Add one regular UTF-8 JSON file under `entries/` for a user-visible change. Review
the title and body with the implementation PR. Internal maintenance need not
produce a public entry. Do not copy raw PR discussions, logs or private identifiers.

```json
{
  "id": "a-stable-change-id",
  "source": "https://github.com/webiumsk/satflux/pull/404",
  "title": "Short user-facing title",
  "body": "What changed and how it helps users. Markdown is supported.",
  "labels": ["fix"]
}
```

The filename must equal `id` plus `.json`. IDs use lower-case letters, digits and
hyphens, up to 80 characters. The source must be a canonical public Satflux PR URL
and unique across fragments. Use one combined note per PR. Titles are limited to
200 characters, bodies to 20,000; labels must be nonempty strings.

Retain committed fragments and treat synchronized content as immutable. A new
correction should have its own reviewed PR/note, or use a separately reviewed
operator reconciliation. The synchronizer never edits an existing entry's text
to match a later fragment. It adds a public Details link to the source PR.

Successful owned-master CI prepares drafts only. The deployment hook requests
publication for the exact deployed commit, and publication waits for successful
CI on that same commit. PR checks and pushes to feature branches do not publish.

See [automation setup and recovery](../docs/CHORALA_CHANGELOG_AUTOMATION.md).
