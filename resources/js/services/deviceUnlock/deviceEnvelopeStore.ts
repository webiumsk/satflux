import type { DeviceEnvelope } from "./envelope";

/**
 * Persists the device unlock envelope in IndexedDB (one record). Only the
 * versioned ENCRYPTED envelope lives here - never the plaintext phrase, KEK or
 * DEK. Raw IndexedDB (no extra dependency); a single object store, single key.
 */

const DB_NAME = "satflux-device-unlock";
const DB_VERSION = 1;
const STORE = "envelope";
const RECORD_KEY = "current";

function idbAvailable(): boolean {
    return typeof indexedDB !== "undefined";
}

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE);
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function readFromStore<T>(fn: (store: IDBObjectStore) => IDBRequest): Promise<T> {
    return openDb().then(
        (db) =>
            new Promise<T>((resolve, reject) => {
                const tx = db.transaction(STORE, "readonly");
                const request = fn(tx.objectStore(STORE));
                request.onsuccess = () => resolve(request.result as T);
                request.onerror = () => reject(request.error);
                tx.oncomplete = () => db.close();
                tx.onabort = tx.onerror = () => {
                    db.close();
                    reject(tx.error);
                };
            }),
    );
}

/** Writes resolve on tx.oncomplete so a failed commit is reported, not silently lost. */
function writeToStore(fn: (store: IDBObjectStore) => void): Promise<void> {
    return openDb().then(
        (db) =>
            new Promise<void>((resolve, reject) => {
                const tx = db.transaction(STORE, "readwrite");
                fn(tx.objectStore(STORE));
                tx.oncomplete = () => {
                    db.close();
                    resolve();
                };
                tx.onabort = tx.onerror = () => {
                    db.close();
                    reject(tx.error);
                };
            }),
    );
}

export async function loadDeviceEnvelope(): Promise<DeviceEnvelope | null> {
    if (!idbAvailable()) return null;
    try {
        const value = await readFromStore<DeviceEnvelope | undefined>((store) => store.get(RECORD_KEY));
        return value ?? null;
    } catch {
        return null;
    }
}

export async function saveDeviceEnvelope(envelope: DeviceEnvelope): Promise<void> {
    if (!idbAvailable()) return;
    await writeToStore((store) => { store.put(envelope, RECORD_KEY); });
}

/** "Forget this device": remove the whole encrypted envelope. Idempotent. */
export async function clearDeviceEnvelope(): Promise<void> {
    if (!idbAvailable()) return;
    try {
        await writeToStore((store) => { store.delete(RECORD_KEY); });
    } catch {
        // Nothing to remove / storage unavailable.
    }
}

export async function hasDeviceEnvelope(): Promise<boolean> {
    return (await loadDeviceEnvelope()) !== null;
}
