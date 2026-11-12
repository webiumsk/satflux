import { expect, test } from '@playwright/test';
import { mkdtemp, realpath, rm } from 'node:fs/promises';
import { createServer as createHttpServer } from 'node:http';
import type { AddressInfo } from 'node:net';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer, normalizePath, type ViteDevServer } from 'vite';
import { createIdokladPdf } from '../../resources/js/__tests__/fixtures/migration-idoklad-pdf/createPdf.mjs';

const repository = fileURLToPath(new URL('../../', import.meta.url));
const moduleUrl = (name: string) => '/@fs/'
    + normalizePath(resolve(repository, 'resources/js/evolu', name)).replace(/^\//, '');
const readerUrl = moduleUrl('migrationIsdocPdf.ts');
const parserUrl = moduleUrl('migrationIdokladPdf.ts');
let server: ViteDevServer | undefined;
let http: ReturnType<typeof createHttpServer> | undefined;
let directory: string;
let origin: string;

test.beforeAll(async () => {
    directory = await mkdtemp(join(tmpdir(), 'satflux-pdf-vite-'));
    server = await createServer({
        configFile: false,
        envDir: false,
        root: directory,
        publicDir: false,
        cacheDir: join(directory, 'cache'),
        optimizeDeps: { noDiscovery: true, exclude: ['pdfjs-dist'] },
        plugins: [{
            name: 'pdf-test-page',
            configureServer(vite) {
                vite.middlewares.use((request, response, next) => {
                    if (request.url !== '/') return next();
                    response.setHeader('Content-Type', 'text/html; charset=utf-8');
                    response.setHeader('Content-Security-Policy',
                        "default-src 'none'; script-src 'self'; worker-src 'self'; connect-src 'self'");
                    response.end('<!doctype html><meta charset="utf-8"><title>PDF worker integration</title>');
                });
            },
        }],
        server: {
            middlewareMode: true, hmr: false, watch: null,
            fs: { allow: [directory, resolve(repository, 'resources/js/evolu'),
                resolve(repository, 'node_modules'), await realpath(resolve(repository, 'node_modules'))] },
        },
    });
    http = createHttpServer(server.middlewares);
    await new Promise<void>((resolve, reject) => {
        http!.once('error', reject);
        http!.listen(0, '127.0.0.1', resolve);
    });
    origin = `http://127.0.0.1:${(http.address() as AddressInfo).port}`;
});

test.afterAll(async () => {
    try {
        if (http?.listening) {
            http.closeAllConnections();
            await new Promise<void>((resolve, reject) => http!.close(error => error ? reject(error) : resolve()));
        }
        await server?.close();
    }
    finally { if (directory) await rm(directory, { recursive: true, force: true }); }
});

test.beforeEach(async ({ context, page }) => {
    await context.route('**/*', route => {
        const request = route.request();
        return new URL(request.url()).origin === origin && request.method() === 'GET'
            && !new URL(request.url()).pathname.startsWith('/api/')
            ? route.continue() : route.abort('blockedbyclient');
    });
    await context.routeWebSocket('**/*', socket => socket.close());
    await page.goto(origin);
});

test('real owned PDF worker preserves iDoklad text, dates, identifiers and mixed VAT items', async ({ page }) => {
    const workers: string[] = [];
    const closed = new Set<string>();
    const pageErrors: string[] = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('worker', worker => {
        workers.push(worker.url());
        worker.on('close', () => closed.add(worker.url()));
    });
    const pdf = createIdokladPdf({
        number: '00001234', buyerName: 'Žltý kôň, s. r. o.',
        supplierRegistration: '00012345', buyerRegistration: '00054321',
        issueDate: '01.10.2026', deliveryDate: '02.10.2026', dueDate: '15.10.2026',
        lines: [
            { name: 'Údržba softvéru', quantity: '2', unit: 'h', unitPrice: '50', rate: '23', net: '100', tax: '23', gross: '123' },
            { name: 'Doplnková služba', quantity: '0.5', unit: 'ks', unitPrice: '40', rate: '10', net: '20', tax: '2', gross: '22' },
        ],
    });
    const result = await page.evaluate(async ({ bytes, readerUrl, parserUrl }) => {
        const reader = await import(readerUrl) as typeof import('../../resources/js/evolu/migrationIsdocPdf');
        const parser = await import(parserUrl) as typeof import('../../resources/js/evolu/migrationIdokladPdf');
        const original = new Uint8Array(bytes);
        const before = Array.from(original);
        const pages = await reader.readMigrationPdfTextPages(original);
        return { pages: pages.length, invoices: parser.parseIdokladPdfText(pages),
            preservedBytes: original.length === before.length && original.every((byte, index) => byte === before[index]) };
    }, { bytes: Array.from(pdf), readerUrl, parserUrl });

    expect(result).toEqual({ pages: 1, preservedBytes: true, invoices: [{
        dueDate: '2026-10-15', deliveryDate: '2026-10-02', invoice: {
            documentNumber: '00001234', issueDate: '2026-10-01', currency: 'EUR',
            supplierRegistrationNumber: '00012345', buyerRegistrationNumber: '00054321', buyerName: 'Žltý kôň, s. r. o.',
            subtotal: '120.00', taxTotal: '25.00', total: '145.00', lines: [
                { name: 'Údržba softvéru', description: null, quantity: '2.0000', unit: 'h', unitPrice: '50.0000',
                    lineDiscountPercent: '0.00', taxRate: '23.00', lineTotal: '123.00' },
                { name: 'Doplnková služba', description: null, quantity: '0.5000', unit: 'ks', unitPrice: '40.0000',
                    lineDiscountPercent: '0.00', taxRate: '10.00', lineTotal: '22.00' },
            ],
        },
    }] });
    expect(workers).toHaveLength(1);
    expect(new URL(workers[0]).origin).toBe(origin);
    expect(workers[0]).toContain('pdf.worker');
    await expect.poll(() => closed.size).toBe(1);
    expect(pageErrors).toEqual([]);
});

test('rejects inconsistent VAT after extracting actual PDF bytes', async ({ page }) => {
    const pdf = createIdokladPdf({ lines: [
        { quantity: '1', unitPrice: '100', rate: '23', net: '100', tax: '22.99', gross: '122.99' },
    ] });
    const result = await page.evaluate(async ({ bytes, readerUrl, parserUrl }) => {
        const reader = await import(readerUrl) as typeof import('../../resources/js/evolu/migrationIsdocPdf');
        const parser = await import(parserUrl) as typeof import('../../resources/js/evolu/migrationIdokladPdf');
        const pages = await reader.readMigrationPdfTextPages(new Uint8Array(bytes));
        try { return { pages: pages.length, invoices: parser.parseIdokladPdfText(pages) }; }
        catch (error) { return { pages: pages.length, error: error instanceof parser.IdokladPdfError ? error.code : 'unexpected_error' }; }
    }, { bytes: Array.from(pdf), readerUrl, parserUrl });
    expect(result).toEqual({ pages: 1, error: 'idoklad_pdf_totals' });
});
