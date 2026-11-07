import { createConsole, createRandom, createRandomBytes, createTime } from "@evolu/common";
import { createDbWorkerForPlatform } from "@evolu/common/local-first";
import { createWasmSqliteDriver, wrapWebWorkerSelf } from "@evolu/web";
import { createObservedRelayWebSockets } from "./relayConnectionStatusWorker";

// Match the installed public web worker construction and observe only its socket factory.
const observed = createObservedRelayWebSockets();
const dbWorker = createDbWorkerForPlatform({
    console: createConsole(),
    createSqliteDriver: createWasmSqliteDriver,
    createWebSocket: observed.createWebSocket,
    random: createRandom(),
    randomBytes: createRandomBytes(),
    time: createTime(),
});

wrapWebWorkerSelf(dbWorker);
