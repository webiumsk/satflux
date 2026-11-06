# Local workspace

## Evolu relay source publication authorized - 2026-11-06

The owner requests pushing the independently accepted additional-relay feature.
Prepare one scoped commit on `feat/evolu-custom-relay` in the owned Satflux
repository, then verify the actual remote tip. Product bytes remain runtime16
`5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`, accepted independently by QA
and security. Exactly35 paths include those30 reviewed source/test/doc files and
five coordination records; no unrelated primary edits or private fixtures belong
in the commit. Prior frontend1,940/192, PHP58/432 and static/build/API results
remain attributed to the reviewed bytes; no product checks are repeated for this
publication-only documentation update.

Source push is authorized. PR creation, merge and deployment remain separate;
actual publication outcome and fresh remote checks must be verified after push.
The primary checkout remains on its existing branch with unrelated work preserved.

## Additional Evolu relay worktree - 2026-11-06

Retain `storage/app/private/worktrees/evolu-custom-relay` on
`feat/evolu-custom-relay`, base local origin/master `2154101e`. Source and five-locale
docs are uncommitted and locally reviewed; the primary checkout stays on its
existing branch with unrelated changes preserved. Private fixtures, manifests,
QA/security reports and gate logs are in that worktree's
`storage/app/private/reviews/evolu-custom-relay/`.

QA/security accept runtime16 `5bbc13eb...dc39d7` / source30 `4594a9d3...beb9f`.
Full frontend1,940/192 and related PHP/API/static/build gates pass; no unresolved
finding remains. Full evidence: the worktree's `docs/development/EVOLU_CUSTOM_RELAY.md`.
No commit, publication, merge, deployment or production verification occurred.
Next: owner review/applicable publication approval, then separate release approval.

## PR430 review corrections ready in the retained feature worktree - 2026-11-06

`storage/app/private/worktrees/landing-showcase` retains the independently accepted
review-fix scope on `feat/landing-showcase`, based on published `665cc2f6`.
Runtime15 `f936a8ae...68369f` / source-tests22 `c5c36215...d1bed` match QA/security
acceptance. Main owns source publication to the same owned branch and verification
of the actual remote commit/checks. The dirty primary checkout, design originals,
other worktrees and external repositories are preserved. No merge/deployment.

Evidence, exact scope, original CI attribution and known unchanged header limits
are in [the review record](LANDING_SHOWCASE_REVIEW.md); private reproducible evidence
is retained beneath the feature worktree's existing review evidence directory.

## Landing showcase publication handoff - 2026-11-06

The Product Owner authorizes commit/push from the existing durable
`storage/app/private/worktrees/landing-showcase` worktree. Main owns publication
of exactly 82 accepted paths to `feat/landing-showcase` in owned Satflux; the dirty
primary checkout and other worktrees remain preserved. Reviewed runtime70 and
source/tests77 manifests retain the original accepted fingerprints; publication
records the sole two-space CSS blank-line normalization separately. Record the resulting commit,
remote verification and clean feature-worktree status in the publication receipt
and coordinator handoff; no merge or deployment follows from the push.

## Landing showcase isolated implementation - 2026-11-05

`storage/app/private/worktrees/landing-showcase` retains the authorized implementation
on `feat/landing-showcase`, based on owned `origin/master` `e584490d`. Existing primary
checkout changes and design originals are preserved. Only Satflux is modified.
Implementation is complete, local/uncommitted and independently accepted on final
runtime70 `8466ca0e...ab90f3f` / source-tests77 `fbb90756...4493576`. No push, merge
or deployment has occurred. Final frontend1,882/188 and PHP44/279 pass with the
recorded frontend/PHP/style/build/graph gates. The worktree has its own AST graph.

The worktree has its own synthetic ignored test environment and output directories;
Node dependencies are reused via a dependency symlink. PHP tests run in an isolated
network-disabled container with in-memory SQLite and an exact-lock read-only vendor
mount. Browser checks use a separate local harness, inert fixtures and blocked
external HTTP/WebSockets. Do not infer production/backend persistence or deployment
from frontend screenshots. Final reviewed scope and actual checks are recorded in
[the implementation contract](LANDING_SHOWCASE_IMPLEMENTATION.md).

## Expense reconciliation publication authorized - 2026-11-05

The owner requests commit/push of the reviewed 51-path `feat/expense-reconciliation`
worktree to owned Satflux. Publication runtime23 `620c7f00...956f2` and source/test/CI35
`c947959b...8557b` differ from reviewed hashes only by one removed EOF LF in the
bank-value helper. Main will verify the remote tip/tree and record
the actual outcome in the coordinator's current workspace. Primary checkout work,
PR creation, merge and deployment remain separate.

## Expense reconciliation implemented and reviewed - 2026-11-05

`storage/app/private/worktrees/expense-reconciliation` retains the local,
uncommitted implementation on `feat/expense-reconciliation`, based on freshly
fetched `origin/master` at `cb55aab0`. Only the owned Satflux repository changed.
The primary checkout remains on its prior `fix/sepa-confirmation-isolation` branch
with unrelated work preserved; its workspace record contains the other retained
initiatives.

Independent QA/security accept runtime23 `982868ca...5ef95` and source/test/CI35
`edf41386...46850`. Final frontend1,760/177, focused SQLite129/563 and actual
PostgreSQL2/36 pass. Lint/typecheck/locales/build/API/Pint/PHPStan pass without
raising thresholds. Six broad-PHP DNS validation failures reproduce on unchanged
base; do not claim global/remote CI green.

The ignored worktree graph was updated with AST extraction only; the original
primary graph is preserved. Disposable PostgreSQL used only synthetic tmpfs data
and was removed after review. Complete contracts, exact hashes, resolved findings
and validation limits: [implementation record](EXPENSE_RECONCILIATION.md).

No commit, push, PR, publication, merge, deployment, real payment or merchant-data
access. Next is owner review and applicable source-publication/CI/merge/deployment
approval. Relay redundancy, QR expense payments and the remaining suggestions are
separate proposals.
