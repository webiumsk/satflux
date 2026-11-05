import { Worker } from 'node:worker_threads';
import { DatabaseSync } from 'node:sqlite';
import { createConsole, createEvolu, createId, createRandom, createRandomBytes, createTime, SimpleName } from '@evolu/common';
import { createAppOwner, createOwnerSecret, createBaseSqliteStorageTables, createRelayStorageTables, createRelaySqliteStorage, applyProtocolMessageAsRelay } from '@evolu/common/local-first';
import { createSqlite, type SqliteQuery } from '@evolu/common';
import { InvoicingLocalSchema } from '@/evolu/schema';
import { withCompanyOwnerScoping } from '@/evolu/ownerScope';

let sequence = 0;
type Envelope = { response?: unknown; protocol?: Uint8Array };

/** Genuine encrypted protocol exchange, through Evolu's reference relay storage, between independent SQLite replicas. */
export async function createExpenseReplicaPair() {
  const deps = { console: createConsole({ enableLogging: false }), randomBytes: createRandomBytes(), random: createRandom(), time: createTime() };
  const appOwner = createAppOwner(createOwnerSecret(deps));
  const database = new DatabaseSync(':memory:');
  const sqliteResult = await createSqlite({ createSqliteDriver: async () => ({
    exec: (query: SqliteQuery, mutation: boolean) => {
      if (mutation && query.parameters.length === 0) { database.exec(query.sql); return { rows: [], changes: 0 }; }
      const statement = database.prepare(query.sql);
      const parameters = query.parameters.map((value) => value instanceof Uint8Array ? Buffer.from(value) : value);
      if (mutation) { const result = statement.run(...parameters); return { rows: [], changes: Number(result.changes) }; }
      return { rows: statement.all(...parameters), changes: 0 };
    }, export: () => new Uint8Array(), [Symbol.dispose]: () => database.close(),
  }) as never })(SimpleName.orThrow(`ExpenseRelay${++sequence}`));
  if (!sqliteResult.ok) throw new Error('synthetic_relay_initialization_failed');
  const sqlite = sqliteResult.value;
  if (!createBaseSqliteStorageTables({ sqlite }).ok || !createRelayStorageTables({ sqlite }).ok) throw new Error('synthetic_relay_schema_failed');
  const storage = createRelaySqliteStorage({ sqlite, random: deps.random,
    timingSafeEqual: (a, b) => a.length === b.length && a.every((value, index) => value === b[index]),
  })({ isOwnerWithinQuota: () => true, onStorageError: () => { throw new Error('synthetic_relay_storage_failed'); } });
  const apply = applyProtocolMessageAsRelay({ storage });
  const queue: Array<{ sender: number; message: Uint8Array }> = [];
  const workers: Worker[] = [];
  const online = [false, false];
  const protocolErrors: unknown[] = [];
  const clients = [0, 1].map((index) => {
    const worker = new Worker(new URL('./expenseReplicaWorker.mjs', import.meta.url));
    workers.push(worker);
    let receive: ((message: unknown) => void) | undefined;
    worker.on('message', (envelope: Envelope) => {
      if (envelope.protocol) queue.push({ sender: index, message: new Uint8Array(envelope.protocol) });
      else {
        if ((envelope.response as { type?: string })?.type === 'onError') protocolErrors.push(envelope.response);
        receive?.(envelope.response);
      }
    });
    worker.on('error', (error) => { protocolErrors.push(error); });
    return withCompanyOwnerScoping(createEvolu({ ...deps, reloadApp: () => {}, createDbWorker: () => ({
      postMessage: (message: unknown) => worker.postMessage(structuredClone(message)),
      onMessage: (listener: (message: unknown) => void) => { receive = listener; },
    }) } as never)(InvoicingLocalSchema, {
      name: SimpleName.orThrow(`ExpenseReplica${sequence}-${index}`), externalAppOwner: appOwner,
      transports: [{ type: 'WebSocket', url: 'wss://synthetic.invalid' }], inMemory: true,
    }));
  });
  const setOnline = (index: number, value: boolean) => { online[index] = value; workers[index]!.postMessage({ control: 'online', value }); };
  const stateQueries = ['company', 'expense', 'bankTransaction', 'bankTransactionMatch', 'document', 'bankImportBatch'].map((table) =>
    clients[0]!.createQuery((db) => db.selectFrom(table as 'expense').selectAll()));
  const readState = async (index: number) => JSON.stringify(await Promise.all(stateQueries.map(async (query) => {
    const rows = await clients[index]!.loadQuery(query);
    return rows.map((row) => Object.fromEntries(Object.entries(row).filter(([key]) => !['createdAt', 'updatedAt'].includes(key)).sort(([a], [b]) => a.localeCompare(b))))
      .sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b)));
  })));
  const deliver = async () => {
    while (queue.length) {
      const item = queue.shift()!;
      if (!online[item.sender]) continue;
      const result = await apply(item.message as never, {
        broadcast: (_owner, message) => workers.forEach((worker, receiver) => {
          if (online[receiver]) worker.postMessage({ control: 'protocol', data: new Uint8Array(message) });
        }),
      });
      if (!result.ok) throw new Error('synthetic_protocol_exchange_failed');
      if (online[item.sender]) workers[item.sender]!.postMessage({ control: 'protocol', data: new Uint8Array(result.value.message) });
    }
  };
  const synchronize = async (order: readonly number[] = [0, 1]) => {
    let idle = 0;
    for (const index of order) {
      setOnline(index, true);
      idle = 0;
      for (let round = 0; round < 300 && idle < 8; round++) {
        await new Promise((resolve) => setTimeout(resolve, 5));
        if (queue.length === 0) { idle++; continue; }
        idle = 0;
        await deliver();
      }
    }
    // Relay-idle is not receiver-applied: require actual independent SQLite reads to agree.
    const deadline = Date.now() + 5000;
    let equalReads = 0;
    while (equalReads < 2 && Date.now() < deadline) {
      await deliver();
      const states = await Promise.all([readState(0), readState(1)]);
      equalReads = states[0] === states[1] && queue.length === 0 ? equalReads + 1 : 0;
      if (equalReads < 2) await new Promise((resolve) => setTimeout(resolve, 5));
    }
    if (equalReads < 2) throw new Error('synthetic_replica_receiver_not_converged');
    if (protocolErrors.length || queue.length) throw new Error('synthetic_replica_sync_failed');
  };
  const mutate = (index: number, table: string, patch: Record<string, unknown>, options: { ownerId?: string } = {}) => new Promise<void>((resolve, reject) => {
    const client = clients[index]!;
    let stop = () => {};
    const timer = setTimeout(() => { stop(); reject(new Error(`synthetic_replica_seed_timed_out:${table}:${client.getError()?.type ?? 'no-worker-error'}`)); }, 5000);
    const done = () => { clearTimeout(timer); stop(); resolve(); };
    stop = client.subscribeError(() => { if (client.getError()) { clearTimeout(timer); stop(); reject(new Error(`synthetic_replica_seed_worker_failed:${table}:${client.getError()?.type}`)); } });
    const result = client.upsert(table as never, patch as never, { ...options, onComplete: done } as never);
    if (!result.ok) { clearTimeout(timer); stop(); reject(new Error(`synthetic_replica_seed_failed:${table}`)); }
  });
  const close = async () => { await Promise.all(workers.map((worker) => worker.terminate())); sqlite[Symbol.dispose](); };
  try { await Promise.all(clients.map((client) => client.appOwner)); }
  catch (error) { await close(); throw error; }
  return { clients, mutate, synchronize, disconnect: () => { setOnline(0, false); setOnline(1, false); },
    newId: () => createId(deps), appOwnerId: appOwner.id, close };
}
