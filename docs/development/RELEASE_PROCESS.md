# Satflux release PRs

## Scope and acceptance criteria

The Product Owner selected the release PR approach on 2026-11-07. This change
configures the owned `webiumsk/satflux` repository only. Preparing automation does
not merge a PR, dispatch a workflow, create a release, change repository settings
or deploy the application.

- Use one root `node` release strategy, with `2.0.0` as the current version.
- Keep `package.json`, both root version fields in `package-lock.json` and
  `.release-please-manifest.json` equal. Generate technical `CHANGELOG.md` in the
  release PR; do not replace reviewed user-facing Chorala fragments.
- Start initial commit collection after `f7118b10f2cbde07f15edc68bc154ceb2a94718d`,
  the verified master base. Do not backfill earlier history or fabricate a `2.0.0`
  tag/release. Once a managed release exists, Release Please uses that boundary.
- Prepare or update the release PR only after successful owned master-push CI.
  Reject fork/PR events and failed, cancelled or stale CI sources.
- Retain human review and merge approval. Before invoking Release Please, verify
  successful master-push CI for the merge revision of each pending release candidate.
  Publish source tags and GitHub Releases through the standard action. Do not auto-merge,
  approve PRs, publish packages, deploy, or publish Chorala entries from this workflow.
- Use pinned Actions and the repository `GITHUB_TOKEN`; install no tools or secrets.
  Keep privileged execution on trusted master code and test event rejection offline.

| Task | Owner / writable files | Dependencies and evidence |
| --- | --- | --- |
| Release automation | DevOps specialist: dedicated workflow, release config/manifest, `scripts/release/`, dedicated offline CI job | Verified base, pinned upstream action, offline event/config checks |
| Integration and documentation | Coordinator: this runbook, README/CLAUDE version guidance, shared project state | Final implementation and independent review |
| Security review | Independent security specialist, read-only implementation/docs | Exact reviewed file hashes, permissions/event/token scenarios |
| QA review | Independent QA specialist, read-only implementation/docs | Offline validators, invalid-event/version fixtures, preserved public changelog behavior |

No other repository needs a branch, version bump or deployment for this change.
The runtime version endpoint and deployment-bound Chorala publication retain their
existing contracts. Existing unrelated working-tree changes stay outside this branch.

## Everyday workflow

1. Keep application versions unchanged in feature/fix PRs. Write Conventional
   Commit messages in English. For squash merges, the final squash commit must
   carry the correct type; using a conventional PR title makes this easier. For
   ordinary merge commits, ensure the underlying commits have the correct types.
   A conventional PR title alone does not rewrite those commits.
2. Merge the reviewed feature/fix PR and wait for successful master-push `CI`.
   Release Please collects changes into one release PR against `master`.
3. Review the proposed version, package/lockfile/manifest changes and technical
   changelog. Several feature/fix PRs may be combined into one release.
4. Approve CI execution for the bot-created release PR if GitHub shows
   **Approve workflows to run**. Wait for the usual checks at its latest revision,
   then merge the release PR after Product Owner approval.
5. After successful master CI, the release workflow creates `v<version>` and a
   GitHub Release for the merged release revision. This identifies source code;
   it does not attest a production deployment.
6. Deploy only through the separately approved deployment process. The existing
   Chorala integration publishes reviewed user-facing notes only after confirmation
   of the exact production deployment and its successful master CI.

| Commit | Effect from `2.0.0` |
| --- | --- |
| `fix: correct invoice total rounding` | Patch, `2.0.1` |
| `feat: add expense search` | Minor, `2.1.0` |
| `feat(api)!: change the export response` or a `BREAKING CHANGE:` footer | Major, `3.0.0` |

When several changes are included, the largest required increment wins. Maintenance
commits such as `docs:`, `ci:` and `chore:` do not normally trigger a release by
themselves. The setup PR may therefore finish successfully without immediately
opening a release PR. Inspect the automation logs before interpreting that as failure.
Keep the automation's generated PR metadata and release labels intact.

## Setup and boundaries

The workflow uses `GITHUB_TOKEN`, with write permissions limited to the release job
for contents, pull requests and required release labels. It needs no personal token.
GitHub repository/organization policy must allow GitHub Actions to create pull
requests. The read-only settings check on 2026-11-07 returned
`can_approve_pull_request_reviews=false` and default workflow permissions `read`.
Creating release PRs is therefore blocked until the Product Owner authorizes enabling
**Allow GitHub Actions to create and approve pull requests**. This implementation
does not change that setting or approve PRs.

The same read-only check found no classic branch protection or effective branch
rules for `master`. CI and human merge approval remain procedural requirements,
not a remotely enforced guarantee. Adding required checks/approvals is a separate
repository settings decision; this change does not silently introduce protections.

GitHub currently places CI triggered by `GITHUB_TOKEN`-created/updated pull requests
in an approval-required state. A user with write access must approve those runs.
Do not weaken checks or introduce a personal token to avoid that step. A separately
authorized GitHub App integration can be considered later if unattended CI is needed.

The legacy `npm run version:*` commands only update `package.json`. Do not use them
to prepare routine releases or independently bump versions in feature branches.
Release Please's `node` strategy synchronizes the package, existing lockfile and
version manifest in its release PR. The offline CI check rejects version drift.

The application footer gets its version from `/api/version`, which reads the
deployed `package.json`. The README links to that source instead of duplicating a
version literal. Optional `VITE_APP_VERSION` consumers retain their existing behavior;
frontend build metadata and runtime version API unification are separate work.

## Recovery and rollback

Do not rerun deployment to retry release automation. Investigate workflow logs and
the existing release PR/tag first. Recovery uses a rerun of the current master
`CI` workflow. There is no separate manual release trigger.
If the branch advances while an older CI run finishes, wait for the newest successful
master run instead of releasing an unchecked head. Check the tag's exact target SHA
and the merged release PR before treating a GitHub Release as complete.

An earlier pending release whose own merge revision never passed master-push CI
blocks release creation even if a later fix passes CI. Do not relabel it or rerun
deployment blindly; review that candidate and repair its CI or prepare an explicitly
reviewed recovery. A successful build of a later commit is not evidence for the
earlier candidate's code.

The API checks are preflight checks. Release Please rereads mutable `master` and
pending-PR metadata and has no immutable source-SHA input, so a branch/label change
after validation cannot be excluded atomically. Keep merges coordinated while
release automation runs. Tags target the release PR's merge revision, not the
latest master head. Offline checks do not establish a repository-wide merge lock.

Do not delete or move published tags, edit old release versions, or force-push to
repair automation. Make corrections in a new reviewed PR. A source rollback or
production rollback must retain version history and use the separate deployment
approval and rollback procedure. To pause automation, use the normal repository
workflow controls with operator approval; do not remove branch protections.

## Upstream evidence

- [Pinned Release Please Action v4.4.1](https://github.com/googleapis/release-please-action/tree/5c625bfb5d1ff62eadeeb3772007f7f66fdcf071)
- [Manifest bootstrap and configuration](https://github.com/googleapis/release-please/blob/v17.3.0/docs/manifest-releaser.md)
- [Bundled Node strategy package and lockfile updates](https://github.com/googleapis/release-please/blob/v17.3.0/src/strategies/node.ts)
- [Current GitHub token and CI triggering behavior](https://docs.github.com/en/actions/concepts/security/github_token)
- [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/)
- [Existing public changelog contract](../CHORALA_CHANGELOG_AUTOMATION.md)

Action pins, final local checks and independent review evidence are recorded with
the implementation milestone. Live release creation must be verified after the
approved merge; offline checks do not establish GitHub runtime behavior.
