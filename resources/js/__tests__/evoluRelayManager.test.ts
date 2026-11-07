import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Ref } from 'vue';
import { createI18n } from 'vue-i18n';
import en from '../locales/en.json';
import type { User } from '../store/auth';
import EvoluRelayManager from '../components/account/EvoluRelayManager.vue';

const SATFLUX = 'wss://evolu.satflux.io';
const FREE = 'wss://evolu.eu.freedomrelay.dev';
const CUSTOM = 'wss://relay.example.test';
const mocks = vi.hoisted(() => ({
  user: null as User | null,
  put: vi.fn(), fetchUser: vi.fn(), success: vi.fn(), error: vi.fn(), cacheWriter: vi.fn(), reload: vi.fn(),
  protocols: undefined as Ref<Record<string, { state: string; observedAt: number }>> | undefined,
  connections: undefined as Ref<Record<string, string>> | undefined,
  online: undefined as Ref<boolean> | undefined,
  active: [] as string[], notifyActive: undefined as (() => void) | undefined,
}));
vi.mock('../store/auth', async () => {
  const { shallowRef } = await import('vue');
  const user = shallowRef(mocks.user);
  Object.defineProperty(mocks, 'user', { get: () => user.value, set: (value: User | null) => { user.value = value; }, configurable: true });
  return { useAuthStore: () => ({ get user() { return user.value; }, fetchUser: mocks.fetchUser }) };
});
vi.mock('../store/flash', () => ({ useFlashStore: () => ({ success: mocks.success, error: mocks.error }) }));
vi.mock('../services/api', () => ({ default: { put: mocks.put } }));
vi.mock('../evolu/relayOverrideStorage', async (importOriginal) => ({ ...await importOriginal<typeof import('../evolu/relayOverrideStorage')>(), writeRelayOverrideUrl: mocks.cacheWriter, writeRelayOverrideRelays: mocks.cacheWriter }));
vi.mock('../evolu/relayPreferenceLifecycle', () => ({
  getActiveEvoluRelayUrls: () => mocks.active,
  subscribeActiveEvoluRelayUrls: (listener: () => void) => { mocks.notifyActive = listener; return () => { mocks.notifyActive = undefined; }; },
}));
vi.mock('../composables/useEvoluRelayConnections', async () => {
  const { ref } = await import('vue');
  mocks.connections = ref({});
  mocks.protocols = ref({});
  return { useEvoluRelayConnections: () => ({ connections: mocks.connections, protocols: mocks.protocols, refresh: vi.fn() }) };
});
vi.mock('../composables/useOnlineStatus', async () => {
  const { ref } = await import('vue');
  mocks.online = ref(true);
  return { useOnlineStatus: () => mocks.online };
});

let wrapper: VueWrapper | undefined;
/** Mount the actual manager with synthetic account and socket evidence. */
async function render(deviceSyncDisabled = false): Promise<VueWrapper> {
  wrapper = mount(EvoluRelayManager, { attachTo: document.body, props: { deviceSyncDisabled }, global: { plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })] } });
  await flushPromises();
  return wrapper;
}
/** Select a visible semantic button by its complete label. */
function button(text: string) {
  const found = wrapper!.findAll('button').find(row => row.text().trim() === text);
  if (!found) throw new Error(`Missing button: ${text}`);
  return found;
}
/** Open the real add form and submit the supplied URL. */
async function add(url: string): Promise<void> {
  await button('＋ Add relay').trigger('click');
  await wrapper!.get('input[type="url"]').setValue(url);
  await wrapper!.get('form').trigger('submit');
  await flushPromises();
}
/** Open an existing row through its accessible name. */
async function detail(url: string): Promise<void> {
  await wrapper!.get(`button[aria-label="Open relay ${url}"]`).trigger('click');
  await flushPromises();
}

