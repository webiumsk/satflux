<template>
  <section class="space-y-4" :aria-label="t('account.evolu_relay_title')">
    <div class="flex items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-3">
        <button v-if="view !== 'list'" ref="backButton" type="button" class="rounded-lg p-2 text-gray-300 hover:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400" :disabled="saving" :aria-label="t('common.back')" @click="showList">
          <span aria-hidden="true">←</span>
        </button>
        <h4 ref="heading" tabindex="-1" class="text-sm font-semibold text-white focus:outline-none">
          {{ view === 'add' ? t('account.evolu_relay_add') : view === 'detail' ? t('account.evolu_relay_detail') : t('account.evolu_relay_title') }}
        </h4>
      </div>
      <button v-if="view === 'list'" ref="addButton" type="button" class="rounded-lg border border-indigo-400 px-3 py-2 text-sm font-medium text-indigo-200 hover:bg-indigo-500/20 disabled:opacity-50" :disabled="saving || relays.length >= 8" @click="showAdd">
        <span aria-hidden="true">＋</span> {{ t('account.evolu_relay_add') }}
      </button>
    </div>

    <template v-if="view === 'list'">
      <p class="text-sm text-gray-300">{{ t('account.evolu_relay_list_desc') }}</p>
      <p v-if="hasInsecureDefault" class="text-sm text-amber-300">{{ t('account.evolu_relay_secure_default_required') }}</p>
      <p class="text-xs text-gray-400" role="status">{{ t('account.evolu_relay_connection_count', { connected: connectedCount, total: enabledCount }) }}</p>
      <ul v-if="relays.length" class="divide-y divide-gray-700 overflow-hidden rounded-xl border border-gray-700 bg-gray-900/40">
        <li v-for="relay in relays" :key="relay.url">
          <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left hover:bg-gray-700/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400" :disabled="saving" :aria-label="t('account.evolu_relay_open_detail', { url: relay.url })" @click="showDetail(relay.url)">
            <span class="min-w-0 space-y-2">
              <span class="block break-all text-sm text-gray-100">{{ relay.url }}</span>
              <span v-if="isDefault(relay.url)" class="inline-flex rounded-full bg-indigo-500/15 px-2 py-0.5 text-xs font-medium text-indigo-200">{{ t('account.evolu_relay_default_badge') }}</span>
            </span>
            <span class="flex shrink-0 flex-col items-end gap-1 text-xs sm:text-sm">
              <span v-if="protocolFor(relay).state !== 'unknown'" :class="isRelayProtocolError(protocolFor(relay).state) ? 'text-amber-300' : 'text-gray-400'">{{ protocolText(relay) }}</span>
              <span class="flex items-center gap-2" :class="statusClass(stateFor(relay))">
                <span>{{ statusText(stateFor(relay)) }}</span>
                <span class="h-2 w-2 rounded-full" :class="dotClass(stateFor(relay))" aria-hidden="true" />
                <span class="text-gray-400" aria-hidden="true">›</span>
              </span>
            </span>
          </button>
        </li>
      </ul>
      <p v-else class="rounded-xl border border-gray-700 p-4 text-sm text-gray-300">{{ t('account.evolu_relay_empty') }}</p>
      <button v-if="missingDefaults.length" type="button" class="text-sm font-medium text-indigo-300 hover:text-indigo-200 disabled:opacity-50" :disabled="saving || relays.length + missingDefaults.length > 8" @click="addDefaults">
        {{ t('account.evolu_relay_add_defaults') }}
      </button>
      <p class="text-xs text-gray-400">{{ t('account.evolu_relay_connection_hint') }}</p>
    </template>

    <form v-else-if="view === 'add'" class="space-y-4" @submit.prevent="addRelay">
      <label class="block text-sm text-gray-300">
        <span class="mb-2 block">{{ t('account.evolu_relay_url_label') }}</span>
        <input ref="urlInput" v-model="enteredUrl" type="url" required autocomplete="off" spellcheck="false" maxlength="512" placeholder="wss://relay.example.com" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-3 text-sm text-white placeholder:text-gray-500 focus:border-indigo-400 focus:outline-none" :disabled="saving" :aria-invalid="Boolean(error)" :aria-describedby="error ? errorId : undefined" @input="error = ''" />
      </label>
      <p v-if="error" :id="errorId" role="alert" class="text-sm text-red-300">{{ error }}</p>
      <p class="text-xs text-gray-400">{{ t('account.evolu_relay_compatibility') }}</p>
      <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50" :disabled="saving || !enteredUrl.trim()">{{ saving ? t('account.evolu_relay_saving') : t('account.evolu_relay_add') }}</button>
    </form>

    <div v-else-if="selectedRelay" class="space-y-5">
      <p class="break-all text-sm text-gray-100">{{ selectedRelay.url }}</p>
      <dl class="space-y-4 text-sm">
        <div class="flex items-center justify-between gap-4">
          <dt class="text-gray-300">{{ t('account.evolu_relay_connection_label') }}</dt>
          <dd class="flex items-center gap-2" :class="statusClass(stateFor(selectedRelay))">
            {{ statusText(stateFor(selectedRelay)) }}
            <span class="h-2 w-2 rounded-full" :class="dotClass(stateFor(selectedRelay))" aria-hidden="true" />
          </dd>
        </div>
        <div class="flex items-center justify-between gap-4">
          <dt class="text-gray-300">{{ t('account.evolu_relay_protocol_label') }}</dt>
          <dd :class="isRelayProtocolError(protocolFor(selectedRelay).state) ? 'text-amber-300' : 'text-gray-400'">{{ protocolText(selectedRelay) }}</dd>
        </div>
      </dl>
      <p v-if="protocolFor(selectedRelay).state === 'quota_exceeded'" class="text-sm text-amber-300" role="status">{{ t('account.evolu_relay_quota_hint') }}</p>
      <label class="flex cursor-pointer items-center justify-between gap-4 text-sm text-gray-300">
        <span>{{ t('account.evolu_relay_enabled_label') }}</span>
        <span class="relative inline-flex shrink-0 items-center">
          <input type="checkbox" role="switch" class="peer sr-only" :checked="selectedRelay.enabled" :disabled="saving" @change="toggleSelected" />
          <span class="h-6 w-11 rounded-full bg-gray-600 transition-colors peer-checked:bg-indigo-500 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-indigo-300 peer-disabled:opacity-50" aria-hidden="true" />
          <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5" aria-hidden="true" />
        </span>
      </label>
      <p class="text-xs text-gray-400">{{ t('account.evolu_relay_account_scope') }}</p>
      <p v-if="isDefault(selectedRelay.url)" class="text-xs text-gray-400">{{ t('account.evolu_relay_default_retained') }}</p>
      <button v-else ref="removeButton" type="button" class="w-full rounded-lg border border-red-400/60 px-4 py-3 text-sm font-medium text-red-200 hover:bg-red-500/10 disabled:opacity-50" :disabled="saving" @click="confirmRemove = true">{{ t('account.evolu_relay_remove') }}</button>
      <p class="text-xs text-gray-400">{{ t('account.evolu_relay_connection_hint') }}</p>
    </div>

    <p v-if="error && view !== 'add'" role="alert" class="text-sm text-red-300">{{ error }}</p>

    <DangerConfirmModal :open="confirmRemove" variant="dark" :title="t('account.evolu_relay_remove')" :body="t('account.evolu_relay_remove_confirm', { url: selectedUrl })" :confirm-label="t('account.evolu_relay_remove')" :busy="saving" @close="confirmRemove = false" @confirm="removeSelected" />
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/store/auth';
import { useFlashStore } from '@/store/flash';
import api from '@/services/api';
import { getDefaultEvoluRelayUrls, normalizeCustomEvoluRelayUrl, normalizeEvoluRelayConfigurations, resolveConfiguredEvoluRelays, type EvoluRelayConfiguration } from '@/evolu/config';
import { getActiveEvoluRelayUrls, subscribeActiveEvoluRelayUrls } from '@/evolu/relayPreferenceLifecycle';
import { useEvoluRelayConnections } from '@/composables/useEvoluRelayConnections';
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import { isRelayProtocolError, type RelayProtocolObservation } from '@/evolu/relayProtocolStatus';
import DangerConfirmModal from '@/components/ui/DangerConfirmModal.vue';

