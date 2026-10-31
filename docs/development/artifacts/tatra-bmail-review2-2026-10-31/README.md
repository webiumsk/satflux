# Tatra review follow-up on 52fb0e0f

CodeRabbit review 5720326942 identified four valid follow-ups after the positive
Tatra implementation. This delta preserves supported automatic confirmation.

1. Non-UTF-8 original MIME now enters manual review before JSON serialization or
   subprocess verification. Permanently unsupported input no longer consumes
   transient queue retries. A real form-delivery regression verifies no process
   or provider call and the expected review audit.
2. The old manual-only qualification README explicitly identifies its cd951b3b
   snapshot and points to the later automatic implementation. Original reports
   and manifest hashes are retained.
3. The architecture note describes the missing verifier as the pre-change state.
4. The operator runbook requires non-overlapping store/company Mailgun routes:
   store ps addresses go to .mime; company pay addresses retain parsed intake.
   stop/catch-all guidance prevents accidental company-import interruption.

[Independent security](security.md) accepts its [five-file delta](security-reviewed-source.json),
manifest SHA-2564074cfb2890aa211fde50af57dc80a45ff23be63340a3ca7ac1c10d082508440.
The regenerated API coverage additionally binds the updated service source.
Coordinator MIME/legacy-company regressions pass 22 tests / 80 assertions, affected Pint and targeted
PHPStan pass. [Independent QA](qa.md) passes 22 MIME/API tests / 283 assertions, zero skips,
and binds the [six-file snapshot](qa-reviewed-source.json), including current
generated API coverage. Its [post-delivery check](post-delivery-verification.json)
confirms both new archives plus all70 older archives/four metadata files.
The Node verifier, strict credit parser, canonical identity, locale strings and
provider archives are unchanged; their earlier qualification remains scoped to
its original snapshots. No new bank sample, route mutation or deployment occurs.
