import { afterEach, expect, it, vi } from 'vitest';
import { createRandomBytes } from '@evolu/common';
import { createAppOwner, createOwnerSecret, createSharedOwner, type Evolu, type SyncOwner } from '@evolu/common/local-first';
import { deriveRecoveryPublicKeyHex } from '../services/accountSeed';
import type { InvoicingLocalSchema } from '../evolu/schema';
import { registerEvoluRelayOwner, reconcileEvoluRelayPreference } from '../evolu/relayPreferenceLifecycle';
import { writeRelayOverrideRelays } from '../evolu/relayOverrideStorage';

const releases: (() => void)[] = [];
afterEach(() => { releases.splice(0).forEach(release => release()); localStorage.clear(); vi.unstubAllEnvs(); });

it('keeps retained owners local before authentication when the relay cache is absent', async () => {
  localStorage.clear();
  vi.stubEnv('VITE_EVOLU_RELAY_URL', 'wss://evolu.satflux.io');
  const randomBytes = createRandomBytes();
  const owner = createAppOwner(createOwnerSecret({ randomBytes }));
  const shared = createSharedOwner(createOwnerSecret({ randomBytes }));
  const active = new Map<symbol, SyncOwner>();
  const evolu = {
    appOwner: Promise.resolve(owner),
    useOwner: (value: SyncOwner) => {
      const token = Symbol(); active.set(token, value);
      return () => { active.delete(token); };
    },
  } as unknown as Evolu<InvoicingLocalSchema>;
  releases.push(registerEvoluRelayOwner(evolu, owner), registerEvoluRelayOwner(evolu, shared));
  await Promise.resolve();
  const destinations = () => [...active.values()].flatMap(row => row.transports ?? []).map(row => new URL(row.url).origin);
  expect(destinations()).toEqual([]);
  const defaults = ['wss://evolu.satflux.io', 'wss://evolu.eu.freedomrelay.dev'];
  const publicKey = deriveRecoveryPublicKeyHex(owner.mnemonic!);
  writeRelayOverrideRelays(defaults.map(url => ({ url, enabled: true })), publicKey);
  reconcileEvoluRelayPreference(defaults, publicKey);
  expect([...new Set(destinations())]).toEqual(defaults);
});
