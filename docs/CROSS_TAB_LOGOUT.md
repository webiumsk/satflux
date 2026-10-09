# Cross-tab logout and explicit recovery

Previously, a tab holding a session-only recovery phrase would handle a 401
from `/api/user` by signing a new recovery challenge automatically. Logout in
another tab invalidated the shared session but only cleared that tab's phrase,
so a reload in the other tab could silently create another authenticated session.

Unauthorized profile reads now clear local authentication and tenant selection
without sending recovery authentication requests. Recovery login requires an
explicit phrase or passkey action. Valid server sessions still survive reload;
natural session expiry now also requires explicit sign-in.

Logout publishes a random marker in localStorage before its HTTP request and
again when that request completes. It contains no account identifier, phrase,
public key, or signature. Each tab records the last consumed marker in
sessionStorage and clears authentication, tenant selection and phrase storage
when it observes a different marker. Storage events, focus, visibility, startup,
and profile-response checks cover active, background, and reloaded tabs.
Remote logout also navigates an active tab to login. Store disposal removes
all event listeners.

An in-memory logout generation rejects late profile responses and cancels
pending authentication continuations before they can restore local user state
or save a phrase again. Explicit recovery begun after the logout operation finishes remains available.
Authentication that overlaps a pending logout is cancelled conservatively; the
user can retry once logout has completed.
The backend still performs the existing session invalidation and CSRF-token
regeneration. Encrypted local invoicing data is not deleted by this change.

## Coverage

`authCrossTabLogout.test.ts` covers unauthorized reads, remote and missed events,
reload and focus, late profile and CSRF responses, logout intent before HTTP
completion, failed requests, explicit recovery after logout, recovery races,
listener disposal and storage failure. Only public synthetic BIP39 vectors are
used in tests.

`e2e/recoveryEnrollment.spec.ts` opens a real same-origin second tab after
recovery, verifies logout in the first tab redirects both to login, reloads the
second tab without recovery requests, then explicitly restores and reloads.
The existing enrollment, CSRF, account-switching and onboarding steps remain.

## Limits

Cross-tab notifications require same-origin browser storage. If storage is
blocked, local cleanup and explicit-only recovery still apply, while another
tab learns the server session ended on its next profile read. Tabs running an
older frontend build do not have this protection until reloaded.

A failed server logout cannot invalidate the server session; its error remains
visible to the caller. Authentication requests already dispatched before a
concurrent logout may have server effects even though their frontend
continuations are rejected. This change does not add server serialization of
concurrent explicit login/logout requests or erase encrypted local data.
