---
title: Slovak e-invoicing - current limitations
category: invoicing
order: 6
meta_description: Current status and limitations of the Slovak eFaktura connection in Satflux.
updated: 2026-11-10
---

# Slovak e-invoicing - current limitations

**A complete Slovak eFaktura provider integration has not been verified in Satflux. We currently do not present Satflux as a ready solution for mandatory Slovak e-invoicing.** A receiving integration is being developed and still requires provider verification. No completion date is promised.

## Export and delivery

Satflux can export invoices as **UBL/XML**. Downloading this file does not deliver the invoice to its recipient or confirm that legal obligations have been met. A provider must accept the document and perform the required delivery. Manual upload is an option only if the selected provider supports it; that complete workflow has not been verified in Satflux.

## Provider account and costs

A provider may require its own account and separately paid API access. A free web inbox does not necessarily include a connection from Satflux. Before paying for or relying on a provider, confirm its price, permitted use and compatibility with Satflux. Choosing the provider on the Financial Administration portal does not automatically create working access credentials in Satflux.

## Existing settings and documents

Existing settings, document history and integration controls are retained. If you already use the connection, open your company's **e-invoicing** settings to review the saved provider and sending or receiving options. Keep access credentials private.

**Test the connection** checks authentication. A successful result or a completed checklist does not prove that the provider accepted or delivered an invoice, that receiving works, or that required reporting was completed. Check the status and evidence with your provider.

Where available, the document panel shows submission status and the provider's document reference. The incoming invoice inbox lets you review received documents and import them as expenses. These controls describe existing functionality; their presence does not confirm a verified complete provider integration.

## Planning your invoicing

Until you have a verified arrangement, use a service that can demonstrate the sending and receiving you need. Confirm your specific obligations and deadlines with the Financial Administration or your accountant.

## Receiving with ePostman

When the receiving pilot is available, select ePostman for the correct Slovak company in its e-invoicing settings. Production and test accounts are separate. Complete provider activation and check the receiving registration; a successful connection test checks credentials only. This connector does not enable sending through ePostman.

Received documents appear in the incoming inbox on **Expenses**. Review the document or download its original XML. Import creates an expense and stores the original in your encrypted local data. A failed or oversized attachment leaves the inbox item available for recovery; an existing expense alone does not complete the import. Credit notes must be identified and handled as credits, not silently recorded as ordinary positive expenses.

While waiting for successful local import, the original is encrypted on the server with server-held keys; the provider credentials and parsed summary are also processed there. Pending originals are not automatically deleted by age. After verified local handoff, temporary server content is removed. This is not a longterm Satflux server archive. Confirm archive access, retention and export with your provider, including after cancellation.

Keep each company's connection separate. Activating receiving for a Slovak company does not activate it for a foreign LLC. No real-network receiving or reporting readiness is established by this guide.
