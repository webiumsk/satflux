// @vitest-environment node
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createExpenseReplicaPair } from './expenseReplicaTestSupport';
import { installBankLocks } from './bankReconciliationTestSupport';
import { setCompanyShare, markCompanyShareRegistryReady, clearCompanyShares } from '@/evolu/companyShareRegistry';
import { clearRowOwnerIndex } from '@/evolu/ownerScope';
import { effectiveExpensePayment } from '@/evolu/bankExpenseClaims';

vi.mock('@/i18n', () => ({default:{global:{t:(key:string)=>key}}}));
vi.mock('@/evolu/client', async () => {
  const { createQuery } = await import('@evolu/common/local-first');
  const { sqliteTrue } = await import('@evolu/common');
  const tables = { BankImportBatches:'bankImportBatch', BankTransactions:'bankTransaction', BankTransactionMatches:'bankTransactionMatch',
    CompaniesDetail:'company', Companies:'company', CompanyShares:'companyShare', CompanyStockBalances:'companyStockBalance', CompanyStockItems:'companyStockItem',
    CompanyStockMovements:'companyStockMovement', CompanyWarehouses:'companyWarehouse', Contacts:'contact', DocumentEvents:'documentEvent', InvoiceTemplates:'invoiceTemplate',
    DocumentLines:'documentLine', DocumentSnapshots:'documentSnapshot', Documents:'document', Expenses:'expense', ExpenseAttachments:'expenseAttachment',
    NumberSeries:'numberSeries', RecurringProfileLines:'recurringProfileLine', RecurringProfiles:'recurringProfile',
    MigrationSourcesBackup:'migrationSource', MigrationChunks:'migrationChunk' };
  const {InvoicingLocalSchema}=await import('@/evolu/schema');
  const queries:Record<string,unknown> = {};
  for (const [suffix,table] of Object.entries(tables)) queries[`all${suffix}Query`] = createQuery(db => db.selectFrom(table).select([...Object.keys(InvoicingLocalSchema[table as keyof typeof InvoicingLocalSchema]),'ownerId']).where('isDeleted','is not',sqliteTrue));
  for (const [suffix,table] of Object.entries({Batches:'bankImportBatch',Transactions:'bankTransaction',Matches:'bankTransactionMatch',Documents:'document',Expenses:'expense'})) {
    queries[`bankReconciliation${suffix}Query`] = createQuery(db => db.selectFrom(table).selectAll().where('isDeleted','is not',sqliteTrue).where('id','is not',null));
  }
  return queries;
});
import { matchLocalBankTransactionToExpense } from '@/evolu/bankExpenseManualMatching';
import { loadBankPaymentContext, mapLocalBankTransactionsToApi } from '@/evolu/bankCrud';
import { snapshotInvoicingData, restoreInvoicingSnapshotDetailedAsync, type InvoicingDataSnapshot } from '@/evolu/invoicingSnapshot';

afterEach(()=>{clearCompanyShares();clearRowOwnerIndex();vi.unstubAllGlobals();});
/** Project real persisted rows without changing raw payment state. */
async function view(p:Awaited<ReturnType<typeof ownedFixture>>,i=0){
 const ctx=await loadBankPaymentContext(p.clients[i]!);
 return {ctx,expenses:ctx.expenses.map(e=>effectiveExpensePayment(e,ctx.transactions,ctx.matches)),txs:mapLocalBankTransactionsToApi(ctx.transactions,ctx)};
}
/** Confirm the selected pair through the production writer and fresh owner gate. */
async function match(p:Awaited<ReturnType<typeof ownedFixture>>,i:number,ti:number,ei:number){installBankLocks();await matchLocalBankTransactionToExpense(p.clients[i]!,p.company,p.ts[ti]!,p.es[ei]!);}

/** Change one persisted fixture row and await its real worker commit. */
async function patch(p: Awaited<ReturnType<typeof ownedFixture>>, i: number, table: string, values: Record<string, unknown>) {
  await new Promise<void>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('synthetic_patch_deadline')), 3000);
    const result = p.clients[i]!.update(table as never, values as never, {
      onComplete: () => { clearTimeout(timer); resolve(); },
    });
    if (!result.ok) { clearTimeout(timer); reject(new Error('synthetic_patch_validation_failed')); }
  });
}

