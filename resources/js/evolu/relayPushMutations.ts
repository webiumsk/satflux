import type { Evolu } from "@evolu/common/local-first";
import { allCompaniesDetailQuery, allDocumentsQuery } from "./client";
import { logDocumentEvent, RELAY_SYNC_EVENT_ACTION } from "./documentEventLog";
import type { CompanyId, DocumentId, InvoicingLocalSchema } from "./schema";
import type { EvoluDocumentRow } from "./documentMap";
import { toAppRows } from "./queryLoad";

/** Wait for Evolu worker WebSocket + subscribe upload before mutating. */
export const RELAY_CONNECTION_WARMUP_MS = 12_000;

export async function bumpCompaniesForRelayPush(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId?: string,
): Promise<number> {
    const companies = await evolu.loadQuery(allCompaniesDetailQuery);
    let updated = 0;

    for (const row of companies) {
        if (companyId && String(row.id) !== companyId) {
            continue;
        }
        let settings: Record<string, unknown> = {};
        try {
            settings = JSON.parse(String(row.appSettingsJson ?? "{}")) as Record<string, unknown>;
        } catch {
            settings = {};
        }
        const result = evolu.update("company", {
            id: row.id as CompanyId,
            appSettingsJson: JSON.stringify({
                ...settings,
                satfluxRelayPushAt: Date.now(),
            }),
        });
        if (result.ok) {
            updated += 1;
        }
    }

    return updated;
}

export async function bumpDocumentsForRelayPush(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId?: string,
): Promise<number> {
    const documents = toAppRows<EvoluDocumentRow>((await evolu.loadQuery(allDocumentsQuery)));
    let inserted = 0;

    for (const doc of documents) {
        if (companyId && doc.companyId !== companyId) {
            continue;
        }
        const result = logDocumentEvent(
            evolu,
            doc.id as DocumentId,
            RELAY_SYNC_EVENT_ACTION,
            { push: Date.now() },
        );
        if (result.ok) {
            inserted += 1;
        }
    }

    return inserted;
}

export async function readEvoluOwnerHint(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<string> {
    const owner = await evolu.appOwner;
    return String(owner.id).slice(0, 12);
}

export function readEvoluErrorMessage(evolu: Evolu<InvoicingLocalSchema>): string | null {
    const error = evolu.getError();
    if (!error) {
        return null;
    }
    if (typeof error === "object" && error !== null && "type" in error) {
        return String((error as { type: string }).type);
    }
    return String(error);
}
