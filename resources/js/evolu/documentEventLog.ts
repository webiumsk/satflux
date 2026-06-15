import { maxLength, NonEmptyString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { DocumentId, InvoicingLocalSchema } from "./schema";

const ActionType = maxLength(128)(NonEmptyString);

export type EvoluDocumentEventRow = {
    id: string;
    documentId: string;
    action: string;
    metadataJson: string | null;
    createdAt: string;
};

export type DocumentHistoryEntry = {
    id: string;
    action: string;
    created_at: string;
    user: null;
    metadata: Record<string, unknown> | null;
};

export function logDocumentEvent(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    action: string,
    metadata?: Record<string, unknown>,
) {
    const actionParsed = ActionType.from(action);
    if (!actionParsed.ok) return actionParsed;

    const metadataJson =
        metadata && Object.keys(metadata).length > 0 ? JSON.stringify(metadata) : null;

    return evolu.insert("documentEvent", {
        documentId,
        action: actionParsed.value,
        metadataJson,
    });
}

export function documentHistoryFromEvents(events: EvoluDocumentEventRow[]): DocumentHistoryEntry[] {
    return [...events]
        .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
        .slice(0, 50)
        .map((event) => ({
            id: event.id,
            action: event.action,
            created_at: event.createdAt,
            user: null,
            metadata: (() => {
                if (!event.metadataJson) return null;
                try {
                    return JSON.parse(event.metadataJson) as Record<string, unknown>;
                } catch {
                    return null;
                }
            })(),
        }));
}
