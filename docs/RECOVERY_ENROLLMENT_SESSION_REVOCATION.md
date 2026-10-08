# Recovery enrollment and password-session revocation

Deliberate enrollment through `POST /api/account/recovery-key` makes the
account recovery-only. The first enrollment replaces the password with an
unknown random password and rotates the remember token in the same locked
transaction as the recovery key and audit record. A failed audit rolls back
all credential changes.

The enrolling browser keeps its authenticated session and CSRF token. Its
session is updated to the new password hash only after the transaction
commits. Other password sessions are rejected on their next authenticated
request: Sanctum already checks the session hash for stateful API requests,
and the web middleware now checks it for direct authenticated web routes.
Requests already executing before enrollment commits are not cancelled.

Password-derived remember cookies cannot authenticate a recovery-enrolled
account. The user model also enforces this when the credentials were issued
by an older version that did not rotate them during enrollment. Accounts
without recovery enrollment retain password and remember-me login.

Same-key enrollment retries do not rotate credentials again or duplicate the
audit event. The retired password returns the normal invalid-credentials
error; signed recovery remains the explicit way to sign back in. Login
rechecks account state after password validation to reject an enrollment
that completed during validation.

This change does not retrospectively terminate active sessions belonging to
accounts enrolled before deployment, revoke API tokens, or change account
switching and cross-tab automatic recovery behavior. Those are separate
flows. No recovery phrase is sent to the server or persisted by these tests.

## Focused coverage

`tests/Feature/RecoveryEnrollmentRevocationTest.php` sends actual encrypted
browser cookies with independent guards between requests. It covers API and
web session revocation, remember-only cookies (including historical
credentials), preservation of the enrolling session, idempotent retries,
ordinary remember login, explicit signed recovery, audit rollback, and
interleaved enrollment during password validation.

`e2e/recoveryEnrollment.spec.ts` checks the browser flow through deliberate
enrollment, retired-password rejection, fresh-device recovery and store
onboarding using synthetic accounts and the BTCPay stub.
