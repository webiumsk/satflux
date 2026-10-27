# Automatic Chorala changelog

## Behavior and ownership

Satflux PRs carry reviewed user-facing JSON notes under `.changelog/entries`.
After successful `CI` on an owned `master` push, the Chorala workflow creates
drafts through the existing project API. After a production deployment, the
optional deployment hook confirms the captured Git SHA against the host and PHP
checkout, checks public HTTPS `/api/health`, checks the runtime SHA again, then
dispatches publication for that exact revision. Publication separately requires
successful master-push CI on the same SHA. A deployment arriving before CI is
saved in the journal and reconsidered after a later successful CI event.

This is Satflux-only integration. It does not change Chorala, WordPress or BTCPay
repositories. Existing imported history is not backfilled, republished or edited.
The first note describes raffle creation in PR #404. Publication uses Chorala's
ordinary notification/webhook behavior; a draft does not send publication notices.

| Task | Owner / files | Acceptance |
| --- | --- | --- |
| Fragment validation and API synchronization | Coordinator, `scripts/changelog/sync.mjs`, `check.mjs`, reviewed fragments | Trusted exact source, immutable notes, draft/publish distinction |
| Durable mutation journal | Coordinator, `scripts/changelog/git-ledger.mjs` | Fixed ref, target identity, intent before mutation, conflicting writers rejected |
| CI and deploy wiring | DevOps specialist, dedicated workflow, dispatcher and minimal `deploy.sh` hook | No PR/fork secrets, explicit production, health/SHA checks, nonfatal dispatch failure |
| Independent review | Security and QA specialists, read-only scope | Failure/retry/uncertainty, cross-target/source and token boundaries checked |

## One-time configuration

The integration stays disabled until explicitly configured. No credentials are
included in source and no activation, initialization, live publication or deployment
is performed while preparing this PR.

After the reviewed workflow is merged to `master`, configure repository variables:

| Variable | Value |
| --- | --- |
| `CHORALA_CHANGELOG_ENABLED` | `true` when ready to activate |
| `CHORALA_API_URL` | Public HTTPS API base, including `/api/v1` and any reverse-proxy prefix |
| `CHORALA_PROJECT_ID` | The intended Satflux project ID |

Configure repository secret `CHORALA_CHANGELOG_API_KEY` with a dedicated Chorala
project key containing **both read and write scopes**. Write does not imply read.
Never use the public widget key or put this key into frontend/Vite configuration.
The automatic GitHub token needs Contents write for its journal branch and Actions
read for the exact CI check. Repository/organization rules must permit those
workflow permissions; the code does not bypass rules.

Run the **Chorala changelog** workflow once on `master` with mode `initialize`.
This explicitly creates `automation/chorala-changelog-state` in the owned Satflux
repository. It creates no Chorala entries. The journal binds repository, complete
canonical API base and project ID, and contains only source identities, hashes,
Chorala entry IDs and mutation state. It contains no keys or merchant data.
If a target changes, an existing journal is not silently repurposed.

If the latest master CI finished while integration was disabled, run mode `draft`
once with its full 40-character SHA. Later successful master-push CI prepares
drafts automatically. Existing feature-branch PR checks cannot activate the workflow.

On the deployment host, configure these values in the protected operator
environment used to invoke `deploy.sh`:

```text
CHORALA_CHANGELOG_ENABLED=true
CHORALA_DEPLOY_ENVIRONMENT=production
CHORALA_DEPLOY_HEALTH_URL=https://satflux.io/api/health
```

Provide exactly one of `CHORALA_DEPLOY_TOKEN` or `CHORALA_DEPLOY_TOKEN_FILE`.
Use a repository-scoped GitHub token with **Actions write** for workflow dispatch;
the deployment host does not need Git journal write permission or the Chorala key.
A token file must be a private, non-symlink regular file readable inside the PHP
container, with no group/other permissions. Keep it outside tracked files. The
hook passes environment names to Docker, never token values in arguments.

The existing deployment script reads only its previous Compose/branch selectors
from the deployment env file. These new hook variables must be exported through
the protected operator environment; adding arbitrary variables to `.env.standalone`
alone does not export them to this hook. The dispatcher uses Node already present
inside the PHP image and does not install anything on the host.

## Deployment and retry boundary

