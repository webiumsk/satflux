import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref, shallowRef, type EffectScope } from 'vue';
import { createBankExpenseClaim, createBankExpensePaymentIndex, effectiveExpensePayment, projectExpensePayments } from '@/evolu/bankExpenseClaims';
import type { EvoluExpenseRow } from '@/evolu/expenseMap';
import type { EvoluBankTransactionMatchRow, EvoluBankTransactionRow } from '@/evolu/bankMap';
import type { EvoluDocumentRow } from '@/evolu/documentMap';
import type { BankPaymentContext } from '@/evolu/bankCrud';

const mocks = vi.hoisted(() => ({ queryRows: new Map<string, { value: unknown[] }>(), loadQuery: vi.fn() }));
vi.mock('@evolu/vue', () => ({ useQuery: (query: string) => mocks.queryRows.get(query) ?? { value: [] } }));
vi.mock('@/evolu/client', () => ({
    allBankImportBatchesQuery: 'batches', allBankTransactionMatchesQuery: 'matches', allBankTransactionsQuery: 'transactions',
    allCompaniesDetailQuery: 'companies', allDocumentsQuery: 'documents', allExpensesQuery: 'expenses', allExpenseAttachmentsQuery: 'attachments',
    useInvoicingEvolu: () => ({ loadQuery: mocks.loadQuery, appOwner: Promise.resolve({ id: 'owner-a' }) }),
}));
vi.mock('@/evolu/documentCrud', () => ({ markLocalDocumentPaidWithAmount: vi.fn(), unmarkLocalDocumentPaid: vi.fn() }));
vi.mock('@/evolu/flags', () => ({ isInvoicingLocalFirst: () => true }));
vi.mock('@/evolu/bridgeCompanyEnsure', () => ({ ensureBridgeCompanyIdForLocalCompany: vi.fn(async () => ({ ok: false })) }));
vi.mock('@/composables/useLocalInvoiceDocument', () => ({ useLocalInvoiceDocumentSupport: () => ({ refreshAll: vi.fn() }) }));
vi.mock('@/evolu/ephemeralBridge', () => ({ buildAccountantExportEphemeralRequest: vi.fn(), fetchEphemeralAccountantExport: vi.fn(), downloadResponseBlob: vi.fn() }));
import { mapLocalBankTransactionsToApi } from '@/evolu/bankCrud';
import { useInvoicingExpenses, useInvoicingExpense } from '@/composables/useInvoicingExpenses';
import { useAccountantExport } from '@/composables/useAccountantExport';
import { useInvoicingBankPayments } from '@/composables/useInvoicingBankPayments';
import { clearCompanyShares, markCompanyShareRegistryReady } from '@/evolu/companyShareRegistry';

const scopes: EffectScope[] = [];
beforeEach(() => {
    mocks.queryRows.clear();
    mocks.loadQuery.mockReset();
    mocks.loadQuery.mockResolvedValue([]);
    clearCompanyShares();
    markCompanyShareRegistryReady();
});
afterEach(() => { scopes.splice(0).forEach((scope) => scope.stop()); clearCompanyShares(); });

/** Build a reciprocal synthetic pair whose IDs can be reused in a different owner or company partition. */
function pair(index = 0, ownerId = 'owner-a', companyId = 'company-a') {
    const expense = { id: `expense-${index}`, ownerId, companyId, status: 'recorded', paidAt: null, cancelledAt: null,
        internalNumber: `E${index}`, total: '19.00', currency: 'EUR', variableSymbol: '0019', externalNumber: 'INV-0019' } as unknown as EvoluExpenseRow;
    const transaction = { id: `tx-${index}`, ownerId, companyId, direction: 'debit', source: 'csv',
        amount: '19.00', currency: 'EUR', variableSymbol: '0019', reference: 'INV-0019', counterpartyName: null,
        bookedAt: '2026-11-05', businessExpenseId: expense.id, matchStatus: 'matched' } as unknown as EvoluBankTransactionRow;
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = createBankExpenseClaim(transaction, expense, 'manual');
    return { expense, transaction };
}

/** Count deterministic property work while retaining the exact production row values and array semantics. */
function counted<T extends object>(row: T, property: keyof T, read: () => void): T {
    return new Proxy(row, { /** Observe only the selected property, preserving every underlying row read. */ get(target, key, receiver) {
        if (key === property) read();
        return Reflect.get(target, key, receiver);
    } });
}

/** Supply all raw relationships to the actual mapper without involving a worker or network request. */
function context(transactions: EvoluBankTransactionRow[], expenses: EvoluExpenseRow[] = [], matches: EvoluBankTransactionMatchRow[] = [], documents: EvoluDocumentRow[] = []): BankPaymentContext {
    return { transactions, expenses, matches, documents, batches: [] };
}

