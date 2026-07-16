/**
 * Minimal BTCPay Greenfield stub for the Playwright e2e suite
 * (docs/BTCPAY_E2E_SCENARIOS.md). Plain node:http, zero dependencies,
 * in-memory state - NOT a BTCPay emulator, it implements exactly the
 * endpoint subset BtcPayClient calls plus a control API for tests:
 *
 *   POST /_stub/stores/{storeId}/invoices   create an invoice (test setup;
 *                                           fires InvoiceCreated, detached)
 *   POST /_stub/invoices/{id}/settle        mark settled + fire the SIGNED
 *                                           InvoiceSettled webhook
 *   POST /_stub/invoices/{id}/expire        mark expired + fire InvoiceExpired
 *   GET  /_stub/state                       full state dump for asserts
 *
 * Wiring is config-only: point BTCPAY_BASE_URL at this server. Webhook
 * secrets are minted per store webhook (like real BTCPay) and used to sign
 * deliveries with BTCPay-Sig, so the panel's signature verification runs
 * for real end-to-end.
 */

import { createHmac, randomUUID } from "node:crypto";
import http from "node:http";

const PORT = Number(process.env.BTCPAY_STUB_PORT ?? 14142);
const APP_URL = (process.env.APP_URL ?? "http://localhost:8000").replace(/\/$/, "");

/** @type {Map<string, {id: string, name: string, defaultCurrency: string, webhooks: Array<{id: string, url: string, secret: string}>}>} */
const stores = new Map();
/** @type {Map<string, {id: string, storeId: string, amount: string, currency: string, status: string, checkoutLink: string, createdTime: number, paidTime: number|null, metadata: Record<string, unknown>}>} */
const invoices = new Map();
/** @type {Array<{invoiceId: string, status: number|string, body: string}>} */
const webhookDeliveries = [];

function json(res, status, body) {
    const payload = JSON.stringify(body);
    res.writeHead(status, { "Content-Type": "application/json" });
    res.end(payload);
}

function readBody(req) {
    return new Promise((resolve) => {
        let data = "";
        req.on("data", (chunk) => (data += chunk));
        req.on("end", () => {
            try {
                resolve(data ? JSON.parse(data) : {});
            } catch {
                resolve({});
            }
        });
    });
}

function storePayload(store) {
    return {
        id: store.id,
        storeId: store.id,
        name: store.name,
        defaultCurrency: store.defaultCurrency,
        timeZone: "Europe/Vienna",
        preferredExchange: "kraken",
        logoUrl: null,
    };
}

function invoicePayload(invoice) {
    return {
        id: invoice.id,
        storeId: invoice.storeId,
        amount: invoice.amount,
        currency: invoice.currency,
        status: invoice.status,
        checkoutLink: invoice.checkoutLink,
        createdTime: invoice.createdTime,
        paidTime: invoice.paidTime,
        metadata: invoice.metadata,
    };
}

function createInvoice(storeId, body) {
    // No shared prefix: the panel UI truncates invoice ids to 8 chars, so the
    // id must be unique in its prefix (like real BTCPay invoice ids).
    const id = `inv${randomUUID().replace(/-/g, "").slice(0, 16)}`;
    const invoice = {
        id,
        storeId,
        amount: String(body.amount ?? "1.00"),
        currency: String(body.currency ?? "EUR"),
        status: "New",
        checkoutLink: `http://localhost:${PORT}/checkout/${id}`,
        createdTime: Math.floor(Date.now() / 1000),
        paidTime: null,
        metadata: typeof body.metadata === "object" && body.metadata !== null ? body.metadata : {},
    };
    invoices.set(id, invoice);
    return invoice;
}