The hook runs only at the successful end of the existing deployment script. It
does not initiate a deployment or weaken existing failure exits. A matching PHP
checkout plus HTTP boot-health response is evidence for dispatch, not exhaustive
validation of every worker, external provider or business journey. An operator
may also explicitly attest a completed deployment through mode `publish`, with
`environment=production` and the exact deployed SHA.

During the first rollout, an already-running older deployment script may not
execute the newly added hook. After confirming that deployment, dispatch only
the changelog workflow for its recorded SHA. Do not run another application
deployment solely to publish or retry a changelog.

If dispatch fails or its response is lost, the deployment remains successful and
prints a warning with the exact SHA. Retry the changelog dispatch/workflow only.
Use the SHA from the completed deployment, not the newest branch head. A 204
dispatch response confirms queue acceptance, not completed publication.

## Duplicate prevention and recovery

Both triggers share one non-cancelling GitHub concurrency group with `queue: max`.
The journal adds compare-and-swap protection: each write creates a uniquely named
commit whose parent is the observed state head, then advances only the fixed ref
without force. Competing writers cannot both claim the same initial state, even
for identical payloads. Normal synchronization never initializes a missing journal.

Before each external create/publication request, its intent is committed to the
journal. After a lost response or failed acknowledgement:

- One exact remote source/content match can reconcile an uncertain create.
- No matching row with a pending create stops synchronization; no second POST is
  attempted. Multiple matches also require review.
- An uncertain publish can reconcile an already-published row, without another
  draft-to-published transition. A still-draft result stays blocked.
- Deleted managed entries are not recreated. Changed text, labels, audience or
  linked posts stop publication; published entries are never reset to drafts.

Keep the state branch and its history backed up with the repository. Do not delete
the branch, clear pending entries or force-push a reset as a retry mechanism.
For a missing or unresolved result, inspect the intended Chorala project and exact
public PR source, establish whether the original request committed, then perform
only a narrowly reviewed reconciliation. A pending request with no evidence stays
blocked until that uncertainty is resolved.

## Current provider compatibility

Verified provider source: Chorala `a164d0b71d9a6e63286271dd502c999dbd24f482`.
Its collection API returns raw arrays, create returns a raw draft and the project
key is checked separately for read/write. The synchronizer validates these shapes,
rejects redirects and credential-bearing/insecure API URLs, bounds responses and
timeouts, and never prints transport response bodies or keys.

That provider's partial-update schema can supply default empty labels/linked posts.
Publication therefore explicitly preserves the observed arrays and audience while
omitting title/body. All managed content/audience is fingerprinted before publication.
Reserve these automatically managed drafts for the PR-based workflow: simultaneous
manual editor changes are not atomically protected by the old provider API. A narrow
read/PATCH race remains for those metadata fields; no conditional-update API is
invented here. Existing provider PR #5 fixes partial defaults but is not deployed by
this change.

Chorala commits published state separately from queuing notifications. A published
row does not prove delivery, and this integration does not promise exactly-once
email/webhook delivery. Never reset publication status merely to resend a notice.

## Verification

Run the dependency-free offline suites and static checks:

```sh
node --test scripts/changelog/*.test.mjs
node scripts/changelog/check.mjs
bash -n deploy.sh
```

The CI job validates fragments from the actual Git commit, including PR commits
without exposing publication credentials. Tests use disposable Git repositories,
fake GitHub/Chorala transports and fake deployment commands; they do not run the
deployment script or contact production. Security and QA independently review the
final source. No frontend/backend dependency, schema, billing or permission change
is required. New-head GitHub checks remain separate evidence from local tests.

Disable the repository and host opt-in flags to stop future synchronization while
retaining journal history and existing drafts/publications. A code rollback should
retain the journal for any later reactivation; it does not withdraw already-public
notes or undo sent notifications.

## Review record

The coordinator and `changelog_devops` authored separate owned files. Independent
`changelog_security` accepted the final bounded source after closing the identical
Git-commit CAS edge and inherited-property lookup finding. Independent
`changelog_qa` repeated all 53 maintained cases (27 sync, eight ledger, 18 dispatch)
and passed 21 additional adversarial cases. Security also passed memory-only
uncertainty/transport checks and an independent identical-intent concurrency probe.
Both reviewers accepted the documented provider boundaries without remaining
blocking findings. Node/Bash syntax, YAML parsing, fragment checks and whitespace
checks pass. Source preparation used synthetic transports only; activation and
new-head GitHub CI remain separate evidence.
