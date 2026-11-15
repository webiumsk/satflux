---
title: Slovak e-invoicing - ePostman connection
category: invoicing
order: 6
meta_description: Connect your company's ePostman account to receive e-invoices and import their originals into Satflux expenses.
updated: 2026-11-15
---

# Slovak e-invoicing - ePostman connection

The connection supports **receiving and manual sending**. Receiving and importing an invoice and a credit note have been verified in the Peppol Test Network. Manual sending of both document types through ePostman to Recommand has also been confirmed in that test network. Production activation, statutory reporting and the provider's archive arrangements require separate confirmation. Satflux does not present this as a complete solution for mandatory Slovak e-invoicing.

On the company profile, setup guidance appears as compact cards. Open a card to see its checklist and the option to hide it or remind you later. The unverified eFaktúra status remains visible when the card is closed.

## Connect your company

Set up each company's connection separately. Enabling a Slovak company's receiving does not enable it for a foreign LLC.

1. Open the correct company's **e-invoicing** settings and enable e-invoicing. Choose **ePostman** and the appropriate environment. Production and test accounts use separate access credentials; changing the environment requires that environment's credentials. Satflux fills in the server address for ePostman.
2. Complete your company's activation with ePostman. For production, follow the provider's [activation guide](https://postman.slovakodata.com/help/zaciname/onboarding/) and confirm receiving registration. Choosing a provider in Satflux does not register your company with the provider or the Financial Administration. For tests, use the test portal and test credentials only.
3. In the ePostman portal, open **Settings > SAPI clients** and create a dedicated client for Satflux. Copy its **Client ID** and **Client Secret** into Satflux. These are SAPI client credentials, not a REST API key. The provider displays the secret only when it is created; keep it private. See the provider's [settings guide](https://postman.slovakodata.com/help/portal/nastavenia/).
4. Check the company identity shown in Satflux. The receiving identifier is derived from the company's details. If the guide reports a missing DIČ, complete the correct Slovak tax identifier in the company details before continuing. Use credentials belonging to that company.
5. Select **Test connection**. This checks access credentials only. It does not confirm receiving registration, delivery of a document or legal compliance. Correct any error before continuing.
6. Keep receiving enabled and **save** the settings. A successful test alone does not save the complete receiving configuration. Resolve any save or synchronization error. After successful saving, Satflux can retrieve incoming documents in the background, including while your browser is closed. If the saved form asks you to test again after changing the connection, repeat the test to verify the saved credentials.

If a secret is already saved for the same connection, leave its field empty to keep it. When switching provider or environment, enter the new connection's credentials and test again.

## Separate receiving and sending connections

A receiving-only company sees one **Receiving provider** setup with its existing connection. Select the provider, enter or retain its credentials, test the connection and save. There is no sending-connection choice in this mode. Opening the settings does not move credentials or switch the provider.

Companies that send and receive have separate **Sending provider** and **Receiving provider** sections. By default, **Use the sending connection for receiving** is selected: receiving uses the provider and credentials entered in the sending section. Turn it off to configure a supported receiving provider with its own credentials. Both sets of details remain available when you change this choice; apply the change with Save. An incomplete separate receiver does not fall back to the sender.

If a receiving-only company has an empty separate setup and a previous saved connection is available, **Use previous connection** selects it in the form. Save to apply the selection. Existing separate details and entered drafts are not automatically replaced.

Mailbox checks use the receiving connection; manual sends and the Enterprise API key use the sending connection. Changing receiving details does not rewrite an outgoing attempt or its history. Reconcile uncertain sends with their original provider; Satflux does not automatically send them through another provider.

Choosing a provider here does not register or transfer your receiving service. A company can have one receiving provider per DIČ; arrange any transfer separately. Presets do not establish tested compatibility with every provider. Separate connections require a compatible Satflux server.

## Find and import received documents

Open **Expenses > Received e-invoices** for the same company. Review the document or download its original XML, then import it as an expense. Credit notes are imported as corrections with the appropriate signed amount. A successful empty retrieval means no new documents were fetched; it does not establish that a test invoice has been delivered.

Use **Check mailbox** directly in **Expenses > Received e-invoices** to fetch incoming documents from the provider and then reload the inbox and receiving status. **Refresh** only reloads documents and status already stored in Satflux. **Fetch incoming now** in the company's e-invoicing settings remains available. Already imported documents stay in Expenses; the inbox lists only documents still waiting for local import. An empty inbox is a normal state after import.

After a successful local connection test, the form shows that the verification still needs saving. Choose **Save my details** to retain it. Saving the same connection and credentials preserves the last mailbox check; changing or clearing credentials invalidates that connection evidence. If no mailbox check is recorded or the status is old, use **Check mailbox**. A successful empty check does not establish delivery or production readiness.

Import stores the original in your encrypted local data. If import fails or the original is too large for a local attachment, the inbox item remains available for recovery and original download. An existing expense alone does not complete the import.

If the original UBL contains embedded PDF attachments, the document detail offers **View** and **Download** for each PDF. The same actions are available when you select the original XML attachment in an already imported expense. Viewing opens a page preview; downloading preserves the embedded PDF bytes. The original XML is unchanged. Satflux does not fetch external attachment links. Up to ten PDFs are supported, each up to 1 MiB, within an XML document up to 2 MiB; local import retains its existing attachment size limit. Unsupported, damaged or oversized PDFs show a warning. If the preview fails, you can still download a successfully extracted PDF.

## Original custody and provider costs

Until verified local import completes, the original is encrypted on the server with server-held keys. Provider credentials and the parsed document summary are also processed there. Pending originals are not automatically deleted by age. After verified local handoff, temporary server content is removed. This is not a long-term Satflux server archive. Confirm archive access, retention and export with your provider, including after cancellation.

A provider account may have separate API charges or usage conditions. A free web inbox does not necessarily include access from Satflux. Confirm the applicable plan, SAPI access and archive terms with ePostman before relying on the production connection.

## Manual sending and legal obligations

For this domestic sending flow, both parties must be in Slovakia and use `0245:<DIČ>` with their own ten-digit DIČ.

This first sending version rejects documents with line or invoice discounts, prices that lose precision in the export, or amounts that differ from the issued document. Correcting export support is required before those documents can be sent. Partial payments are included in the XML balance.

For an eligible Slovak full VAT payer, open an issued domestic B2B invoice or credit note and choose **Send e-invoice**. Save the correct company connection first. The issuer and credentials must belong to the same company. Automatic sending through ePostman remains disabled.

A successful submission means **accepted for processing**. Delivery and tax reporting are shown separately when an Enterprise API key is configured. The linked ePostman portal remains available in the matching production/test environment.

If the result is uncertain, retry only when Satflux offers it. The retry uses the original request and identifier within the provider's retry window. After that window, or after a connection/document change, reconcile the result with the provider before sending again. Do not create a new invoice merely to retry an uncertain submission.

## Automatic delivery status

In the same company's e-invoicing settings, enter an **ePostman API key** from **Settings > API Keys** in the matching production or test portal, then save. This is separate from the SAPI Client ID and Client Secret. Satflux stores this key encrypted on its server and does not include it in shared company data. Keep it private. Removing the API key disables automatic status checks; SAPI receiving and sending use their existing credentials.

Satflux periodically checks sent documents, including while the browser is closed. The document panel shows delivery, the recipient's **C3 MLS** confirmation, and the provider-reported **C5** technical status separately. Use **Refresh status** for an eligible document to request a check, subject to a short cooldown. An open visible panel also reloads cached results.

**UNCONFIRMED / TIMEOUT** means the receiving access point took the message but the final MLS confirmation did not arrive in time. It does not by itself mean sending failed. A positive C5 value is a technical status reported by the provider. It does not confirm tax approval or the recipient's business approval. On a failed check, the last valid observation remains visible with the check error. Replace an expired or revoked API key in the company settings.

Automatic checks may be unavailable for older submissions. Use the provider portal for those documents. Do not resend an existing invoice just to obtain a status.

Confirm your company's specific obligations and deadlines with the Financial Administration or your accountant. A working connection alone does not establish that all sending and reporting obligations are met.