const props = defineProps<{ deviceSyncDisabled: boolean }>();
const emit = defineEmits<{ 'sync-error': [value: boolean] }>();
const { t } = useI18n();
const auth = useAuthStore();
const flash = useFlashStore();
const { connections, protocols } = useEvoluRelayConnections();
const isOnline = useOnlineStatus();
const relays = computed(() => resolveConfiguredEvoluRelays(auth.user ?? {}));
const defaults = computed(() => getDefaultEvoluRelayUrls());
const hasInsecureDefault = computed(() => relays.value.some(row => !normalizeCustomEvoluRelayUrl(row.url)));
const missingDefaults = computed(() => defaults.value.filter(url => !relays.value.some(row => row.url === url)));
const view = ref<'list' | 'add' | 'detail'>('list');
const selectedUrl = ref('');
const enteredUrl = ref('');
const error = ref('');
const saving = ref(false);
const confirmRemove = ref(false);
const heading = ref<HTMLElement | null>(null);
const urlInput = ref<HTMLInputElement | null>(null);
const addButton = ref<HTMLButtonElement | null>(null);
const backButton = ref<HTMLButtonElement | null>(null);
const removeButton = ref<HTMLButtonElement | null>(null);
const errorId = useId();
const activeUrls = ref<readonly string[]>(getActiveEvoluRelayUrls());
const selectedRelay = computed(() => relays.value.find(row => row.url === selectedUrl.value));
let accountGeneration = 0;
let unsubscribe: (() => void) | undefined;
type RowState = 'unknown' | 'connecting' | 'connected' | 'disconnected' | 'error' | 'disabled' | 'device_off' | 'paused';

