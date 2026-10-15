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
