# Legacy email verification

A legacy registration email opens `/auth/verify-email/{id}/{hash}`. The page calls the signed API URL to verify the address. The API checks the signature, expiry, email hash, and registration fingerprint; the fingerprint prevents an older link from activating replacement registration credentials.

Verification does not sign in, sign out, regenerate the session, or update `last_login_at`. An anonymous browser remains anonymous. A browser signed into account A stays signed into A even when it verifies account B. Responses contain verification status, not a user to adopt as the session identity. Already-verified links are idempotent after signature and expiry validation.

After success, the page redirects to the existing sign-in route with the email-verified notice. An existing authenticated session is handled by the normal router guard. Otherwise, the user explicitly signs in with the password or recovery method applicable to the account.

Regression coverage: `EmailVerificationSessionIsolationTest`, `RegistrationVerificationTest`, and `verifyEmailSessionIsolation.test.ts`.
