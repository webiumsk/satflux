import { parentPort } from 'node:worker_threads';
import { DatabaseSync } from 'node:sqlite';
import { createConsole, createRandom, createRandomBytes, createTime } from '@evolu/common';

/** Each fixture worker owns a separate memory-only SQLite database and real Evolu sync/CRDT implementation. */
async function initialize() {
  const { createDbWorkerForPlatform } = await import(new URL('./local-first/Db.js', import.meta.resolve('@evolu/common')));
  const sqlite = new DatabaseSync(':memory:');
  let online = false;
  let handlers;
  const worker = createDbWorkerForPlatform({
    console: createConsole({ enableLogging: false }), randomBytes: createRandomBytes(), random: createRandom(), time: createTime(),
    createWebSocket: (_url, options) => {
      handlers = options;
      if (online) queueMicrotask(() => options.onOpen());
      return {
        isOpen: () => online,
        send: (data) => { if (online) parentPort.postMessage({ protocol: new Uint8Array(data) }); },
        [Symbol.dispose]: () => { handlers = undefined; },
      };
    },
    createSqliteDriver: async () => ({
      exec: (query, mutation) => {
        if (mutation && query.parameters.length === 0) { sqlite.exec(query.sql); return { rows: [], changes: 0 }; }
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
    if (message.control === 'online') { online = message.value; if (online) handlers?.onOpen(); return; }
    if (message.control === 'protocol') {
      if (online) handlers?.onMessage(Uint8Array.from(message.data).buffer);
      return;
    }
    worker.postMessage(message);
  });
}

void initialize();