describe('bounded canonical expense and payment projections', () => {
    it.each([256, 512])('indexes transaction and invoice evidence once for %i expense projections', (size) => {
        const pairs = Array.from({ length: size }, (_, index) => pair(index));
        let transactionReads = 0;
        let invoiceReads = 0;
        const transactions = pairs.map(({ transaction }) => counted(transaction, 'id', () => transactionReads++));
        const matches = pairs.map((_, index) => counted({ id: `match-${index}`, bankTransactionId: `unrelated-${index}`, ownerId: 'owner-a' } as unknown as EvoluBankTransactionMatchRow,
            'bankTransactionId', () => invoiceReads++));
        const expenses = pairs.map(({ expense }) => expense);
        const projected = projectExpensePayments(expenses, transactions, matches);
        expect(projected.every((row) => row.status === 'paid')).toBe(true);
        expect(expenses.every((row) => row.status === 'recorded' && row.paidAt === null)).toBe(true);
        expect(transactionReads).toBeLessThanOrEqual(size * 5);
        expect(invoiceReads).toBeLessThanOrEqual(size * 2);
    });

    it('preserves unindexed scope, duplicate, malformed and invoice-wildcard outcomes', () => {
        const a = pair();
        const foreignOwner = pair(0, 'owner-b');
        const foreignCompany = pair(0, 'owner-a', 'company-b');
        const transactions = [a.transaction, foreignOwner.transaction, foreignCompany.transaction];
        const scenarios = [
            [],
            [{ bankTransactionId: 'tx-0', ownerId: 'owner-b' }],
            [{ bankTransactionId: 'tx-0', ownerId: null }],
            [{ bankTransactionId: 'tx-0' }],
            [{ bankTransactionId: 'tx-0', ownerId: 'owner-a', isDeleted: 1 }],
        ] as unknown as EvoluBankTransactionMatchRow[][];
        for (const matches of scenarios) {
            const index = createBankExpensePaymentIndex(transactions, matches);
            expect(effectiveExpensePayment(a.expense, transactions, matches, index)).toEqual(effectiveExpensePayment(a.expense, transactions, matches));
        }
        expect(projectExpensePayments([a.expense, foreignOwner.expense, foreignCompany.expense], transactions).map((row) => row.status)).toEqual(['paid', 'paid', 'paid']);
        expect(projectExpensePayments([a.expense], [...transactions, { ...a.transaction }])[0]).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
        expect(projectExpensePayments([{ ...a.expense, bankPaymentClaimJson: '{' }], transactions)[0]).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
        const missingOwner = { ...a.transaction };
        Reflect.deleteProperty(missingOwner, 'ownerId');
        expect(projectExpensePayments([a.expense], [missingOwner])[0]).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
    });

    it('does not reuse an index for different query snapshots', () => {
        const { expense, transaction } = pair();
        const transactions = [transaction];
        const matches: EvoluBankTransactionMatchRow[] = [];
        const index = createBankExpensePaymentIndex(transactions, matches);
        const newMatches = [{ bankTransactionId: transaction.id }] as unknown as EvoluBankTransactionMatchRow[];
        expect(effectiveExpensePayment(expense, transactions, newMatches, index)).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
        expect(effectiveExpensePayment(expense, [...transactions, { ...transaction }], matches, index)).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
    });

    it.each([256, 512])('filters before mapping and bounds relationship work for %i unrelated rows', (size) => {
        let documentReads = 0;
        let expenseReads = 0;
        let referenceReads = 0;
        const oldRows = Array.from({ length: size }, (_, index) => ({ ...pair(index).transaction, bankPaymentClaimJson: null, businessExpenseId: null }));
        const visibleRows = Array.from({ length: 60 }, (_, index) => ({ ...pair(index + size).transaction, bankPaymentClaimJson: null, businessExpenseId: null, matchStatus: 'unmatched' as const }));
        const transactions = [...oldRows, ...visibleRows].map((row) => counted(row, 'reference', () => referenceReads++));
        const expenses = oldRows.map((_, index) => counted(pair(index).expense, 'id', () => expenseReads++));
        const documents = oldRows.map((_, index) => counted({ id: `document-${index}`, ownerId: 'owner-a', companyId: 'company-a' } as unknown as EvoluDocumentRow, 'id', () => documentReads++));
        const matches = oldRows.map((row, index) => ({ bankTransactionId: row.id, ownerId: 'owner-a', businessDocumentId: documents[index]!.id } as unknown as EvoluBankTransactionMatchRow));
        documentReads = 0;
        const ctx = context(transactions, expenses, matches, documents);
        const projected = mapLocalBankTransactionsToApi(transactions, ctx, { matchStatus: 'unmatched', limit: 50 });
        expect(projected.map((row) => row.id)).toEqual(visibleRows.slice(0, 50).map((row) => row.id));
        expect(documentReads).toBeLessThanOrEqual(size + 50);
        expect(expenseReads).toBeLessThanOrEqual(size + 50);
        expect(referenceReads).toBeLessThanOrEqual(200);
    });

    it('filters effective conflicts before the limit and never exposes them as raw matched payments', () => {
        const pairs = Array.from({ length: 70 }, (_, index) => pair(index));
        const transactions = pairs.map(({ transaction }, index) => index < 60 ? transaction : { ...transaction, amount: '20.00' });
        const ctx = context(transactions, pairs.map(({ expense }) => expense));
        const unmatched = mapLocalBankTransactionsToApi(transactions, ctx, { matchStatus: 'unmatched', limit: 5 });
        expect(unmatched.map((row) => row.id)).toEqual(transactions.slice(60, 65).map((row) => row.id));
        expect(unmatched.every((row) => row.bank_match_conflict && !row.expense)).toBe(true);
        expect(mapLocalBankTransactionsToApi(transactions, ctx, { matchStatus: 'matched', limit: 100 })).toHaveLength(60);
        expect(mapLocalBankTransactionsToApi(transactions, ctx, { limit: 0 })).toEqual([]);
    });

    it('keeps ambiguous transactions and scoped or wildcard invoice reservations fail closed in bank rows', () => {
        const { expense, transaction } = pair();
        expect(mapLocalBankTransactionsToApi([transaction], context([transaction, { ...transaction }], [expense]))[0])
            .toMatchObject({ match_status: 'unmatched', bank_match_conflict: true });
        expect(mapLocalBankTransactionsToApi([transaction], context([transaction], [expense], [{ bankTransactionId: transaction.id }] as unknown as EvoluBankTransactionMatchRow[]))[0])
            .toMatchObject({ match_status: 'unmatched', bank_match_conflict: true });
        const foreign = pair(0, 'owner-b');
        const ctx = context([transaction, foreign.transaction], [foreign.expense, expense]);
        expect(mapLocalBankTransactionsToApi([transaction], ctx)[0]).toMatchObject({ match_status: 'matched', expense: { id: expense.id, status: 'paid' } });
        expect(mapLocalBankTransactionsToApi([transaction], context([transaction], [expense, { ...expense }]))[0])
            .toMatchObject({ match_status: 'unmatched', bank_match_conflict: true });
    });

    it.each([256, 512])('uses one bank index in the actual expense list and accountant consumers at %i rows', async (size) => {
        const pairs = Array.from({ length: size }, (_, index) => pair(index));
        let reads = 0;
        const transactions = shallowRef(pairs.map(({ transaction }) => counted(transaction, 'id', () => reads++)));
        const expenses = pairs.map(({ expense }) => ({ ...expense, issueDate: '2026-11-01' }));
        mocks.queryRows.set('transactions', transactions);
        mocks.queryRows.set('expenses', shallowRef(expenses));
        mocks.queryRows.set('matches', shallowRef([]));
        const scope = effectScope(); scopes.push(scope);
        const list = scope.run(() => useInvoicingExpenses(ref('company-a')))!;
        expect(list.expenseRows.value.every((row) => row.status === 'paid')).toBe(true);
        expect(reads).toBeLessThanOrEqual(size * 5);
        reads = 0;
        const accounting = scope.run(() => useAccountantExport(ref('company-a'), ref('Synthetic company')))!;
        accounting.period.value = { preset: 'custom', customFrom: '2026-11-01', customTo: '2026-11-30' };
        expect(accounting.plan.value?.expenses).toBe(size);
        expect(reads).toBeLessThanOrEqual(size * 5);
        const detail = scope.run(() => useInvoicingExpense(ref('company-a'), ref('expense-0')))!;
        expect(detail.expense.value?.status).toBe('paid');
        transactions.value = transactions.value.map((row, index) => index === 0 ? { ...row, amount: '20.00' } : row);
        await nextTick();
        expect(detail.expense.value).toMatchObject({ status: 'recorded', bank_match_conflict: true });
        expect(list.expenseRows.value[0]).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
        expect(expenses.every((row) => row.status === 'recorded' && row.paidAt === null)).toBe(true);
    });

    it('the actual payment hook filters canonical conflicts before limiting its visible rows', async () => {
        const pairs = Array.from({ length: 70 }, (_, index) => pair(index));
        const transactions = pairs.map(({ transaction }, index) => index < 60 ? transaction : { ...transaction, amount: '20.00' });
        mocks.queryRows.set('transactions', shallowRef(transactions));
        mocks.queryRows.set('expenses', shallowRef(pairs.map(({ expense }) => expense)));
        const scope = effectScope(); scopes.push(scope);
        const bank = scope.run(() => useInvoicingBankPayments(ref('company-a'), ref('EUR')))!;
        expect(bank.transactions.value).toHaveLength(50);
        await bank.load('unmatched');
        expect(bank.transactions.value.map((row) => row.id)).toEqual(transactions.slice(60).map((row) => row.id));
        expect(bank.transactions.value.every((row) => row.bank_match_conflict)).toBe(true);
        await bank.load('matched');
        expect(bank.transactions.value).toHaveLength(50);
        expect(bank.transactions.value.every((row) => !row.bank_match_conflict && row.match_status === 'matched')).toBe(true);
    });
});
