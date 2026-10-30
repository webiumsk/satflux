---
title: Pay Button
category: payments
order: 2
meta_description: Add a Bitcoin "pay" button to your website with a snippet of HTML.
updated: 2026-10-30
---

# Pay Button

A **Pay Button** is a small snippet you paste into your website so visitors can pay you in Bitcoin or over Lightning - handy for donations, a fixed-price product, or a "buy me a coffee" button.

## Create a Pay Button

1. In your store, open the **Pay Button** app.
2. Set the amount (fixed or customer-chosen) and currency.
3. Copy the generated HTML snippet.

## Add it to your site

Paste the snippet into your page's HTML where you want the button to appear. When a visitor clicks it, they get a payment screen and can pay on-chain or with Lightning.

## Notes

- The snippet is self-contained - no account or plugin required on your site.
- For a full shop, a WooCommerce integration is a better fit than individual buttons.

## Buyer email and return address

The optional **Buyer email** identifies the customer on the invoice. It does not enable merchant email notifications. Configure those through the store's email rules.

The browser redirect must be a complete HTTP or HTTPS address, such as `https://example.com/thanks?order=123`. Relative paths and other URL schemes are rejected. Correct an invalid address before copying the generated code.
