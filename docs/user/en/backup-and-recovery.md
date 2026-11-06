---
title: Backup & recovery (24 words)
category: security
order: 4
meta_description: Protect your recovery phrase and export invoicing backups; learn how relay recovery and file restoration differ.
updated: 2026-11-07
---

# Backup & recovery (24 words)

Your **24-word recovery phrase** restores access to your Satflux identity and derives the keys for your encrypted invoicing data. It contains no invoices and does not replace a data backup.

> **Satflux never stores your recovery phrase and cannot reset it.** Save it somewhere safe before signing out or closing your tabs.

## Back it up properly

- Write the 24 words **in order** on paper (or steel) and store them somewhere safe and offline.
- Do not paste them into notes apps, chats, or screenshots.
- Consider a second copy in a separate location.

Satflux shows a **backup reminder** until you confirm you have saved the phrase - do not dismiss it until you actually have.

## Restore on another device

On a new device, choose **Restore with recovery phrase** and type your 24 words to recover account access. **Recovering invoicing data through a relay requires both the correct recovery phrase and an available copy of the data.** The phrase alone cannot reconstruct data deleted from every device and relay. Give synchronization time to finish before creating companies to avoid duplicates. Add a [passkey](/documentation/passkeys) on the new device for convenience.

Signing out clears the recovery phrase from your open Satflux tabs in that browser. Keep your backup before signing out. If your session expires or you sign out, choose an explicit sign-in action or **Restore with recovery phrase** to return; a retained phrase no longer signs you back in automatically. Reloading a tab with a valid session continues to work normally.

## Export an invoicing backup

1. On a device that still has your invoicing data, open **Account settings > Evolu sync > Data backup**.
2. To encrypt the file, enable **Encrypt the backup with a passphrase** and choose or generate a backup passphrase. Save it separately from the file; this is the passphrase for the backup, not a requirement to reuse your 24-word recovery phrase.
3. Choose **Export data** and save the downloaded JSON file outside the browser. Without the encryption option, the file contains readable invoices; protect it accordingly.

## Restore invoicing data from a file

An exported invoicing backup can be imported into another account **without the original account's recovery phrase**. It restores invoicing data, not the original account's sign-in or BTCPay credentials.

1. Sign in to and unlock the destination account's local invoicing data, then open **Account settings > Evolu sync > Data backup**. Export its existing data first if you want to preserve a separate copy.
2. Choose **Restore from file** and select your backup JSON file.
3. If the file is encrypted, enter its **backup passphrase** and choose **Unlock backup**. The original account's recovery phrase is not a substitute for that passphrase.
4. Review the preview and any different-account warning, choose **Restore backup**, and confirm. New rows are added and rows with matching IDs are overwritten by the backup; other local rows are not deleted. Check the result for any failures.

## Local-first data

Clearing browser data removes the local invoicing copy on that device. Recovery then needs either the correct recovery phrase with an available relay copy, or an exported backup file (and its backup passphrase if encrypted). If no data copy or export remains, the recovery phrase alone cannot recover the invoices.
