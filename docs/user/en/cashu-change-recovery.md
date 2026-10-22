---
title: Recovering Cashu change
category: payments
order: 4
meta_description: View Cashu fee-reserve change and securely recover it into your own wallet.
---

# Recovering Cashu change

CashuMelt can retain unused payment fee reserves as Cashu change. Completed payments
can therefore coexist with a remaining balance. Automatic Lightning sweeping starts
at 100 sat for eligible change from the same mint; a smaller balance can still block
a wallet change.

The Cashu connection settings show the latest wallet-change safety check before
you start editing. If changes are blocked, follow the link to settlements and
recovery. Refreshing this status preserves any settings you have already edited;
an unavailable check does not mean the wallet is safe to change.

With CashuMelt **1.4.0.5 or later**, the safety summary separates failed payments,
other unfinished payments and completed records retaining recovery material. The
total can exceed the number of failed rows. Affected rows show why they block a
wallet change and which recovery stage needs attention. Older plugins show this
breakdown as unknown.

A funding-status error such as HTTP 400 does not establish whether a payment was
paid. Retry can recover supported stored evidence, but cannot certify an unpaid
payment from an error. If the original mint no longer provides the payment status
and no usable recovery evidence exists, investigation with that mint is required.
Change-token recovery below does not resolve these separate payment obligations.

With CashuMelt **1.4.0.4 or later**, open the store's **Cashu settlements** and its
change recovery panel. Amounts are in satoshi; record counts are separate. Only the
verified, registered store owner can download tokens or verify recovery.

1. Choose the token download and confirm the notice. Enter your password if asked,
   then the six-digit code sent to your email.
2. Keep the downloaded `cashumelt-change.json` file private. It contains spendable
   tokens. Import the tokens from its JSON array into your own compatible Cashu wallet.
   Never send the file to support or another person.
3. After importing, choose recovery verification and confirm with a new email code.
   The mint must confirm the exported tokens were consumed before recovery completes.

Downloading alone does not release the wallet-change hold. Satflux refreshes the
remaining balance and safety check after each operation; other pending records can
still block a change. If a download is lost or the outcome is unknown, refresh and
start a new authorized attempt. Uncertain sweeps and unsupported units may need
support; their records are preserved.

Older plugins show that this capability is unsupported. An unavailable check does
not mean a zero balance. The existing **Cashu recovery** section in BTCPay remains
available for supported native recovery operations.

## Lightning checkout and Cashu availability

With the updated plugin, CashuMelt is hidden from new checkout whenever any Lightning wallet is configured, including Blink API, LN addresses and Boltz. A Lightning outage does not automatically offer Cashu. Existing Cashu quotes remain available for recovery under their original settings.

Re-enabling an existing Cashu configuration with the same mint, unit, payout address, trust list and fee caps does not change its payment destination. Changing the payment destination remains blocked while funds are unresolved. Combining re-enable with changes to any of those values also retains the fund-safety checks. Enabling Cashu does not make it appear alongside a configured Lightning wallet.

A BTCPay invoice paid directly through Lightning can be settled while a separate Cashu quote remains pending. HTTP 400 from the mint does not determine whether that quote was funded. The invoice status alone cannot clear its recovery hold. When changing wallet credentials, detection occurs after leaving the input or using Save/Test; typing alone does not select another wallet.
