---
title: Slovak e-invoicing - ePostman connection
category: invoicing
order: 6
meta_description: Connect your company's ePostman account to receive e-invoices and import their originals into Satflux expenses.
updated: 2026-11-12
---

# Slovak e-invoicing - ePostman connection

The connection supports **receiving and manual sending**. Receiving and importing an invoice and a credit note have been verified in the Peppol Test Network. Manual sending of both document types through ePostman to Recommand has also been confirmed in that test network. Production activation, statutory reporting and the provider's archive arrangements require separate confirmation. Satflux does not present this as a complete solution for mandatory Slovak e-invoicing.

## Connect your company

Set up each company's connection separately. Enabling a Slovak company's receiving does not enable it for a foreign LLC.

1. Open the correct company's **e-invoicing** settings and enable e-invoicing. Choose **ePostman** and the appropriate environment. Production and test accounts use separate access credentials; changing the environment requires that environment's credentials. Satflux fills in the server address for ePostman.
2. Complete your company's activation with ePostman. For production, follow the provider's [activation guide](https://postman.slovakodata.com/help/zaciname/onboarding/) and confirm receiving registration. Choosing a provider in Satflux does not register your company with the provider or the Financial Administration. For tests, use the test portal and test credentials only.
3. In the ePostman portal, open **Settings > SAPI clients** and create a dedicated client for Satflux. Copy its **Client ID** and **Client Secret** into Satflux. These are SAPI client credentials, not a REST API key. The provider displays the secret only when it is created; keep it private. See the provider's [settings guide](https://postman.slovakodata.com/help/portal/nastavenia/).
4. Check the company identity shown in Satflux. The receiving identifier is derived from the company's details. If the guide reports a missing DIČ, complete the correct Slovak tax identifier in the company details before continuing. Use credentials belonging to that company.
5. Select **Test connection**. This checks access credentials only. It does not confirm receiving registration, delivery of a document or legal compliance. Correct any error before continuing.
6. Keep receiving enabled and **save** the settings. A successful test alone does not save the complete receiving configuration. Resolve any save or synchronization error. After successful saving, Satflux can retrieve incoming documents in the background, including while your browser is closed. If the saved form asks you to test again after changing the connection, repeat the test to verify the saved credentials.

If a secret is already saved for the same connection, leave its field empty to keep it. When switching provider or environment, enter the new connection's credentials and test again.

## Find and import received documents

Open **Expenses > Received e-invoices** for the same company. Review the document or download its original XML, then import it as an expense. Credit notes are imported as corrections with the appropriate signed amount. A successful empty retrieval means no new documents were fetched; it does not establish that a test invoice has been delivered.

Use **Fetch incoming now** in the company's e-invoicing settings to request a retrieval. Then refresh the incoming inbox on Expenses. Refreshing the inbox shows documents already retrieved by Satflux.

Import stores the original in your encrypted local data. If import fails or the original is too large for a local attachment, the inbox item remains available for recovery and original download. An existing expense alone does not complete the import.

## Original custody and provider costs

Until verified local import completes, the original is encrypted on the server with server-held keys. Provider credentials and the parsed document summary are also processed there. Pending originals are not automatically deleted by age. After verified local handoff, temporary server content is removed. This is not a long-term Satflux server archive. Confirm archive access, retention and export with your provider, including after cancellation.

A provider account may have separate API charges or usage conditions. A free web inbox does not necessarily include access from Satflux. Confirm the applicable plan, SAPI access and archive terms with ePostman before relying on the production connection.

## Manual sending and legal obligations

For this domestic sending flow, both parties must be in Slovakia and use `0245:<DIČ>` with their own ten-digit DIČ.

This first sending version rejects documents with line or invoice discounts, prices that lose precision in the export, or amounts that differ from the issued document. Correcting export support is required before those documents can be sent. Partial payments are included in the XML balance.

For an eligible Slovak full VAT payer, open an issued domestic B2B invoice or credit note and choose **Send e-invoice**. Save the correct company connection first. The issuer and credentials must belong to the same company. Automatic sending through ePostman remains disabled.

A successful submission means **accepted for processing**, not delivered or accepted by the tax authority. Open the linked ePostman portal in the same production/test environment to check delivery and reporting. SAPI credentials cannot read those states; automatic status tracking requires a separate integration.

If the result is uncertain, retry only when Satflux offers it. The retry uses the original request and identifier within the provider's retry window. After that window, or after a connection/document change, reconcile the result with the provider before sending again. Do not create a new invoice merely to retry an uncertain submission.

Confirm your company's specific obligations and deadlines with the Financial Administration or your accountant. A working connection alone does not establish that all sending and reporting obligations are met.