import { createRandomBytes } from '@evolu/common';
import { createSharedOwner, createOwnerSecret } from '@evolu/common/local-first';
/** Seed a current private or registered shared owner on two independent SQLite clients. */
async function ownedFixture(shared:boolean){
 const p=await createExpenseReplicaPair();
 try{
  const company=p.newId(),es=[p.newId()],ts=[p.newId()],shareId=p.newId();
  const sharedOwner=shared?createSharedOwner(createOwnerSecret({randomBytes:createRandomBytes()})):null;
  if(sharedOwner){p.clients.forEach(c=>c.useOwner(sharedOwner));setCompanyShare({companyId:company,ownerId:sharedOwner.id,role:'owner',status:'active',bridgeCompanyId:null});}
  if(sharedOwner)await p.mutate(0,'companyShare',{id:shareId,companyId:company,sharedOwnerId:sharedOwner.id,secretB64:'synthetic-not-a-real-secret',role:'owner',status:'active',bridgeCompanyId:null});
  markCompanyShareRegistryReady();
  const options=sharedOwner?{ownerId:sharedOwner.id}:{};
  await p.mutate(0,'company',{id:company,legalName:'Synthetic native restore',jurisdiction:'eu_sk',defaultCurrency:'EUR',vatStatus:'none'},options);
  await p.mutate(0,'expense',{id:es[0],companyId:company,internalNumber:'RESTORE-QA',status:'recorded',paidAt:null,total:'19.00',currency:'EUR',variableSymbol:'0019',externalNumber:'INV-0019'},options);
  await p.mutate(0,'bankTransaction',{id:ts[0],companyId:company,source:'csv',direction:'debit',amount:'19.00',currency:'EUR',bookedAt:'2026-11-03T10:00:00Z',variableSymbol:'0019',reference:'INV-0019',matchStatus:'unmatched',businessExpenseId:null},options);
  await p.synchronize();p.disconnect();return {...p,company,es,ts,expectedOwner:sharedOwner?.id??p.appOwnerId,shareId};
 }catch(e){await p.close();throw e;}
}
describe('native restore current trusted owner contract',()=>{
 it.each([false,true])('restores raw claim in current registered target, shared=%j, ignoring foreign backup owner metadata',async(shared)=>{
  const p=await ownedFixture(shared);try{
   await match(p,0,0,0);await p.synchronize();const before=await view(p);const claim=before.ctx.expenses[0]!.bankPaymentClaimJson;
   const snap=await snapshotInvoicingData(p.clients[0]!);
   expect(snap.expense[0]).toMatchObject({ownerId:p.expectedOwner,status:'recorded',paidAt:null,bankPaymentClaimJson:claim});
   const foreign=createSharedOwner(createOwnerSecret({randomBytes:createRandomBytes()}));
   const untrusted=Object.fromEntries(Object.entries(snap).map(([table,rows])=>[table,rows.map(row=>({...row,ownerId:foreign.id}))]));
   await patch(p,0,'expense',{id:p.es[0],companyId:p.company,bankPaymentClaimJson:null});await patch(p,0,'bankTransaction',{id:p.ts[0],companyId:p.company,bankPaymentClaimJson:null});
   installBankLocks();const result=await restoreInvoicingSnapshotDetailedAsync(p.clients[0]!,untrusted as unknown as InvoicingDataSnapshot);expect(result.failed).toEqual([]);expect(result.upserted).toBe(3);
   await p.synchronize();
   for(const i of [0,1]){const after=await view(p,i);expect(after.ctx.expenses).toHaveLength(1);expect(after.ctx.transactions).toHaveLength(1);expect(after.ctx.expenses[0]).toMatchObject({ownerId:p.expectedOwner,status:'recorded',paidAt:null,bankPaymentClaimJson:claim});expect(after.ctx.transactions[0]).toMatchObject({ownerId:p.expectedOwner,bankPaymentClaimJson:claim});expect(after.expenses[0]).toMatchObject({status:'paid',bankMatchConflict:0});}
  }finally{await p.close();}
 },30000);
 it.each(['revoked','ambiguous'])('rejects fresh unavailable/ambiguous shared routing %s despite stale trusted registry',async(kind)=>{
  const p=await ownedFixture(true);try{
   await match(p,0,0,0);await p.synchronize();const before=await view(p);const claim=before.ctx.expenses[0]!.bankPaymentClaimJson;
   const snap=await snapshotInvoicingData(p.clients[0]!);const onlyExpense={...snap,company:[],bankTransaction:[]};
   if(kind==='revoked')await patch(p,0,'companyShare',{id:p.shareId,status:'revoked'});
   else await p.mutate(0,'companyShare',{id:p.newId(),companyId:p.company,sharedOwnerId:p.expectedOwner,secretB64:'synthetic-not-a-real-secret',role:'owner',status:'active',bridgeCompanyId:null});
   installBankLocks();await expect(restoreInvoicingSnapshotDetailedAsync(p.clients[0]!,onlyExpense)).rejects.toThrow(/expense_reconciliation_owner_unavailable|bank_reconciliation_outcome_unknown/);
   const after=await view(p);expect(after.ctx.expenses).toHaveLength(1);expect(after.ctx.expenses[0]).toMatchObject({ownerId:p.expectedOwner,status:'recorded',paidAt:null,bankPaymentClaimJson:claim});
  }finally{await p.close();}
 },30000);

});
