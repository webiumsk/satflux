# Persistent multi-agent workflow

## Resume and plan

1. Read root AGENTS.md, PROJECT_STATE, ROADMAP, DECISION_LOG and workspace/projects.yaml.
2. Inspect Git status/branch/remotes in each affected repository without modifying it.
   Resolve environment overrides first, then root-relative fallbacks. A directory's
   existence does not establish that it is the intended repository: verify its remote.
3. Identify authorization, tenant/payment/key boundaries and existing decisions.
4. Write acceptance criteria, repository dependencies, integration contracts and a
   task table: task ID, role, repository, inputs, exact writable files, prerequisites,
   outputs and review gates. Main owns shared files and status.
5. Delegate independent tasks to real agents, reconcile evidence, and implement only
   authorized scope. External repositories remain read-only during bootstrap.

## Installed configuration and discovery

Bootstrap baseline: Codex CLI 0.162.1. `.codex/config.toml` enables agents and sets
`agents.max_concurrent_threads_per_session = 3`. This field excludes the primary
thread: three specialists plus the coordinator means four simultaneous agents.
The current session itself has four total slots; never exceed runtime limits.

Custom standalone `.codex/agents/*.toml` files use `name`, `description` and
`developer_instructions`, with supported `sandbox_mode = "read-only"` defaults for
security, QA and researcher. Model, reasoning, providers and approval settings inherit
the parent; no global configuration or MCP server is added. Runtime parent overrides
can supersede sandbox defaults. Assignment instructions therefore repeat read-only
boundaries. A skill grants no additional system permission.

Roles: backend, frontend, designer, security, payments, wordpress, btcpay-plugins, qa,
devops and researcher. Designer studies existing UI patterns before redesign.
Specialists do not own project-level Git publication or deployment.

Project skills are discovered from `.agents/skills/*/SKILL.md`. Root AGENTS.md is
discovered when Codex constructs a session instruction chain. Do not restart the
current session just to reload it. In a running session without configured-role
routing, spawn a genuine subagent and tell it to read the exact role and skill files.
Fresh CLI discovery can be verified independently without a model request.

Official schema/discovery references:
[subagents](https://learn.chatgpt.com/docs/agent-configuration/subagents.md),
[configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference),
[AGENTS.md](https://learn.chatgpt.com/docs/agent-configuration/agents-md.md),
[skills](https://learn.chatgpt.com/docs/build-skills.md).
Reverify the installed CLI schema before changing configuration after upgrades.

## Review and handoff

Reviewers must be independent of implementation authors. Security reviews
authentication, authorization, isolation, encryption, webhook trust and payment
recovery. QA independently evaluates behavior and test coverage. Each report names
the reviewer, exact revision or bounded working-tree files, severity, evidence,
reproduction, remediation, test result and unresolved findings. A failed/blocked
review is not approval. Record review revisions after material changes.

For cross-repository work, establish contracts before parallel edits. Validate the
consumer against the provider version actually packaged, including missing endpoint,
older client, retry, duplicate webhook and recovery scenarios. Prepare separate PRs
with dependencies and merge/release order. Only main coordinates publication.

Approval gates are explicit applicable Product Owner authorization for implementation
scope, repository writes, promotion policy, merges and deployments/releases. Existing
authorization is sufficient within its scope; do not repeatedly ask for routine reads,
fixes or tests. Always finish a concrete reviewable result before a release approval.

Plugin installation must precede Satflux consumers of new endpoints; WooCommerce
changes follow compatible Satflux API deployment. Keep independent Git histories,
versions and rollback plans. Never change wallets or bypass pending-invoice checks
to make an integration test pass. Never use real funds.

## Checks

From the existing repository root with Python 3.11+ and the installed Codex CLI:

```bash
python3 scripts/orchestration/validate.py
python3 scripts/orchestration/probe_codex.py
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s scripts/orchestration -p 'test_*.py'
```

The first validates orchestration files without installing dependencies. The second
uses the installed CLI with temporary child-only diagnostic/state paths; it performs
configuration/discovery RPCs and a strict-parser negative control, without creating a
model thread or changing real user trust/credentials. HOME and CODEX_HOME are preserved.
It does not restart this session. If CLI startup is blocked by the sandbox, use only
the normal tool approval mechanism or report the unavailable check; never bypass it.
Role metadata is checked against the documented standalone schema; strict CLI probes
validate role session settings. Native named-role routing is a separate live-client
capability and is not inferred from those checks.
When available, also run the system skill-creator `quick_validate.py` for every skill
using a Python interpreter with existing PyYAML. Do not install PyYAML just for bootstrap.

Registry build/test commands describe entry points, not authorization to run them.
Application tests require verified disposable targets and stubbed external services.
Do not run deploy.sh, force migrations, package releases or container restarts during
orchestration validation. Product tests are required when product code changes.

## Persistent handoff

Update state after milestones: dated observation source, completed scope, changed
repositories, reviews, tests, unresolved risks, owner decisions and next allowed task.
Keep historical results attributed; do not claim an old CI count was rerun locally.
No credentials, recovery phrases, real payment identifiers or host-specific absolute
paths belong in tracked state. Skills reference this workflow rather than duplicating
the full safety policy.
