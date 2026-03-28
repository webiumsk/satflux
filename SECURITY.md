# Security policy

## Supported versions

We aim to patch security issues in the **default branch** (`master` / `main`) used for production deployments.

| Version / branch | Supported |
|------------------|-----------|
| Latest code on the default branch | Yes |
| Older tags or forks | Best effort only |

Runtime expectations from `composer.json`: **PHP ^8.3**, **Laravel ^12**. Deployments should use supported PHP releases from [php.net/supported-versions](https://www.php.net/supported-versions).

## Reporting a vulnerability

**Please do not open a public GitHub issue** for undisclosed security problems.

1. **Preferred:** Use [GitHub private vulnerability reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability) for this repository (if enabled in repository settings).
2. **Alternatively:** Email **hello@satflux.io** with a clear subject (e.g. `Security: short summary`). Include steps to reproduce, impact, and affected component if known.

We will try to acknowledge receipt within a few business days and coordinate a fix and release timeline. We ask that you allow a reasonable disclosure window before any public details.

## Scope (in scope)

- This application (Laravel API, Vue SPA, auth, multi-tenant store isolation).
- Integration assumptions documented in the repository (e.g. BTCPay Server, webhooks) when the issue is in **our** code or configuration we ship.

## Out of scope

- BTCPay Server, third-party wallets, or hosting misconfiguration (e.g. leaked `.env`, weak server SSH) unless our documentation clearly steers users into an unsafe default.
- Denial-of-service requiring extreme resources without a clear defect in our code.
- Reports from automated scanners without a concrete exploit path.

## Safe harbor

We appreciate responsible disclosure and will not pursue legal action against researchers who follow this policy in good faith.
