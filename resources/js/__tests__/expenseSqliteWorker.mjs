import { parentPort } from 'node:worker_threads';
import { DatabaseSync } from 'node:sqlite';
import { createConsole, createRandom, createRandomBytes, createTime } from '@evolu/common';

/** Bootstrap the exact installed worker while retaining compatibility with the repository's ESLint parser. */
async function initializeWorker() {
  // Use the exact installed Evolu worker implementation and a synthetic, memory-only database.
  const { createDbWorkerForPlatform } = await import(new URL('./local-first/Db.js', import.meta.resolve('@evolu/common')));
  const sqlite = new DatabaseSync(':memory:');
  let failNextCommit = false;
  let driftNextCommit;
  const worker = createDbWorkerForPlatform({
    console: createConsole({ enableLogging: false }), randomBytes: createRandomBytes(), random: createRandom(), time: createTime(),
    createWebSocket: () => { throw new Error('network_forbidden_in_expense_test'); },
    createSqliteDriver: async () => ({
      exec: (query, mutation) => {
        if (String(query.sql).trim().toLowerCase() === 'commit;' && failNextCommit) {
          failNextCommit = false;
          throw new Error('synthetic_expense_commit_failure');
        }
        if (mutation && query.parameters.length === 0) {
          sqlite.exec(query.sql);
          if (String(query.sql).trim().toLowerCase() === 'commit;' && driftNextCommit) {
            const { table, field, value } = driftNextCommit;
            driftNextCommit = undefined;
            sqlite.prepare(`update "${table}" set "${field}" = ?`).run(value);
          }
          return { rows: [], changes: 0 };
        }
        const statement = sqlite.prepare(query.sql);
        const parameters = query.parameters.map((value) => value instanceof Uint8Array ? Buffer.from(value) : value);
        if (mutation) { const result = statement.run(...parameters); return { rows: [], changes: Number(result.changes) }; }
        return { rows: statement.all(...parameters), changes: 0 };
      },
      export: () => new Uint8Array(), [Symbol.dispose]: () => {},
    }),
  });
  worker.onMessage((response) => parentPort.postMessage({ response }));
  parentPort.on('message', (message) => {
    if (message.control === 'failNextCommit') { failNextCommit = true; return; }
    if (message.control === 'driftNextCommit') {
      const allowed = { expense: ['total', 'currency', 'variableSymbol'], bankTransaction: ['amount', 'currency', 'variableSymbol', 'bookedAt', 'businessExpenseId'] };
      if (!allowed[message.table]?.includes(message.field)) throw new Error('invalid_synthetic_drift_field');
      driftNextCommit = message;
      return;
    }
    if (message.control === 'snapshot') {
      parentPort.postMessage({ snapshot: {
        expense: sqlite.prepare('select * from expense').all(),
        bankTransaction: sqlite.prepare('select * from bankTransaction').all(),
      } });
      return;
    }
    worker.postMessage(message);
  });
}

void initializeWorker();
