import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { OwnerId } from '@evolu/common/local-first';

const mocks = vi.hoisted(() => ({ loadQuery: vi.fn(), log: vi.fn() }));
vi.mock('@/evolu/client', () => ({ evolu: { loadQuery: mocks.loadQuery, appOwner: Promise.resolve({ id: 'private-owner' }) }, allDocumentsQuery: 'documents', allDocumentEventsQuery: 'events' }));
vi.mock('@/evolu/schema', () => ({ DocumentId: { from: (id: string) => ({ ok: true, value: id }) } }));
vi.mock('@/evolu/documentEventLog', () => ({ logDocumentEvent: mocks.log }));

import { EMAIL_ATTENTION_TIMEOUT_MS, recordLocalEmailFailure } from '@/evolu/localEmailAttention';
import { clearCompanyShares, markCompanyShareRegistryReady, setCompanyShare } from '@/evolu/companyShareRegistry';

function shared(status: 'active' | 'revoked') {
    setCompanyShare({ companyId: 'company-a', ownerId: 'owner-a' as OwnerId, role: 'member', status, bridgeCompanyId: null });
}

beforeEach(() => {
    vi.clearAllMocks();
    clearCompanyShares();
    markCompanyShareRegistryReady();
    mocks.loadQuery.mockResolvedValue([{ id: 'doc-a', companyId: 'company-a', ownerId: 'private-owner' }]);
    mocks.log.mockReturnValue({ ok: true });
});
afterEach(() => vi.useRealTimers());

describe('encrypted local email attention evidence', () => {
    it('records only a generic reason for the exact company document', async () => {
        expect(await recordLocalEmailFailure('company-a', 'doc-a')).toBe(true);
        expect(mocks.log).toHaveBeenCalledWith(expect.anything(), 'doc-a', 'business_document.email_failed', {
            reason: 'delivery_failed', attempt_started_at: expect.any(String),
        });
    });

    it('does not attach an event to a document belonging to another company', async () => {
        expect(await recordLocalEmailFailure('company-b', 'doc-a')).toBe(false);
        expect(mocks.log).not.toHaveBeenCalled();
    });

    it('does not fork a revoked company into the private owner', async () => {
        shared('revoked');
        expect(await recordLocalEmailFailure('company-a', 'doc-a')).toBe(false);
        expect(mocks.loadQuery).not.toHaveBeenCalled();
        expect(mocks.log).not.toHaveBeenCalled();
    });

    it('rejects a colliding document from another owner partition', async () => {
        mocks.loadQuery.mockResolvedValue([{ id: 'doc-a', companyId: 'company-a', ownerId: 'another-owner' }]);
        expect(await recordLocalEmailFailure('company-a', 'doc-a')).toBe(false);
        expect(mocks.log).not.toHaveBeenCalled();
    });

    it('fails closed while company routing is not initialized', async () => {
        clearCompanyShares();
        expect(await recordLocalEmailFailure('company-a', 'doc-a')).toBe(false);
        expect(mocks.log).not.toHaveBeenCalled();
    });

    it('drops a pending write when the company share changes during the read', async () => {
        shared('active');
        let finish!: (rows: { id: string; companyId: string }[]) => void;
        mocks.loadQuery.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
        const pending = recordLocalEmailFailure('company-a', 'doc-a');
        await vi.waitFor(() => expect(mocks.loadQuery).toHaveBeenCalled());
        shared('revoked');
        finish([{ id: 'doc-a', companyId: 'company-a' }]);
        expect(await pending).toBe(false);
        expect(mocks.log).not.toHaveBeenCalled();
    });

    it('does not replace the sending outcome with an audit-worker failure', async () => {
        mocks.loadQuery.mockRejectedValue(new Error('synthetic local worker failure'));
        expect(await recordLocalEmailFailure('company-a', 'doc-a')).toBe(false);
        expect(mocks.log).not.toHaveBeenCalled();
    });

    it('expires a stalled worker and never writes if that read later completes', async () => {
        vi.useFakeTimers();
        let finish!: (rows: { id: string; companyId: string; ownerId: string }[]) => void;
        const delayed = new Promise(resolve => { finish = resolve; });
        mocks.loadQuery.mockReturnValue(delayed);
        const pending = recordLocalEmailFailure('company-a', 'doc-a');
        await vi.advanceTimersByTimeAsync(EMAIL_ATTENTION_TIMEOUT_MS + 1);
        expect(await pending).toBe(false);
        finish([{ id: 'doc-a', companyId: 'company-a', ownerId: 'private-owner' }]);
        await vi.advanceTimersByTimeAsync(1);
        expect(mocks.log).not.toHaveBeenCalled();
    });

    it('does not append an earlier failure after a newer successful send', async () => {
        mocks.loadQuery.mockImplementation(async query => query === 'events'
            ? [{ ownerId: 'private-owner', documentId: 'doc-a', action: 'business_document.email_sent', createdAt: '2026-11-10T12:01:00Z' }]
            : [{ id: 'doc-a', companyId: 'company-a', ownerId: 'private-owner' }]);
        expect(await recordLocalEmailFailure('company-a', 'doc-a', '2026-11-10T12:00:00Z')).toBe(false);
        expect(mocks.log).not.toHaveBeenCalled();
    });
});