/** Signed delivery exactly like BTCPay: BTCPay-Sig: sha256=<hmac(rawBody, secret)>. */
async function fireInvoiceWebhook(invoice, eventType) {
    const store = stores.get(invoice.storeId);
    const webhook = store?.webhooks[store.webhooks.length - 1];
    if (!webhook) {
        return { status: "no-webhook-registered" };
    }

    const rawBody = JSON.stringify({
        deliveryId: `stub-delivery-${randomUUID()}`,
        webhookId: webhook.id,
        type: eventType,
        eventType,
        storeId: invoice.storeId,
        invoiceId: invoice.id,
        timestamp: Math.floor(Date.now() / 1000),
        metadata: invoice.metadata,
    });
    const signature = "sha256=" + createHmac("sha256", webhook.secret).update(rawBody).digest("hex");

    // Deliver to the URL the panel registered for this webhook (falls back to
    // the default endpoint if none was recorded) - matches real BTCPay.
    const target = webhook.url || `${APP_URL}/api/webhooks/btcpay`;

    try {
        const response = await fetch(target, {
            method: "POST",
            headers: { "Content-Type": "application/json", "BTCPay-Sig": signature },
            body: rawBody,
        });
        const delivery = { invoiceId: invoice.id, status: response.status, body: await response.text() };
        webhookDeliveries.push(delivery);
        return delivery;
    } catch (error) {
        const delivery = { invoiceId: invoice.id, status: "unreachable", body: String(error) };
        webhookDeliveries.push(delivery);
        return delivery;
    }
}

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url ?? "/", `http://localhost:${PORT}`);
    const path = url.pathname;
    const method = req.method ?? "GET";
    let match;

    // ---- Control API (test-only, unauthenticated) ----
    if (method === "GET" && path === "/_stub/state") {
        return json(res, 200, {
            stores: [...stores.values()].map(storePayload),
            invoices: [...invoices.values()].map(invoicePayload),
            webhookDeliveries,
        });
    }
    if (method === "POST" && (match = path.match(/^\/_stub\/stores\/([^/]+)\/invoices$/))) {
        const store = stores.get(match[1]);
        if (!store) return json(res, 404, { message: "unknown store" });
        const invoice = createInvoice(match[1], await readBody(req));
        // Real BTCPay announces creation too. Fire-and-forget: awaiting a
        // callback into the (single-threaded artisan serve) panel while it
        // might itself be waiting on this response would deadlock.
        void fireInvoiceWebhook(invoice, "InvoiceCreated").catch(() => {});
        return json(res, 201, invoicePayload(invoice));
    }
    if (method === "POST" && (match = path.match(/^\/_stub\/invoices\/([^/]+)\/settle$/))) {
        const invoice = invoices.get(match[1]);
        if (!invoice) return json(res, 404, { message: "unknown invoice" });
        invoice.status = "Settled";
        invoice.paidTime = Math.floor(Date.now() / 1000);
        const delivery = await fireInvoiceWebhook(invoice, "InvoiceSettled");
        return json(res, 200, { invoice: invoicePayload(invoice), webhook: delivery });
    }
    if (method === "POST" && (match = path.match(/^\/_stub\/invoices\/([^/]+)\/expire$/))) {
        const invoice = invoices.get(match[1]);
        if (!invoice) return json(res, 404, { message: "unknown invoice" });
        invoice.status = "Expired";
        const delivery = await fireInvoiceWebhook(invoice, "InvoiceExpired");
        return json(res, 200, { invoice: invoicePayload(invoice), webhook: delivery });
    }

    // ---- Fake checkout page (checkoutLink target) ----
    if (method === "GET" && path.startsWith("/checkout/")) {
        res.writeHead(200, { "Content-Type": "text/html" });
        return res.end("<html><body><h1>BTCPay stub checkout</h1></body></html>");
    }

    // ---- Greenfield subset (requires any Authorization header) ----
    if (!req.headers.authorization) {
        return json(res, 401, { message: "missing authorization" });
    }

    if (method === "POST" && path === "/api/v1/stores") {
        const body = await readBody(req);
        const id = `stub-store-${randomUUID().slice(0, 8)}`;
        stores.set(id, {
            id,
            name: String(body.name ?? "Stub Store"),
            defaultCurrency: String(body.defaultCurrency ?? "EUR"),
            webhooks: [],
        });
        return json(res, 201, storePayload(stores.get(id)));
    }
    if (method === "GET" && path === "/api/v1/stores") {
        return json(res, 200, [...stores.values()].map(storePayload));
    }
    if ((match = path.match(/^\/api\/v1\/stores\/([^/]+)\/webhooks$/))) {
        const store = stores.get(match[1]);
        if (!store) return json(res, 404, { message: "unknown store" });
        if (method === "POST") {
            const body = await readBody(req);
            const webhook = { id: `stub-wh-${randomUUID().slice(0, 8)}`, url: String(body.url ?? ""), secret: randomUUID().replace(/-/g, "") };
            store.webhooks.push(webhook);
            return json(res, 200, { id: webhook.id, url: webhook.url, secret: webhook.secret, enabled: true });
        }
        return json(res, 200, store.webhooks.map((w) => ({ id: w.id, url: w.url, enabled: true })));
    }
    if (method === "DELETE" && (match = path.match(/^\/api\/v1\/stores\/([^/]+)\/webhooks\/([^/]+)$/))) {
        const store = stores.get(match[1]);
        if (store) store.webhooks = store.webhooks.filter((w) => w.id !== match[2]);
        return json(res, 200, {});
    }
    if ((match = path.match(/^\/api\/v1\/stores\/([^/]+)\/users(\/[^/]+)?$/))) {
        return json(res, 200, method === "GET" ? [] : { userId: "stub-user", role: "Owner" });
    }
    if ((match = path.match(/^\/api\/v1\/stores\/([^/]+)\/invoices$/))) {
        const store = stores.get(match[1]);
        if (!store) return json(res, 404, { message: "unknown store" });
        if (method === "POST") {
            const invoice = createInvoice(match[1], await readBody(req));
            // Detached like the control-API create: the caller here IS the
            // panel - awaiting a callback into it would deadlock artisan serve.
            void fireInvoiceWebhook(invoice, "InvoiceCreated").catch(() => {});
            return json(res, 200, invoicePayload(invoice));
        }
        const list = [...invoices.values()].filter((i) => i.storeId === match[1]).map(invoicePayload);
        return json(res, 200, list);
    }
    if (method === "GET" && (match = path.match(/^\/api\/v1\/stores\/([^/]+)\/invoices\/([^/]+)$/))) {
        const invoice = invoices.get(match[2]);
        return invoice ? json(res, 200, invoicePayload(invoice)) : json(res, 404, { message: "unknown invoice" });
    }
    if (method === "GET" && (match = path.match(/^\/api\/v1\/stores\/([^/]+)\/invoices\/([^/]+)\/payment-methods$/))) {
        // Settlement sync (webhook handler) walks the payments of every
        // method; an empty list is a valid "no payment data" answer.
        return invoices.has(match[2]) ? json(res, 200, []) : json(res, 404, { message: "unknown invoice" });
    }
    if (method === "GET" && (match = path.match(/^\/api\/v1\/stores\/([^/]+)\/payment-methods/))) {
        return json(res, 200, []);
    }
    if ((match = path.match(/^\/api\/v1\/stores\/([^/]+)\/(lightning-addresses|apps)/))) {
        return json(res, 200, []);
    }
    if ((match = path.match(/^\/api\/v1\/stores\/([^/]+)$/))) {
        const store = stores.get(match[1]);
        if (!store) return json(res, 404, { message: "unknown store" });
        if (method === "DELETE") {
            stores.delete(match[1]);
            return json(res, 200, {});
        }
        return json(res, 200, storePayload(store));
    }
    if (method === "GET" && (path === "/api/v1/users/me" || path === "/api/v1/api-keys/current")) {
        return json(res, 200, { id: "stub-admin-user", email: "admin@stub.test", isAdministrator: true });
    }
    if (path === "/api/v1/users" || path.startsWith("/api/v1/users/")) {
        if (method === "GET" && path === "/api/v1/users") {
            return json(res, 200, [{ id: "stub-admin-user", email: "admin@stub.test", isAdministrator: true }]);
        }
        if (method === "POST" && path === "/api/v1/users") {
            const body = await readBody(req);
            return json(res, 200, { id: `stub-user-${randomUUID().slice(0, 8)}`, email: body.email ?? "user@stub.test" });
        }
        if (method === "POST" && /^\/api\/v1\/users\/[^/]+\/api-keys$/.test(path)) {
            return json(res, 200, { apiKey: `stub-key-${randomUUID().replace(/-/g, "")}` });
        }
        return json(res, 200, {});
    }

    // Unknown Greenfield endpoint: loud 404 so a missing stub route is
    // obvious in the server log instead of a silent wrong answer.
    console.error(`[btcpay-stub] unhandled ${method} ${path}`);
    return json(res, 404, { message: `stub: unhandled ${method} ${path}` });
});

server.listen(PORT, () => {
    console.log(`[btcpay-stub] listening on :${PORT}, webhooks -> ${APP_URL}/api/webhooks/btcpay`);
});
