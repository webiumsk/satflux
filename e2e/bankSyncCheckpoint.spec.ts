import { test, expect, type Browser, type Page } from '@playwright/test';
import { buildSync } from 'esbuild';
import { fileURLToPath } from 'node:url';

type Checkpoint = { context: string; cursor: string };
type Store = {
    readBankSyncCheckpoint(slot: string, signal: AbortSignal): Promise<Checkpoint | null>;
    compareAndSetBankSyncCheckpoint(slot: string, expected: Checkpoint | null, next: Checkpoint | null,
        options: { signal: AbortSignal; isCurrent: () => boolean }): Promise<'saved' | 'conflict' | 'stale'>;
};

const moduleSource = buildSync({
    entryPoints: [fileURLToPath(new URL('../resources/js/services/bankSyncCheckpointStore.ts', import.meta.url))],
    bundle: true, format: 'esm', platform: 'browser', write: false, sourcemap: false,
}).outputFiles[0].text;
const slot = '1'.repeat(64);
const first: Checkpoint = { context: 'a'.repeat(64), cursor: 'synthetic-encrypted-cursor-first' };
const second: Checkpoint = { context: 'a'.repeat(64), cursor: 'synthetic-encrypted-cursor-second' };

async function loadStore(page: Page): Promise<void> {
    await page.goto('http://satflux-checkpoint-qa.invalid/');
    await page.evaluate(async (source) => {
        const url = URL.createObjectURL(new Blob([source], { type: 'text/javascript' }));
        try { Reflect.set(globalThis, '__qaCheckpointStore', await import(url)); }
        finally { URL.revokeObjectURL(url); }
    }, moduleSource);
}

async function isolatedPages(browser: Browser) {
    // A fresh browser context and intercepted, non-application origin share only synthetic test data.
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    await context.route('http://satflux-checkpoint-qa.invalid/**', (route) => route.fulfill({
        contentType: 'text/html', body: '<!doctype html><title>Synthetic native IndexedDB checkpoint test</title>',
    }));
    const page = await context.newPage();
    await loadStore(page);
    return { context, page };
}

test('native IndexedDB serializes competing CAS operations and persists the winner across page remount', async ({ browser }) => {
    const { context, page } = await isolatedPages(browser);
    try {
        const other = await context.newPage();
        await loadStore(other);
        const write = (target: Page, next: Checkpoint) => target.evaluate(async ({ slot, next }) => {
            const store = Reflect.get(globalThis, '__qaCheckpointStore') as Store;
            return store.compareAndSetBankSyncCheckpoint(slot, null, next,
                { signal: new AbortController().signal, isCurrent: () => true });
        }, { slot, next });
        const results = await Promise.all([write(page, first), write(other, second)]);
        expect([...results].sort()).toEqual(['conflict', 'saved']);
        const winner = results[0] === 'saved' ? first : second;
        // Read the actual value rather than relying on completion order or an in-memory client cache.
        const stored = await other.evaluate(async (slot) => {
            const store = Reflect.get(globalThis, '__qaCheckpointStore') as Store;
            return store.readBankSyncCheckpoint(slot, new AbortController().signal);
        }, slot);
        expect(stored).toEqual(winner);
        await page.close();
        await other.close();
        const remounted = await context.newPage();
        await loadStore(remounted);
        const reopened = await remounted.evaluate(async (slot) => {
            const store = Reflect.get(globalThis, '__qaCheckpointStore') as Store;
            return store.readBankSyncCheckpoint(slot, new AbortController().signal);
        }, slot);
        expect(reopened).toEqual(stored);
        expect(Object.keys(reopened ?? {}).sort()).toEqual(['context', 'cursor']);
    } finally { await context.close(); }
});

