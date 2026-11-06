import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Evolu } from '@evolu/common/local-first';
import type { InvoicingLocalSchema } from '@/evolu/schema';
import { createCompanyShareSecret, sharedOwnerForRelay, sharedOwnerFromSecret } from '@/evolu/sharedOwner';
import { createSyncOwnerForRelay } from '@/evolu/evoluRelayOwner';
import { evoluTransports, normalizeCustomEvoluRelayUrl, resolveEvoluRelayUrls } from '@/evolu/config';
import { getResolvedEvoluRelayUrl, getResolvedEvoluRelayUrls } from '@/services/evoluRelayPreference';
import { readRelayOverride, writeRelayOverrideDisabled, writeRelayOverrideUrl } from '@/evolu/relayOverrideStorage';

const auth = vi.hoisted(() => ({ user: null as null | { evolu_relay_url: string | null; evolu_relay_add_default?: boolean } }));
vi.mock('@/store/auth', () => ({ useAuthStore: () => auth }));

beforeEach(() => {
    localStorage.clear();
    auth.user = null;
    vi.stubEnv('VITE_EVOLU_RELAY_URL', 'wss://default.example.test');
});
afterEach(() => { vi.unstubAllEnvs(); localStorage.clear(); });

const both = ['wss://custom.example.test', 'wss://default.example.test'];

describe('multiple Evolu relays', () => {
    it('boots both relays from cache while preserving the old replacement representation', () => {
        writeRelayOverrideUrl(both[0], true);
        expect(readRelayOverride()).toEqual({ kind: 'url', url: both[0], addDefault: true });
        expect(evoluTransports().map(t => t.url)).toEqual(both);
        expect(getResolvedEvoluRelayUrls()).toEqual(both);
        writeRelayOverrideUrl(both[0]);
        expect(evoluTransports().map(t => t.url)).toEqual([both[0]]);
    });

    it('deduplicates identical origins and respects disabled build defaults', () => {
        expect(resolveEvoluRelayUrls('wss://default.example.test/', true)).toEqual([both[1]]);
        vi.stubEnv('VITE_EVOLU_RELAY_URL', '');
        expect(resolveEvoluRelayUrls(both[0], true)).toEqual([both[0]]);
        expect(resolveEvoluRelayUrls(null, true)).toEqual([]);
    });

    it('uses canonical authenticated settings even when the previous account cache differs', () => {
        writeRelayOverrideUrl(both[0], true);
        auth.user = { evolu_relay_url: null };
        expect(getResolvedEvoluRelayUrls()).toEqual([both[1]]);
        expect(getResolvedEvoluRelayUrl()).toBe(both[1]);
        auth.user = { evolu_relay_url: both[0], evolu_relay_add_default: false };
        expect(getResolvedEvoluRelayUrls()).toEqual([both[0]]);
    });

    it('attaches both transports in one app and shared owner without changing keys', async () => {
        auth.user = { evolu_relay_url: both[0], evolu_relay_add_default: true };
        const owner = sharedOwnerFromSecret(createCompanyShareSecret());
        const evolu = { appOwner: Promise.resolve(owner) } as unknown as Evolu<InvoicingLocalSchema>;
        for (const sync of [await createSyncOwnerForRelay(evolu), sharedOwnerForRelay(owner)]) {
            expect(sync.transports).toEqual(both.map(url => ({ type: 'WebSocket', url: `${url}?ownerId=${owner.id}` })));
            expect(sync.id).toBe(owner.id);
            expect(sync.encryptionKey).toBe(owner.encryptionKey);
            expect(sync.writeKey).toBe(owner.writeKey);
        }
    });

    it('disables every transport explicitly for both owner types despite a saved profile', async () => {
        auth.user = { evolu_relay_url: both[0], evolu_relay_add_default: true };
        writeRelayOverrideDisabled();
        const owner = sharedOwnerFromSecret(createCompanyShareSecret());
        const evolu = { appOwner: Promise.resolve(owner) } as unknown as Evolu<InvoicingLocalSchema>;
        expect(evoluTransports()).toEqual([]);
        expect(getResolvedEvoluRelayUrls()).toEqual([]);
        expect((await createSyncOwnerForRelay(evolu)).transports).toEqual([]);
        expect(sharedOwnerForRelay(owner).transports).toEqual([]);
    });
});

describe('custom relay address validation', () => {
    it('accepts secure origins and normalizes an optional root slash', () => {
        expect(normalizeCustomEvoluRelayUrl(' wss://Relay.Example.test:8443/ ')).toBe('wss://relay.example.test:8443');
    });
    it.each(['https://relay.test', 'ws://relay.test', 'wss://u:p@relay.test', 'wss://relay.test/path', 'wss://relay.test/path/..', 'wss://relay.test/./', 'wss://0x7f000001', 'wss://127.1', 'wss://relay.test:0',
        'wss://relay.test?token=secret', 'wss://relay.test#fragment', 'wss://relay.test?', 'wss://relay.test#',
        'wss://relay.test\\path', 'wss://rel\nay.test', 'wss://', 'invalid'])('rejects unsafe or retargeted input %s', value => {
        expect(normalizeCustomEvoluRelayUrl(value)).toBe('');
    });
});
