import {
    MigrationIsdocError,
    parseMigrationIsdoc,
    readMigrationIsdocXml,
    type MigrationStructuredInvoice,
} from "./migrationIsdoc";
import { MIGRATION_MAX_FILE_BYTES } from "./migrationArchiveValues";
import type { PDFDocumentLoadingTask, PDFWorker } from "pdfjs-dist/legacy/build/pdf.mjs";

const MAX_PDF_INIT_MS = 15_000;
const MAX_PDF_READ_MS = 6_000;
const MAX_XML_ATTACHMENTS = 6;

function unreadable(): MigrationIsdocError {
    return new MigrationIsdocError("migration_isdoc_unreadable");
}

function waitForPdfWorker(port: Worker): Promise<void> {
    return new Promise(resolve => {
        function onMessage(event: MessageEvent<unknown>) {
            const data = event.data;
            // The pinned PDF.js worker sends this after installing its message handlers.
            if (!data || typeof data !== "object" || !("action" in data) || data.action !== "ready"
                || !("sourceName" in data) || data.sourceName !== "worker"
                || !("targetName" in data) || data.targetName !== "main") return;
            port.removeEventListener("message", onMessage);
            resolve();
        }
        port.addEventListener("message", onMessage);
    });
}

async function readIsolatedPdf<T>(
    bytes: Uint8Array,
    read: (task: PDFDocumentLoadingTask) => Promise<T>,
    signal?: AbortSignal,
): Promise<T> {
    let port: Worker | undefined;
    let worker: PDFWorker | undefined;
    let task: PDFDocumentLoadingTask | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let onAbort: (() => void) | undefined;
    function deadline(ms: number): Promise<never> {
        clearTimeout(timer);
        return new Promise((_, reject) => {
            timer = setTimeout(() => reject(unreadable()), ms);
        });
    }
    try {
        if (signal?.aborted) throw unreadable();
        const aborted = new Promise<never>((_, reject) => {
            onAbort = () => reject(unreadable());
            signal?.addEventListener("abort", onAbort, { once: true });
        });
        const initializationDeadline = deadline(MAX_PDF_INIT_MS);
        const [{ getDocument, PDFWorker, VerbosityLevel }, { default: workerUrl }] = await Promise.race([
            Promise.all([
                import("pdfjs-dist/legacy/build/pdf.mjs"),
                import("pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url"),
            ]),
            initializationDeadline,
            aborted,
        ]);
        const url = new URL(workerUrl, window.location.href);
        if (url.origin !== window.location.origin || !["http:", "https:"].includes(url.protocol)) throw unreadable();

        // Supply a real, owned worker port so PDF.js cannot fall back to parsing on the UI thread.
        const livePort = port = new Worker(url, { type: "module" });
        const workerFailure = new Promise<never>((_, reject) => {
            livePort.addEventListener("error", () => reject(unreadable()), { once: true });
            livePort.addEventListener("messageerror", () => reject(unreadable()), { once: true });
        });
        await Promise.race([waitForPdfWorker(livePort), initializationDeadline, workerFailure, aborted]);

        // The parsing budget starts before transferring untrusted bytes, after the trusted worker loads.
        const parsingDeadline = deadline(MAX_PDF_READ_MS);
        worker = PDFWorker.create({ port: livePort, verbosity: VerbosityLevel.ERRORS });
        const parameters: NonNullable<Parameters<typeof getDocument>[0]> & { isEvalSupported: false } = {
            // PDF.js transfers ownership of this buffer. Keep the original for local archival.
            data: new Uint8Array(bytes),
            worker,
            disableWorkerRendering: true,
            disableFontFace: true,
            enableXfa: false,
            isEvalSupported: false,
            stopAtErrors: true,
            useWasm: false,
            useWorkerFetch: false,
            verbosity: VerbosityLevel.ERRORS,
        };
        const loading = task = getDocument(parameters);
        return await Promise.race([read(loading), parsingDeadline, workerFailure, aborted]);
    } finally {
        clearTimeout(timer);
        if (onAbort) signal?.removeEventListener("abort", onAbort);
        // Do not wait for a stuck worker to acknowledge its own shutdown.
        try {
            void task?.destroy().catch(() => undefined);
            worker?.destroy();
        } finally {
            port?.terminate();
        }
    }
}

export type MigrationPdfTextItem = {
    str: string;
    dir: string;
    width: number;
    height: number;
    transform: readonly number[];
};

export type MigrationPdfTextPage = {
    width: number;
    height: number;
    rotation: number;
    items: readonly MigrationPdfTextItem[];
};

