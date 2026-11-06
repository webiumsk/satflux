# Showcase image provenance

These four 2160 x 1500 PNGs are unchanged owner-approved V6 captures made on
2026-11-05 from Satflux application revision `885168f2` using actual Vue
components and synthetic fixtures. They contain no real merchant records,
credentials, IBANs, payment QR codes, recovery phrases or live tickets.

| Asset | Actual component and illustrated state | SHA-256 |
| --- | --- | --- |
| `satflux-overview-v6.png` | `Dashboard.vue`, `DashboardStoresTab.vue`, synthetic sales analytics | `3397faadd6dd2194267b6e3db321202b15162745a7a15327bbe1142de424b79b` |
| `satflux-payments-v6.png` | `StoreInvoices.vue`, eight synthetic payment rows | `c6ae45b0847e69bd260da1304bffe18fc1a322189ef39eadc0e672b9945e964d` |
| `satflux-invoicing-hover-v6.png` | `Invoices.vue`, seven synthetic documents, first row genuinely hovered with actual action icons visible | `4a15602a14db9c5b87a2399d0fb8aa8ceed2a8859d58e5dc365579b08c3771a8` |
| `satflux-events-v6.png` | `TicketsShow.vue`, `AppShowLayout.vue`, one expanded event and two synthetic ticket types | `1903c3257a45f061650ce5f83b0a8fc8e0d03edb3451e4cded962bee3451ecb5` |

The capture used a 1440 x 1000 CSS-pixel viewport, scale 1.5, Slovak locale,
reduced motion and the real application header/footer/fonts. The event example
has standard (15 EUR, 56/90 sold) and supporter (25 EUR, 16/30 sold) tickets.
All visible names, dates, amounts and company/store labels are synthetic.

The original V6 capture harness repositioned the real footer after content to
illustrate the proposed desktop layout. It did not redraw interface pixels or
change the production header. Invoicing used synthetic server-mode UI fixtures;
these images do not demonstrate Evolu persistence, backend integration or a live
production release. The separate implementation now adds the approved footer
behavior to application source.

The disposable frontend harness used actual Vue/Pinia/router/i18n/CSS with an
in-memory Axios adapter. No backend, database, relay, application environment,
production session or cached Laravel configuration was used. The browser blocked
external HTTP, API requests and all WebSockets. No payment, export, check-in,
email or document mutation was performed. The invoice hover triggered only the
real first row's hover state.

Public captions explicitly identify sample data. The same Slovak screenshots
are shown across interface locales as illustrative application captures.
