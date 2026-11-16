import { parentPort } from 'node:worker_threads';
import { DatabaseSync } from 'node:sqlite';
import { createConsole, createRandom, createRandomBytes, createSqlite, createTime, Id, idToIdBytes, SimpleName } from '@evolu/common';
import { getDbSchema, OwnerId, ownerIdToOwnerIdBytes, tryApplyQuarantinedMessages } from '@evolu/common/local-first';

/** Bootstrap the exact installed worker while retaining compatibility with the repository's ESLint parser. */
async function initializeWorker() {
  // Use the exact installed Evolu worker implementation and a synthetic, memory-only database.
  const { createDbWorkerForPlatform } = await import(new URL('./local-first/Db.js', import.meta.resolve('@evolu/common')));
  const sqlite = new DatabaseSync(':memory:');
  let failNextCommit = false;
  let driftNextCommit;
  let parkedArchiveHistory = [];
  const driver = {
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
    };
  const worker = createDbWorkerForPlatform({
    console: createConsole({ enableLogging: false }), randomBytes: createRandomBytes(), random: createRandom(), time: createTime(),
    createWebSocket: () => { throw new Error('network_forbidden_in_expense_test'); },
    createSqliteDriver: async () => driver,
  });
  const replaySqlite = await createSqlite({ createSqliteDriver: async () => driver })(SimpleName.orThrow('ArchiveReplay'));
  if (!replaySqlite.ok) throw new Error('synthetic_archive_sqlite_unavailable');
  worker.onMessage((response) => parentPort.postMessage({ response }));
  parentPort.on('message', (message) => {
    if (message.control === 'parkArchiveHistory') {
      const { ownerId, sourceId, chunkIds, includeSource } = message;
      if (!Array.isArray(chunkIds) || chunkIds.length > 40 || new Set(chunkIds).size !== chunkIds.length
        || typeof includeSource !== 'boolean') throw new Error('invalid_synthetic_archive_scope');
      const ownerBytes = ownerIdToOwnerIdBytes(OwnerId.orThrow(ownerId));
      const targets = [...(includeSource ? [{ table: 'migrationSource', id: sourceId }] : []),
        ...chunkIds.map(id => ({ table: 'migrationChunk', id }))];
      Id.orThrow(sourceId);
      parkedArchiveHistory = [];
      sqlite.exec('begin;');
      for (const { table, id } of targets) {
        const idBytes = idToIdBytes(Id.orThrow(id));
        parkedArchiveHistory.push(...sqlite.prepare('select ownerId, timestamp, "table", id, "column", value from evolu_history where ownerId = ? and "table" = ? and id = ?')
          .all(Buffer.from(ownerBytes), table, Buffer.from(idBytes)));
        sqlite.prepare(`delete from "${table}" where ownerId = ? and id = ?`).run(ownerId, id);
        sqlite.prepare('delete from evolu_history where ownerId = ? and "table" = ? and id = ?').run(Buffer.from(ownerBytes), table, Buffer.from(idBytes));
      }
      sqlite.exec('commit;');
      parentPort.postMessage({ archive: { parkedCells: parkedArchiveHistory.length } });
      return;
    }
    if (message.control === 'replayOlderArchive') {
      const olderThanDelete = parkedArchiveHistory.every(row => {
        const deletion = sqlite.prepare('select timestamp from evolu_history where ownerId = ? and "table" = ? and id = ? and "column" = ? order by timestamp desc limit 1')
          .get(row.ownerId, row.table, row.id, 'isDeleted');
        return deletion && Buffer.compare(Buffer.from(row.timestamp), Buffer.from(deletion.timestamp)) < 0;
      });
      const insert = sqlite.prepare('insert or ignore into evolu_message_quarantine (ownerId, timestamp, "table", id, "column", value) values (?, ?, ?, ?, ?, ?)');
      for (const row of parkedArchiveHistory) insert.run(row.ownerId, row.timestamp, row.table, row.id, row.column, row.value);
      const schema = getDbSchema({ sqlite: replaySqlite.value })();
      if (!schema.ok) throw new Error('synthetic_archive_schema_unavailable');
      const replay = replaySqlite.value.transaction(() => tryApplyQuarantinedMessages({ sqlite: replaySqlite.value, dbSchema: schema.value })());
      if (!replay.ok) throw new Error('synthetic_archive_replay_failed');
      parentPort.postMessage({ archive: { replayedCells: parkedArchiveHistory.length, olderThanDelete: Boolean(olderThanDelete) } });
      return;
    }
    if (message.control === 'archiveSnapshot') {
      parentPort.postMessage({ archive: Object.fromEntries(['company', 'expense', 'expenseAttachment', 'migrationSource', 'migrationChunk']
        .map(table => [table, sqlite.prepare(`select * from "${table}"`).all()])) });
      return;
    }
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
