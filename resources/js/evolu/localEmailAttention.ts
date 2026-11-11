import { companyShareInfo, companyShareRoutingKey, isCompanyShareRegistryReady } from './companyShareRegistry';
import { withTimeout } from './asyncTimeout';

export const EMAIL_ATTENTION_TIMEOUT_MS = 2500;

/** Best-effort encrypted evidence. Never retain recipient, message or transport error. */
export async function recordLocalEmailFailure(companyId: string, documentId: string, attemptStartedAt = new Date().toISOString()): Promise<boolean> {
    if (!companyId || !documentId || !isCompanyShareRegistryReady()) return false;
    if (!Number.isFinite(Date.parse(attemptStartedAt))) return false;
    const share = companyShareInfo(companyId);
    if (share && share.status !== 'active') return false;
    const routing = companyShareRoutingKey(companyId);
    let finished = false;
    const work = async (): Promise<boolean> => {
        const [{ evolu, allDocumentsQuery, allDocumentEventsQuery }, { DocumentId }, { logDocumentEvent }] = await Promise.all([
            import('./client'), import('./schema'), import('./documentEventLog'),
        ]);
        if (finished) return false;
        const expectedOwner = share?.ownerId ?? (await evolu.appOwner).id;
        if (finished) return false;
        const [rows, events] = await Promise.all([evolu.loadQuery(allDocumentsQuery), evolu.loadQuery(allDocumentEventsQuery)]);
        if (finished || companyShareRoutingKey(companyId) !== routing) return false;
        const document = rows.find(row => String(row.id) === documentId && String(row.companyId) === companyId
            && Reflect.get(row, 'ownerId') === expectedOwner);
        const parsed = DocumentId.from(documentId);
        if (!document || !parsed.ok) return false;
        // A slower earlier attempt must not replace a newer successful attempt.
        if (events.some(event => Reflect.get(event, 'ownerId') === expectedOwner && String(event.documentId) === documentId
            && event.action === 'business_document.email_sent' && Date.parse(event.createdAt) > Date.parse(attemptStartedAt))) return false;
        const result = logDocumentEvent(evolu, parsed.value, 'business_document.email_failed', {
            reason: 'delivery_failed', attempt_started_at: attemptStartedAt,
        });
        return result?.ok === true;
    };
    try {
        return await withTimeout(work(), EMAIL_ATTENTION_TIMEOUT_MS);
    } catch {
        // A local audit failure must not hide the original sending result or leak
        // private worker/transport details into a second notification.
        return false;
    } finally {
        finished = true;
    }
}