test('native put request success followed by transaction abort rejects and preserves the previous checkpoint', async ({ browser }) => {
    const { context, page } = await isolatedPages(browser);
    try {
        const result = await page.evaluate(async ({ slot, first, second }) => {
            const store = Reflect.get(globalThis, '__qaCheckpointStore') as Store;
            const options = { signal: new AbortController().signal, isCurrent: () => true };
            await store.compareAndSetBankSyncCheckpoint(slot, null, first, options);
            const originalPut = IDBObjectStore.prototype.put;
            let requestSucceeded = 0;
            IDBObjectStore.prototype.put = function (value, key) {
                const request = originalPut.call(this, value, key);
                if (this.transaction.db.name === 'satflux-bank-sync' && key === slot) {
                    request.addEventListener('success', () => { requestSucceeded++; this.transaction.abort(); }, { once: true });
                }
                return request;
            };
            let rejected = false;
            try { await store.compareAndSetBankSyncCheckpoint(slot, first, second, options); }
            catch { rejected = true; }
            finally { IDBObjectStore.prototype.put = originalPut; }
            return { rejected, requestSucceeded, stored: await store.readBankSyncCheckpoint(slot, options.signal) };
        }, { slot, first, second });
        expect(result.requestSucceeded).toBe(1);
        expect(result.rejected).toBe(true);
        expect(result.stored).toEqual(first);
    } finally { await context.close(); }
});

test('native stale and aborted contexts perform no put and leave the checkpoint intact', async ({ browser }) => {
    const { context, page } = await isolatedPages(browser);
    try {
        const result = await page.evaluate(async ({ slot, first, second }) => {
            const store = Reflect.get(globalThis, '__qaCheckpointStore') as Store;
            await store.compareAndSetBankSyncCheckpoint(slot, null, first,
                { signal: new AbortController().signal, isCurrent: () => true });
            const originalPut = IDBObjectStore.prototype.put;
            const originalGet = IDBObjectStore.prototype.get;
            let puts = 0;
            IDBObjectStore.prototype.put = function (value, key) {
                if (this.transaction.db.name === 'satflux-bank-sync' && key === slot) puts++;
                return originalPut.call(this, value, key);
            };
            let aborted = false;
            let stale: string | undefined;
            try {
                stale = await store.compareAndSetBankSyncCheckpoint(slot, first, second,
                    { signal: new AbortController().signal, isCurrent: () => false });
                const controller = new AbortController();
                IDBObjectStore.prototype.get = function (key) {
                    const request = originalGet.call(this, key);
                    if (this.transaction.db.name === 'satflux-bank-sync' && key === slot) {
                        request.addEventListener('success', () => controller.abort(), { once: true });
                    }
                    return request;
                };
                try {
                    await store.compareAndSetBankSyncCheckpoint(slot, first, second,
                        { signal: controller.signal, isCurrent: () => true });
                } catch { aborted = true; }
            } finally {
                IDBObjectStore.prototype.put = originalPut;
                IDBObjectStore.prototype.get = originalGet;
            }
            return { stale, aborted, puts, stored: await store.readBankSyncCheckpoint(slot, new AbortController().signal) };
        }, { slot, first, second });
        expect(result.stale).toBe('stale');
        expect(result.aborted).toBe(true);
        expect(result.puts).toBe(0);
        expect(result.stored).toEqual(first);
    } finally { await context.close(); }
});

test('native CAS resolves after transaction completion rather than provisional request success', async ({ browser }) => {
    const { context, page } = await isolatedPages(browser);
    try {
        const result = await page.evaluate(async ({ slot, first }) => {
            const store = Reflect.get(globalThis, '__qaCheckpointStore') as Store;
            const events: string[] = [];
            const originalTransaction = IDBDatabase.prototype.transaction;
            const originalPut = IDBObjectStore.prototype.put;
            IDBDatabase.prototype.transaction = function (...args) {
                const transaction = originalTransaction.apply(this, args);
                if (this.name === 'satflux-bank-sync' && args[1] === 'readwrite') {
                    transaction.addEventListener('complete', () => events.push('transaction-complete'), { once: true });
                }
                return transaction;
            };
            IDBObjectStore.prototype.put = function (value, key) {
                const request = originalPut.call(this, value, key);
                if (this.transaction.db.name === 'satflux-bank-sync' && key === slot) {
                    request.addEventListener('success', () => events.push('request-success'), { once: true });
                }
                return request;
            };
            let saved: string;
            try {
                saved = await store.compareAndSetBankSyncCheckpoint(slot, null, first,
                    { signal: new AbortController().signal, isCurrent: () => true }).then((value) => {
                    events.push('promise-resolved');
                    return value;
                });
            } finally {
                IDBDatabase.prototype.transaction = originalTransaction;
                IDBObjectStore.prototype.put = originalPut;
            }
            return { saved, events };
        }, { slot, first });
        expect(result.saved).toBe('saved');
        expect(result.events).toEqual(['request-success', 'transaction-complete', 'promise-resolved']);
    } finally { await context.close(); }
});
