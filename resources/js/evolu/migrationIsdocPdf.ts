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

async function readPdfIsdoc(bytes: Uint8Array): Promise<MigrationStructuredInvoice | null> {
    let port: Worker | undefined;
    let worker: PDFWorker | undefined;
    let task: PDFDocumentLoadingTask | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    function deadline(ms: number): Promise<never> {
        clearTimeout(timer);
        return new Promise((_, reject) => {
            timer = setTimeout(() => reject(unreadable()), ms);
        });
    }
    try {
        const initializationDeadline = deadline(MAX_PDF_INIT_MS);
        const [{ getDocument, PDFWorker, VerbosityLevel }, { default: workerUrl }] = await Promise.race([
            Promise.all([
                import("pdfjs-dist/legacy/build/pdf.mjs"),
                import("pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url"),
            ]),
            initializationDeadline,
        ]);
        const url = new URL(workerUrl, window.location.href);
        if (url.origin !== window.location.origin || !["http:", "https:"].includes(url.protocol)) throw unreadable();

        // Supply a real, owned worker port so PDF.js cannot fall back to parsing on the UI thread.
        const livePort = port = new Worker(url, { type: "module" });
        const workerFailure = new Promise<never>((_, reject) => {
            livePort.addEventListener("error", () => reject(unreadable()), { once: true });
            livePort.addEventListener("messageerror", () => reject(unreadable()), { once: true });
        });
        await Promise.race([waitForPdfWorker(livePort), initializationDeadline, workerFailure]);

        // The parsing budget starts before transferring untrusted bytes, after the trusted worker loads.
        const parsingDeadline = deadline(MAX_PDF_READ_MS);
        worker = PDFWorker.create({ port: livePort, verbosity: VerbosityLevel.ERRORS });
        const loading = task = getDocument({
            // PDF.js transfers ownership of this buffer. Keep the original for local archival.
            data: new Uint8Array(bytes),
            worker,
            disableWorkerRendering: true,
            disableFontFace: true,
            enableXfa: false,
            stopAtErrors: true,
            useWasm: false,
            useWorkerFetch: false,
            verbosity: VerbosityLevel.ERRORS,
        });
        return await Promise.race([readAttachments(loading), parsingDeadline, workerFailure]);
    } finally {
        clearTimeout(timer);
        // Do not wait for a stuck worker to acknowledge its own shutdown.
        try {
            void task?.destroy().catch(() => undefined);
            worker?.destroy();
        } finally {
            port?.terminate();
        }
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
        if (/\.pdf$/i.test(fileName)) return await readPdfIsdoc(bytes);
        if (/\.isdoc$/i.test(fileName)) return parseMigrationIsdoc(bytes);
        if (/\.xml$/i.test(fileName)) return readMigrationIsdocXml(bytes);
        throw unreadable();
    } catch (error) {
        if (error instanceof MigrationIsdocError) throw error;
        throw unreadable();
    }
}
