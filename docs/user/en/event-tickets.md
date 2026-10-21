---
title: Event tickets and display order
category: payments
order: 20
updated: 2026-10-22
---

# Event tickets and display order

Create and edit events in your existing Tickets app. The title, rich description, physical or virtual type, location, dates, image, currency and capacity settings define the public offer.

## Order your ticket types

Expand an event and open **Ticket types**. Choose **Edit order**, drag a type or use the up/down buttons, then **Save order**. **Cancel** discards the draft. Include inactive types in your ordering; the public offer skips them while retaining the relative order. New types are appended. Prices and issued tickets are unchanged.

Ordering requires the compatible **Satflux Tickets 2.1.0** plugin. If another administrator changed the event while you edited, reload the latest order before saving again.

## Public sales

Share the existing public event link on your BTCPay server. Visitors choose ticket types and enter attendee details on one page, then complete payment in BTCPay. With Satflux Tickets 2.1.1, the page uses your browser language and offers a language selector (EN/SK/ES/DE/CS). With Satflux Tickets 2.2.0, physical events show the venue time zone and its UTC offset, wherever the visitor is. Virtual events use the visitor’s browser time zone with a clearly labelled UTC fallback. Tickets for one email address are delivered together; separate recipients receive their own tickets. Preserve your configured virtual-event access instructions in the event and email settings.

From Satflux Tickets 2.1.2, select a language in the page header to update the page immediately. Selected tickets and incomplete recipient details stay in place.

## Event time zone

For a physical event, choose the venue’s IANA time zone in the existing event form, for example Europe/Bratislava. Enter the time at the venue. Existing physical events receive Europe/Bratislava under the approved historical migration; stored UTC instants stay unchanged. Changing only the zone changes presentation, not the scheduled instant. Editing a date explicitly changes the schedule; an unavailable or ambiguous hour during a clock change must be corrected before saving. An older plugin must be upgraded before using the new time fields. Your draft remains available after an error.

For a new physical event, Satflux preselects the store’s saved zone when supported. You can choose a different venue zone in the same form. This choice is saved on the event; changing the store zone later does not change existing events. In native BTCPay, select the venue zone explicitly.