onMounted(() => { unsubscribe = subscribeActiveEvoluRelayUrls(() => { activeUrls.value = getActiveEvoluRelayUrls(); }); });
onUnmounted(() => { accountGeneration++; unsubscribe?.(); });
watch(() => auth.user?.id, () => { accountGeneration++; view.value = 'list'; selectedUrl.value = ''; enteredUrl.value = ''; confirmRemove.value = false; error.value = ''; }, { flush: 'sync' });
const enabledCount = computed(() => relays.value.filter(row => row.enabled).length);
const hasProtocolError = computed(() => relays.value.some(row => isRelayProtocolError(protocolFor(row).state)));
watch(hasProtocolError, value => emit('sync-error', value), { immediate: true, flush: 'sync' });
const connectedCount = computed(() => relays.value.filter(row => stateFor(row) === 'connected').length);

/** Identify the configured application defaults without promoting arbitrary servers. */
function isDefault(url: string): boolean { return defaults.value.includes(url); }

/** Require this tab's guarded owner registration before showing worker connection evidence. */
function stateFor(relay: EvoluRelayConfiguration): RowState {
  if (!relay.enabled) return 'disabled';
  if (props.deviceSyncDisabled) return 'device_off';
  if (!isOnline.value) return 'disconnected';
  if (!activeUrls.value.includes(relay.url)) return 'paused';
  return connections.value[relay.url] ?? 'unknown';
}
/** Hide worker observations when this tab has no current permission to use the destination. */
function protocolFor(relay: EvoluRelayConfiguration): RelayProtocolObservation {
  const connection = stateFor(relay);
  if (!activeUrls.value.includes(relay.url) || ['disabled', 'device_off', 'paused'].includes(connection)) return { state: 'unknown', observedAt: 0 };
  const protocol = protocols.value[relay.url] ?? { state: 'unknown', observedAt: 0 };
  if (connection === 'disconnected' && !isRelayProtocolError(protocol.state)) return { state: 'unknown', observedAt: 0 };
  return protocol;
}
/** Render sanitized protocol evidence separately from the socket's connection state. */
function protocolText(relay: EvoluRelayConfiguration): string { return t(`account.evolu_relay_protocol_${protocolFor(relay).state}`); }
function statusText(state: RowState): string { return t(`account.evolu_relay_state_${state}`); }
/** Keep connection evidence readable without relying on dot color alone. */
function statusClass(state: RowState): string { return state === 'connected' ? 'text-emerald-300' : state === 'error' || state === 'disconnected' ? 'text-amber-300' : 'text-gray-400'; }
/** Add a secondary visual cue for the same textual connection state. */
function dotClass(state: RowState): string { return state === 'connected' ? 'bg-emerald-400' : state === 'connecting' ? 'animate-pulse bg-indigo-400' : state === 'error' || state === 'disconnected' ? 'bg-amber-400' : 'bg-gray-500'; }