/** Bounded plain text only, under the same owned-worker lifetime as ISDOC reads. */
export async function readMigrationPdfTextPages(bytes: Uint8Array, options: { signal?: AbortSignal } = {}): Promise<MigrationPdfTextPage[]> {
    if (!bytes.length || bytes.length > MIGRATION_MAX_FILE_BYTES) throw unreadable();
    try {
        return await readIsolatedPdf(bytes, async task => {
            const document = await task.promise;
            if (!Number.isInteger(document.numPages) || document.numPages < 1 || document.numPages > 50) throw unreadable();
            const pages: MigrationPdfTextPage[] = [];
            let totalItems = 0, totalCharacters = 0;
            for (let number = 1; number <= document.numPages; number++) {
                if (task.destroyed || options.signal?.aborted) throw unreadable();
                const page = await document.getPage(number);
                const viewport = page.getViewport({ scale: 1 });
                if (![viewport.width, viewport.height].every(value => Number.isFinite(value) && value > 0 && value <= 20_000)) throw unreadable();
                const items: MigrationPdfTextItem[] = [];
                let characters = 0;
                const reader = page.streamTextContent({ includeMarkedContent: false, disableNormalization: true }).getReader();
                try {
                    for (;;) {
                        if (task.destroyed || options.signal?.aborted) throw unreadable();
                        const chunk = await reader.read();
                        if (chunk.done) break;
                        if (!Array.isArray(chunk.value.items)) throw unreadable();
                        for (const item of chunk.value.items) {
                            if (!("str" in item) || typeof item.str !== "string" || item.str.length > 4000
                                || !Array.isArray(item.transform) || item.transform.length !== 6
                                || !item.transform.every((value: unknown) => typeof value === "number" && Number.isFinite(value) && Math.abs(value) <= 1_000_000)
                                || ![item.width, item.height].every(value => Number.isFinite(value) && value >= 0 && value <= 20_000)) throw unreadable();
                            characters += item.str.length;
                            totalCharacters += item.str.length;
                            totalItems++;
                            if (items.length >= 5000 || totalItems > 20_000 || characters > 50_000 || totalCharacters > 200_000) throw unreadable();
                            items.push({ str: item.str, dir: item.dir, width: item.width, height: item.height, transform: [...item.transform] });
                        }
                    }
                } finally {
                    void reader.cancel().catch(() => undefined);
                    reader.releaseLock();
                }
                pages.push({ width: viewport.width, height: viewport.height, rotation: page.rotate, items });
                page.cleanup();
            }
            return pages;
        }, options.signal);
    } catch (error) {
        if (error instanceof MigrationIsdocError) throw error;
        throw unreadable();
    }
}

async function readAttachments(task: PDFDocumentLoadingTask): Promise<MigrationStructuredInvoice | null> {
    const document = await task.promise;
    const attachments = await document.getAttachments();
    const candidates = [...(attachments ?? [])].filter(([, attachment]) => /\.(?:isdoc|xml)$/i.test(attachment.filename));
    if (candidates.length > MAX_XML_ATTACHMENTS) throw new MigrationIsdocError("migration_isdoc_ambiguous");
    let evidence: MigrationStructuredInvoice | null = null;
    for (const [id, attachment] of candidates) {
        // A timed-out operation must not advance to another attachment.
        if (task.destroyed) throw unreadable();
        const content = await document.getAttachmentContent(id);
        if (!(content instanceof Uint8Array)) throw unreadable();
        const parsed = /\.isdoc$/i.test(attachment.filename)
            ? parseMigrationIsdoc(content)
            : readMigrationIsdocXml(content);
        if (!parsed) continue;
        if (evidence) throw new MigrationIsdocError("migration_isdoc_ambiguous");
        evidence = parsed;
    }
    return evidence;
}

/** Read only document-level attachments, never render pages, execute PDF actions or fetch a document URL. */
export async function readMigrationIsdocEvidence(fileName: string, bytes: Uint8Array): Promise<MigrationStructuredInvoice | null> {
    if (!bytes.length || bytes.length > MIGRATION_MAX_FILE_BYTES) throw unreadable();
    try {
        if (/\.pdf$/i.test(fileName)) return await readIsolatedPdf(bytes, readAttachments);
        if (/\.isdoc$/i.test(fileName)) return parseMigrationIsdoc(bytes);
        if (/\.xml$/i.test(fileName)) return readMigrationIsdocXml(bytes);
        throw unreadable();
    } catch (error) {
        if (error instanceof MigrationIsdocError) throw error;
        throw unreadable();
    }
}
