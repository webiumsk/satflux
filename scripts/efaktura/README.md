# Read-only ePostman sandbox check

This diagnostic uses Satflux's actual `SapiSkClient`, fixed to
`https://testpostman.slovakodata.com`. It authenticates and inspects a bounded
incoming listing and at most three original documents. It never sends an invoice,
acknowledges receipt, imports an expense, migrates a database or changes a company.
An empty inbox proves only that authentication/listing succeeded.

## Enter credentials privately

From the main Satflux checkout, run the helper in a real local terminal:

```sh
python3 storage/app/private/worktrees/efaktura-epostman-inbound/scripts/efaktura/prepare_sandbox.py
```

If this feature is the main checkout, use `python3 scripts/efaktura/prepare_sandbox.py`.
Enter a newly created **sandbox SAPI** client ID, client secret and participant ID
from the test portal. All three prompts are hidden. This is distinct from a REST
API key. Do not paste the values into chat, command arguments or issue reports.
Rotate credentials previously shared through chat before entering replacements.

The helper writes an exclusive owner-only file at
`storage/app/private/epostman-sandbox/.env.credentials.json`, under a `0700`
directory. Existing files, symlinked paths and unsafe permissions fail closed.
It never overwrites credentials or changes unrelated permissions. The file is
Git-ignored and must stay private; do not print it with `cat` or copy it to a PR.
After saving, tell the coordinator only that sandbox credentials are ready.

## Run the probe

The coordinator uses a dedicated disposable container with matching user ID,
read-only source/vendor/credential mounts, a synthetic `.env` and verified absence
of `.env.testing`,
uncached configuration, in-memory SQLite, array cache/session/mail, synchronous
queue and null logging. Production configuration and cached config are not mounted.
Only the fixed sandbox origin is used by the diagnostic; redirects remain disabled.

Inside that isolated environment, the invocation is:

```sh
php artisan efaktura:probe-sandbox --credentials=/run/epostman-secrets/.env.credentials.json
```

Do not run this command in the normal production container. The command rejects
non-isolated resolved settings before reading credentials. It emits anonymous
stage/count/status fields only, never identifiers, document content, credentials,
tokens or raw provider errors. Authentication failures are not retried automatically.

## What the result establishes

Successful authentication is followed by a bounded receive listing, including
cursor checks. When documents exist, detail retrieval validates the provider's
document/receiver metadata through the real client. Documents remain unacknowledged
and available for the later full import test. Nothing is stored by this probe.

A complete receiving test still needs a synthetic invoice and credit note sent
to the sandbox participant, preferably from another Access Point. Then use the
isolated Satflux application to verify durable original custody, import, correct
amounts, provider acknowledgement and retry. That step requires a persistent
disposable test database and encrypted staging; it must not acknowledge documents
into an in-memory database that disappears when the process ends.

The probe does not establish production activation, provider archive guarantees,
legal readiness or compatibility of outgoing Satflux invoices.
