import { maxLength, NonEmptyString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { CompanyId, InvoicingLocalSchema } from "./schema";

const ImageDataUrlType = maxLength(131072)(NonEmptyString);

const ACCEPTED_MIME = /^image\/(png|jpeg|jpg|webp|gif)$/i;

function canvasToDataUrl(canvas: HTMLCanvasElement, mime: "image/jpeg" | "image/webp", quality: number): string {
    return canvas.toDataURL(mime, quality);
}

function dataUrlByteLength(dataUrl: string): number {
    return new Blob([dataUrl]).size;
}

function loadFileAsImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error("Could not load image."));
        };
        img.src = url;
    });
}

function drawScaledImage(
    img: HTMLImageElement,
    maxWidth: number,
    maxHeight: number,
): { canvas: HTMLCanvasElement; width: number; height: number } {
    let width = img.naturalWidth;
    let height = img.naturalHeight;
    const ratio = Math.min(maxWidth / width, maxHeight / height, 1);
    width = Math.max(1, Math.round(width * ratio));
    height = Math.max(1, Math.round(height * ratio));

    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d");
    if (!ctx) {
        throw new Error("Canvas is not supported.");
    }
    ctx.drawImage(img, 0, 0, width, height);
    return { canvas, width, height };
}

function encodeUnderBudget(
    canvas: HTMLCanvasElement,
    mime: "image/jpeg" | "image/webp",
    maxBytes: number,
): string | null {
    for (let quality = 0.92; quality >= 0.35; quality -= 0.07) {
        const dataUrl = canvasToDataUrl(canvas, mime, quality);
        if (dataUrlByteLength(dataUrl) <= maxBytes) {
            return dataUrl;
        }
    }
    return null;
}

/**
 * Resize and compress an image file to a JPEG/WebP data URL under maxBytes.
 */
export async function resizeImageFile(
    file: File,
    maxWidth: number,
    maxHeight: number,
    maxBytes: number,
): Promise<string> {
    if (!ACCEPTED_MIME.test(file.type)) {
        throw new Error("Unsupported image type.");
    }

    const img = await loadFileAsImage(file);
    let { canvas, width, height } = drawScaledImage(img, maxWidth, maxHeight);
    const preferWebp = file.type === "image/webp";
    const mime: "image/jpeg" | "image/webp" = preferWebp ? "image/webp" : "image/jpeg";

    let dataUrl = encodeUnderBudget(canvas, mime, maxBytes);
    while (!dataUrl && width > 32 && height > 32) {
        width = Math.max(32, Math.round(width * 0.85));
        height = Math.max(32, Math.round(height * 0.85));
        canvas = document.createElement("canvas");
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext("2d");
        if (!ctx) {
            throw new Error("Canvas is not supported.");
        }
        ctx.drawImage(img, 0, 0, width, height);
        dataUrl = encodeUnderBudget(canvas, mime, maxBytes);
    }

    if (!dataUrl) {
        throw new Error("Image is too large after compression.");
    }

    return dataUrl;
}

export function updateLocalCompanyLogo(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    dataUrl: string | null,
) {
    if (dataUrl == null) {
        return evolu.update("company", { id: companyId, logoDataUrl: null });
    }
    const parsed = ImageDataUrlType.from(dataUrl);
    if (!parsed.ok) return parsed;
    return evolu.update("company", { id: companyId, logoDataUrl: parsed.value });
}

export function updateLocalCompanySignature(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    dataUrl: string | null,
) {
    if (dataUrl == null) {
        return evolu.update("company", { id: companyId, signatureDataUrl: null });
    }
    const parsed = ImageDataUrlType.from(dataUrl);
    if (!parsed.ok) return parsed;
    return evolu.update("company", { id: companyId, signatureDataUrl: parsed.value });
}
