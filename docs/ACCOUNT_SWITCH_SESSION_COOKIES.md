# Account switching and remember cookies

A browser that remembered password account A could previously sign in to
account B without remembering B, while retaining A's persistent cookie.
When B's session expired, Laravel could silently restore A. This affected
password login and signed recovery, including passkey recovery after the
browser decrypts its recovery envelope. Tenant ownership checks did not
prevent the browser's authenticated identity from changing back to A.

The application now handles Laravel's `Login` event for session guards.
Whenever login establishes a session without remember-me, it queues deletion
of that guard's persistent cookie. The cookie uses the configured domain and
path, matching the cookie that Laravel issues. This covers password login,
signed recovery and new guest login without duplicating controller logic.

An explicit remembered password login still replaces the cookie with the
new account's cookie. Automatic recall is also a remembered login and is
preserved for accounts that allow it. Repeating login to the same account
without remember-me removes the previous remember choice from that browser.

Invalid passwords and rejected recovery proofs do not fire a successful
login event and retain the previous account's cookie. Email verification
does not establish a login and does not change the current browser account.
Existing logout, session regeneration, CSRF handling, and recovery enrollment
credential retirement remain unchanged.

The change deletes the browser cookie; it does not globally rotate the
previous account's token or sign out other devices. A captured copy remains
a credential until existing logout or credential-revocation behavior retires
it. Browsers that already switched accounts before deployment receive the
cleanup on their next successful session-only login. Cross-tab automatic
recovery from a locally retained phrase is a separate flow.

## Regression coverage

`tests/Feature/AccountSwitchRememberCookieTest.php` carries actual encrypted
cookies between requests, creates a new guard for each request, and simulates
session expiry by discarding the session cookie while retaining persistent
cookies. Tests cover password and signed-recovery switches on both API and
web routes, same-account remember opt-out, explicit remembered switching,
other-device preservation, failed authentication, new guest login with an
orphaned cookie, and configured cookie domain/path matching. Fixtures and
signing keys are synthetic; no production credentials are used.