/** Move focus to the new panel's heading after a row is opened. */
async function showDetail(url: string): Promise<void> { selectedUrl.value = url; view.value = 'detail'; await nextTick(); heading.value?.focus(); }
async function showList(): Promise<void> { view.value = 'list'; error.value = ''; await nextTick(); addButton.value?.focus(); }
async function showAdd(): Promise<void> { enteredUrl.value = ''; error.value = ''; view.value = 'add'; await nextTick(); urlInput.value?.focus(); }

/** Persist a complete list only for the account that initiated the operation. */
async function persist(next: EvoluRelayConfiguration[]): Promise<boolean> {
  if (saving.value) return false;
  const normalized = normalizeEvoluRelayConfigurations(next);
  if (!normalized) { error.value = t(hasInsecureDefault.value ? 'account.evolu_relay_secure_default_required' : 'account.evolu_relay_invalid_list'); return false; }
  const accountId = auth.user?.id;
  const generation = accountGeneration;
  if (accountId == null) return false;
  saving.value = true;
  try {
    await api.put('/user', { evolu_relays: normalized });
    if (generation !== accountGeneration || auth.user?.id !== accountId) return false;
    const refreshed = await auth.fetchUser();
    if (!refreshed || generation !== accountGeneration || auth.user?.id !== accountId) throw new Error('Profile refresh unavailable');
    confirmRemove.value = false;
    flash.success(t('account.evolu_relay_saved'));
    // A fresh document gets the CSP for newly configured origins; the auth refresh
    // already reconciles canonical destinations without writing from stale form state.
    window.location.reload();
    return true;
  } catch {
    if (generation === accountGeneration && auth.user?.id === accountId) {
      error.value = t('account.evolu_relay_save_failed');
      flash.error(error.value);
    }
    return false;
  } finally { saving.value = false; }
}

/** Normalize the entered origin and prevent duplicates before the atomic account update. */
async function addRelay(): Promise<void> {
  const url = normalizeCustomEvoluRelayUrl(enteredUrl.value);
  if (!url) { error.value = t('account.evolu_relay_invalid_url'); return; }
  if (relays.value.some(row => row.url === url)) { error.value = t('account.evolu_relay_duplicate'); return; }
  if (await persist([...relays.value, { url, enabled: true }])) await showList();
}
async function addDefaults(): Promise<void> { await persist([...relays.value, ...missingDefaults.value.map(url => ({ url, enabled: true }))]); }
/** Restore the visible checkbox when the canonical save fails or the account changes. */
async function toggleSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const selected = selectedRelay.value;
  if (selected) await persist(relays.value.map(row => row.url === selected.url ? { ...row, enabled: !row.enabled } : row));
  input.checked = selectedRelay.value?.enabled ?? false;
}
/** Remove only the explicitly confirmed custom row while preserving every other setting. */
async function removeSelected(): Promise<void> {
  const selected = selectedRelay.value;
  if (!selected || isDefault(selected.url)) return;
  if (await persist(relays.value.filter(row => row.url !== selected.url))) await showList();
}
</script>