beforeEach(() => {
  vi.clearAllMocks();
  vi.stubEnv('VITE_EVOLU_RELAY_URL', SATFLUX);
  mocks.user = { id: 1, evolu_relay_url: null, evolu_relay_add_default: false, evolu_relays: null } as User;
  mocks.active = [SATFLUX, FREE];
  mocks.connections!.value = { [SATFLUX]: 'connected', [FREE]: 'connecting' };
  mocks.online!.value = true;
  mocks.protocols!.value = {};
  mocks.put.mockResolvedValue({ data: {} });
  mocks.fetchUser.mockImplementation(async () => {
    const payload = mocks.put.mock.lastCall?.[1] as { evolu_relays: User['evolu_relays'] };
    mocks.user = { ...mocks.user!, evolu_relays: payload.evolu_relays };
    return true;
  });
  const original = window;
  vi.stubGlobal('window', new Proxy(original, {
    /** Stub navigation without replacing browser event or storage behavior. */
    get(target, key) { return key === 'location' ? { reload: mocks.reload } : Reflect.get(target, key); },
  }));
});
afterEach(() => { wrapper?.unmount(); wrapper = undefined; vi.unstubAllGlobals(); vi.unstubAllEnvs(); document.body.innerHTML = ''; });

describe('Evolu relay manager', () => {
  it('shows both requested defaults with independent connection states', async () => {
    await render();
    expect(wrapper!.findAll('li')).toHaveLength(2);
    expect(wrapper!.text()).toContain(SATFLUX);
    expect(wrapper!.text()).toContain(FREE);
    expect(wrapper!.text()).toContain('Connected: 1 of 2 enabled relays');
    expect(wrapper!.findAll('li')[0].text()).toContain('Connected');
    expect(wrapper!.findAll('li')[1].text()).toContain('Connecting');
    expect(mocks.put).not.toHaveBeenCalled();
  });

  it('shows an open connection and its quota failure separately, including detail guidance and a current warning', async () => {
    mocks.protocols!.value = { [FREE]: { state: 'quota_exceeded', observedAt: 1000 } };
    await render();
    const row = wrapper!.findAll('li')[1];
    expect(row.text()).toContain('Connecting');
    expect(row.text()).toContain('Quota exceeded');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([true]);
    mocks.connections!.value = { ...mocks.connections!.value, [FREE]: 'connected' };
    await flushPromises();
    expect(row.text()).toContain('Connected');
    expect(row.text()).toContain('Quota exceeded');
    mocks.online!.value = false; await flushPromises();
    expect(row.text()).toContain('Disconnected');
    expect(row.text()).toContain('Quota exceeded');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([true]);
    mocks.online!.value = true; await flushPromises();
    await detail(FREE);
    expect(wrapper!.text()).toContain('Synchronization');
    expect(wrapper!.text()).toContain('Check the provider');
    mocks.protocols!.value = { [FREE]: { state: 'response_received', observedAt: 2000 } };
    await flushPromises();
    expect(wrapper!.text()).toContain('Response received');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([false]);
  });

  it('requires this tab’s active route even while offline and retains quota only for an authorized offline route', async () => {
    mocks.online!.value = false;
    mocks.active = [];
    mocks.protocols!.value = { [FREE]: { state: 'quota_exceeded', observedAt: 1000 } };
    await render();
    expect(wrapper!.text()).not.toContain('Quota exceeded');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([false]);
    mocks.active = [FREE]; mocks.notifyActive?.();
    await flushPromises();
    expect(wrapper!.text()).toContain('Quota exceeded');
    expect(wrapper!.text()).toContain('Disconnected');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([true]);
    mocks.active = []; mocks.notifyActive?.();
    await flushPromises();
    expect(wrapper!.text()).not.toContain('Quota exceeded');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([false]);
  });

  it('does not present a worker error for a disabled, paused or device-off route', async () => {
    mocks.protocols!.value = { [FREE]: { state: 'quota_exceeded', observedAt: 1000 } };
    await render(true);
    expect(wrapper!.text()).not.toContain('Quota exceeded');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([false]);
    await wrapper!.setProps({ deviceSyncDisabled: false });
    mocks.active = [SATFLUX]; mocks.notifyActive?.();
    await flushPromises();
    expect(wrapper!.text()).not.toContain('Quota exceeded');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([false]);
    mocks.active = [SATFLUX, FREE]; mocks.notifyActive?.();
    mocks.user = { ...mocks.user!, evolu_relays: [{ url: SATFLUX, enabled: true }, { url: FREE, enabled: false }] };
    await flushPromises();
    expect(wrapper!.text()).not.toContain('Quota exceeded');
    expect(wrapper!.emitted('sync-error')?.at(-1)).toEqual([false]);
  });

  it('keeps an explicitly stored former free relay unchanged and removable', async () => {
    const previous = 'wss://free.evoluhq.com';
    mocks.user = { ...mocks.user!, evolu_relays: [{ url: SATFLUX, enabled: true }, { url: previous, enabled: true }] };
    await render();
    expect(wrapper!.text()).toContain(previous);
    expect(wrapper!.text()).not.toContain(FREE);
    await detail(previous);
    expect(button('Remove relay').exists()).toBe(true);
    expect(mocks.put).not.toHaveBeenCalled();
  });

  it('adds a third relay without replacing either default and refreshes canonically before reloading', async () => {
    await render();
    await add(' wss://Relay.Example.test/ ');
    expect(mocks.put).toHaveBeenCalledWith('/user', { evolu_relays: [{ url: SATFLUX, enabled: true }, { url: FREE, enabled: true }, { url: CUSTOM, enabled: true }] });
    expect(mocks.fetchUser).toHaveBeenCalledOnce();
    expect(mocks.reload).toHaveBeenCalledOnce();
    expect(mocks.success).toHaveBeenCalledOnce();
    expect(mocks.cacheWriter).not.toHaveBeenCalled();
    expect(wrapper!.text()).toContain(CUSTOM);
    expect(mocks.fetchUser.mock.invocationCallOrder[0]).toBeLessThan(mocks.reload.mock.invocationCallOrder[0]);
  });

  it.each(['wss://EVOLU.SATFLUX.IO/', 'https://custom.test', 'wss://custom.test/path', 'wss://0x'])('rejects duplicate or malformed address %s without touching preferences', async url => {
    await render();
    await add(url);
    expect(mocks.put).not.toHaveBeenCalled();
    expect(wrapper!.find('[role="alert"]').exists()).toBe(true);
  });

  it('materializes a legacy HTTPS build default as its equivalent secure WebSocket origin', async () => {
    vi.stubEnv('VITE_EVOLU_RELAY_URL', 'https://relay.example.test:8443');
    await render();
    await add(CUSTOM);
    expect(mocks.put).toHaveBeenCalledWith('/user', { evolu_relays: [{ url: 'wss://relay.example.test:8443', enabled: true }, { url: FREE, enabled: true }, { url: CUSTOM, enabled: true }] });
  });

  it('keeps an insecure legacy default unchanged and explains why list edits require a secure default', async () => {
    vi.stubEnv('VITE_EVOLU_RELAY_URL', 'ws://relay.example.test:8080');
    await render();
    expect(wrapper!.text()).toContain('ws://relay.example.test:8080');
    await add(CUSTOM);
    expect(mocks.put).not.toHaveBeenCalled();
    expect(wrapper!.get('[role="alert"]').text()).toContain('Ask the administrator');
  });

  it('fails closed on malformed canonical rows rather than restoring public defaults', async () => {
    mocks.user = { ...mocks.user!, evolu_relays: [{ url: CUSTOM, enabled: true, extra: 'unexpected' }] as never };
    await render();
    expect(wrapper!.findAll('li')).toHaveLength(0);
    expect(mocks.put).not.toHaveBeenCalled();
  });

  it('keeps an old custom-only selection until the user explicitly adds defaults', async () => {
    mocks.user = { ...mocks.user!, evolu_relay_url: CUSTOM };
    await render();
    expect(wrapper!.findAll('li')).toHaveLength(1);
    await button('Add default relays').trigger('click');
    await flushPromises();
    expect(mocks.put).toHaveBeenCalledWith('/user', { evolu_relays: [{ url: CUSTOM, enabled: true }, { url: SATFLUX, enabled: true }, { url: FREE, enabled: true }] });
  });

  it('pauses one default in its detail without removing the row or disabling other relays', async () => {
    await render();
    await detail(FREE);
    expect(wrapper!.text()).toContain('Default relays stay in the list');
    expect(wrapper!.findAll('button').some(row => row.text() === 'Remove relay')).toBe(false);
    await wrapper!.get('input[type="checkbox"]').setValue(false);
    await flushPromises();
    expect(mocks.put).toHaveBeenCalledWith('/user', { evolu_relays: [{ url: SATFLUX, enabled: true }, { url: FREE, enabled: false }] });
    expect(wrapper!.text()).toContain('Turned off');
  });

  it('requires confirmation to remove only the selected custom row', async () => {
    mocks.user = { ...mocks.user!, evolu_relays: [{ url: SATFLUX, enabled: true }, { url: CUSTOM, enabled: true }, { url: FREE, enabled: false }] };
    await render();
    await detail(CUSTOM);
    await button('Remove relay').trigger('click');
    await flushPromises();
    const dialog = wrapper!.get('[role="dialog"]');
    expect(dialog.text()).toContain('Local data stays on your devices');
    expect(mocks.put).not.toHaveBeenCalled();
    await dialog.findAll('button').find(row => row.text() === 'Remove relay')!.trigger('click');
    await flushPromises();
    expect(mocks.put).toHaveBeenCalledWith('/user', { evolu_relays: [{ url: SATFLUX, enabled: true }, { url: FREE, enabled: false }] });
  });

  it('does not cache, reload or announce success if canonical refresh fails', async () => {
    mocks.fetchUser.mockResolvedValue(false);
    await render();
    await add(CUSTOM);
    expect(mocks.error).toHaveBeenCalledWith(en.account.evolu_relay_save_failed);
    expect(mocks.cacheWriter).not.toHaveBeenCalled();
    expect(mocks.reload).not.toHaveBeenCalled();
    expect(mocks.success).not.toHaveBeenCalled();
    expect(wrapper!.text()).not.toContain('Open relay ' + CUSTOM);
  });

  it('restores the visible checkbox after a failed save', async () => {
    mocks.put.mockRejectedValue(new Error('unavailable'));
    await render();
    await detail(FREE);
    await wrapper!.get('input[type="checkbox"]').setValue(false);
    await flushPromises();
    expect((wrapper!.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(true);
    expect(mocks.fetchUser).not.toHaveBeenCalled();
    expect(mocks.reload).not.toHaveBeenCalled();
  });

  it('ignores a save completion after an account switch away and back', async () => {
    let finish!: (value: unknown) => void;
    mocks.put.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
    await render();
    await add(CUSTOM);
    const account = mocks.user!;
    mocks.user = { ...account, id: 2 };
    mocks.user = account;
    finish({ data: {} });
    await flushPromises();
    expect(mocks.fetchUser).not.toHaveBeenCalled();
    expect(mocks.success).not.toHaveBeenCalled();
    expect(mocks.reload).not.toHaveBeenCalled();
  });

  it('shows unknown rather than connected when worker evidence expires and paused without an owner registration', async () => {
    await render();
    mocks.connections!.value = {};
    await flushPromises();
    expect(wrapper!.findAll('li')[0].text()).toContain('Unknown');
    mocks.active = [];
    mocks.notifyActive!();
    await flushPromises();
    expect(wrapper!.text()).toContain('Sync paused');
    expect(wrapper!.text()).toContain('Connected: 0 of 2 enabled relays');
  });

  it('does not present open worker sockets as connected when device sync is off', async () => {
    await render(true);
    expect(wrapper!.text()).toContain('Device sync off');
    expect(wrapper!.text()).toContain('Connected: 0 of 2 enabled relays');
  });

  it('keeps an explicit empty list empty and offers the defaults without adding them automatically', async () => {
    mocks.user = { ...mocks.user!, evolu_relays: [] };
    await render();
    expect(wrapper!.findAll('li')).toHaveLength(0);
    expect(wrapper!.text()).toContain('No relays are configured');
    expect(button('Add default relays').exists()).toBe(true);
    expect(mocks.put).not.toHaveBeenCalled();
  });

  it('moves keyboard focus into the add form and back to its trigger', async () => {
    await render();
    await button('＋ Add relay').trigger('click');
    await flushPromises();
    expect(document.activeElement).toBe(wrapper!.get('input[type="url"]').element);
    await wrapper!.get('button[aria-label="Back"]').trigger('click');
    await flushPromises();
    expect(document.activeElement).toBe(button('＋ Add relay').element);
  });
});
