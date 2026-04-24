<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50"
      @click.self="emit('close')"
    >
      <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h5 class="text-lg font-bold text-white">{{ modalTitle }}</h5>
          <button type="button" @click="handleClose" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Step 1: Choose method -->
        <div v-if="step === 'choose'" class="space-y-3">
          <p class="text-sm text-gray-400 mb-4">{{ t('auth.nostr_choose_method') }}</p>
          <button
            type="button"
            :disabled="loading"
            @click="signWithNip07"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-600 text-left text-white hover:bg-gray-700/50 transition-colors disabled:opacity-50"
          >
            <span class="text-2xl">🔌</span>
            <div>
              <span class="font-medium">{{ t('auth.nostr_nip07') }}</span>
              <span class="block text-sm text-gray-400">{{ t('auth.nostr_nip07_hint') }}</span>
            </div>
          </button>
          <button
            type="button"
            :disabled="loading"
            @click="signWithNip46"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-600 text-left text-white hover:bg-gray-700/50 transition-colors disabled:opacity-50"
          >
            <span class="text-2xl">🔗</span>
            <div>
              <span class="font-medium">{{ t('auth.nostr_nip46') }}</span>
              <span class="block text-sm text-gray-400">{{ t('auth.nostr_nip46_hint') }}</span>
            </div>
          </button>
          <button
            type="button"
            :disabled="loading"
            @click="step = 'nsec'"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-amber-600/50 text-left text-white hover:bg-gray-700/50 transition-colors disabled:opacity-50"
          >
            <span class="text-2xl">⚠️</span>
            <div>
              <span class="font-medium">{{ t('auth.nostr_nsec') }}</span>
              <span class="block text-sm text-amber-400/90">{{ t('auth.nostr_nsec_not_recommended') }}</span>
            </div>
          </button>
        </div>

        <!-- Step nsec: paste nsec with warning -->
        <div v-else-if="step === 'nsec'" class="space-y-4">
          <div class="rounded-lg bg-amber-500/10 border border-amber-500/30 p-3 text-sm text-amber-200">
            {{ t('auth.nostr_nsec_warning') }}
          </div>
          <label class="block text-sm font-medium text-gray-300">{{ t('auth.nostr_paste_nsec') }}</label>
          <input
            v-model="nsecInput"
            type="password"
            autocomplete="off"
            class="w-full px-4 py-2 rounded-lg border border-gray-600 bg-gray-700/50 text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500"
            :placeholder="t('auth.nostr_nsec_placeholder')"
          />
          <div class="flex gap-2">
            <button
              type="button"
              @click="step = 'choose'"
              class="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700"
            >
              {{ t('common.back') }}
            </button>
            <button
              type="button"
              :disabled="!nsecInput.trim() || loading"
              @click="signWithNsec"
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50"
            >
              {{ loading ? t('auth.generating') : t('auth.sign_in') }}
            </button>
          </div>
        </div>

        <!-- NIP-46 info (no full implementation: suggest extension) -->
        <div v-else-if="step === 'nip46_info'" class="space-y-4">
          <p class="text-sm text-gray-400">{{ t('auth.nostr_nip46_use_extension') }}</p>
          <button
            type="button"
            @click="step = 'choose'; signWithNip07();"
            class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500"
          >
            {{ t('auth.nostr_try_extension') }}
          </button>
          <button type="button" @click="step = 'choose'" class="w-full px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700">
            {{ t('common.back') }}
          </button>
        </div>

        <!-- Polling / email step -->
        <div v-else-if="step === 'polling' || step === 'email_step'">
          <p v-if="step === 'polling'" class="text-sm text-gray-400 mb-2">{{ t('auth.waiting_for_authentication') }}</p>
          <div v-if="step === 'email_step'" class="space-y-4">
            <h6 class="font-medium text-white">{{ t('auth.complete_registration') }}</h6>
            <p class="text-sm text-gray-400">{{ t('auth.provide_email_to_complete') }}</p>
            <input
              v-model="emailForm.email"
              type="email"
              required
              class="w-full px-4 py-2 rounded-lg border border-gray-600 bg-gray-700/50 text-white placeholder-gray-500"
              :placeholder="t('auth.email_placeholder')"
            />
            <p v-if="emailError" class="text-sm text-red-400">{{ emailError }}</p>
            <div class="flex gap-2">
              <button
                type="button"
                @click="handleClose"
                class="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700"
              >
                {{ t('common.cancel') }}
              </button>
              <button
                type="button"
                :disabled="emailLoading"
                @click="submitEmail"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50"
              >
                {{ emailLoading ? t('common.loading') : t('common.continue') }}
              </button>
            </div>
          </div>
        </div>

        <p v-if="error" class="mt-3 text-sm text-red-400">{{ error }}</p>
        <div class="mt-4 flex justify-end">
          <button
            v-if="step === 'choose' || step === 'nsec' || step === 'nip46_info'"
            type="button"
            @click="handleClose"
            class="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700"
          >
            {{ t('common.cancel') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../store/auth';
import api from '../../services/api';

const props = withDefaults(
  defineProps<{
    open: boolean;
    mode: 'login' | 'register' | 'link' | 'reveal';
    /** For reveal: after confirm, call this with connectionId to POST reveal with confirm_via_nostr */
    connectionId?: number;
    /** Local store UUID (route /stores/{id}/...) */
    storeId?: string;
    /** When reveal + storeId: wallet secret vs Cashu edit gate (no wallet_connections row). */
    confirmPurpose?: 'wallet_reveal' | 'cashu_edit';
  }>(),
  { connectionId: undefined, storeId: undefined, confirmPurpose: 'wallet_reveal' }
);

const emit = defineEmits<{
  close: [];
  success: [payload?: unknown];
}>();

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();

const step = ref<'choose' | 'nsec' | 'nip46_info' | 'polling' | 'email_step'>('choose');
const loading = ref(false);
const error = ref('');
const nsecInput = ref('');
const challengeId = ref('');
const emailForm = ref({ email: '', user_id: null as number | null });
const emailError = ref('');
const emailLoading = ref(false);
let pollingInterval: ReturnType<typeof setInterval> | null = null;

const modalTitle = computed(() => {
  if (props.mode === 'link') return t('account.add_nostr_login');
  if (props.mode === 'reveal') return t('auth.nostr_confirm_reveal');
  return props.mode === 'register' ? t('auth.sign_up') : t('auth.sign_in');
});

function handleClose() {
  stopPolling();
  step.value = 'choose';
  error.value = '';
  nsecInput.value = '';
  challengeId.value = '';
  emailForm.value = { email: '', user_id: null };
  emailError.value = '';
  emit('close');
}

async function getChallenge(): Promise<string | null> {
  let url = '/nostr-auth/challenge';
  if (props.mode === 'link') url = '/nostr-auth/link-challenge';
  if (props.mode === 'reveal') url = '/nostr-auth/reveal-confirm-challenge';
  const res = await api.post(url);
  const data = res.data?.data ?? res.data;
  const id = data?.challenge_id ?? data?.challengeId;
  if (!id) throw new Error(t('auth.failed_to_generate_challenge'));
  return id;
}

function buildEvent(challenge: string): Record<string, unknown> {
  return {
    kind: 22242,
    created_at: Math.floor(Date.now() / 1000),
    tags: [],
    content: challenge,
  };
}

async function signWithNip07() {
  if (typeof window === 'undefined' || !(window as unknown as { nostr?: { getPublicKey: () => Promise<string>; signEvent: (e: Record<string, unknown>) => Promise<Record<string, unknown>> } }).nostr) {
    error.value = t('auth.nostr_extension_required');
    return;
  }
  const nostr = (window as unknown as { nostr: { getPublicKey: () => Promise<string>; signEvent: (e: Record<string, unknown>) => Promise<Record<string, unknown>> } }).nostr;
  await doSignAndVerify(async (challenge) => {
    const template = buildEvent(challenge);
    const signed = await nostr.signEvent(template as { kind: number; created_at: number; tags: unknown[]; content: string });
    return signed as { pubkey: string; id: string; sig: string; kind: number; created_at: number; tags: unknown[]; content: string };
  });
}

function signWithNip46() {
  step.value = 'nip46_info';
  error.value = '';
}

async function signWithNsec() {
  const nsec = nsecInput.value.trim();
  if (!nsec) {
    error.value = t('auth.nostr_paste_nsec');
    return;
  }
  try {
    const nt = await import('nostr-tools');
    const decoded = nt.nip19.decode(nsec);
    if (decoded.type !== 'nsec') throw new Error('Invalid nsec');
    // finalizeEvent expects private key as Uint8Array (32 bytes), not hex string
    const secretBytes = decoded.data as Uint8Array;
    if (!(secretBytes instanceof Uint8Array) || secretBytes.length !== 32) {
      throw new Error('Invalid nsec');
    }
    await doSignAndVerify(async (challenge) => {
      const template = buildEvent(challenge);
      const signed = nt.finalizeEvent(template as { kind: number; created_at: number; tags: unknown[]; content: string }, secretBytes);
      return signed;
    });
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('auth.error_occurred');
  }
}

async function doSignAndVerify(
  sign: (challenge: string) => Promise<{ pubkey: string; id: string; sig: string; kind: number; created_at: number; tags: unknown[]; content: string }>
) {
  loading.value = true;
  error.value = '';
  try {
    const challenge = await getChallenge();
    if (!challenge) return;
    challengeId.value = challenge;
    const event = await sign(challenge);
    await api.post('/nostr-auth/verify', { challenge_id: challenge, event });
    step.value = 'polling';
    startPolling();
  } catch (e: unknown) {
    const msg = e && typeof e === 'object' && 'response' in e && e.response && typeof e.response === 'object' && 'data' in e.response
      ? (e.response as { data?: { error?: string } }).data?.error
      : e instanceof Error ? e.message : t('auth.error_occurred');
    error.value = msg || t('auth.error_occurred');
  } finally {
    loading.value = false;
  }
}

function startPolling() {
  const id = challengeId.value;
  if (!id) return;
  const doPoll = async () => {
    try {
      const res = await api.get(`/nostr-auth/challenge-status/${id}?_=${Date.now()}`);
      const data = res.data?.data ?? res.data;
      const status = data?.status;

      if (status === 'authenticated') {
        stopPolling();
        await authStore.fetchUser();
        handleClose();
        if (props.mode === 'login' || props.mode === 'register') router.push('/dashboard');
      } else if (status === 'pending_email') {
        stopPolling();
        emailForm.value.user_id = data?.user_id ?? null;
        step.value = 'email_step';
      } else if (status === 'linked') {
        stopPolling();
        await authStore.fetchUser();
        emit('success');
        handleClose();
      } else if (status === 'reveal_confirmed') {
        stopPolling();
        if (props.mode === 'reveal' && (props.connectionId !== undefined || props.storeId !== undefined)) {
          try {
            let res: { data?: { data?: { secret?: string; ok?: boolean } } };
            if (props.storeId !== undefined && props.confirmPurpose === 'cashu_edit') {
              res = await api.post(`/stores/${props.storeId}/cashu/confirm-edit`, { confirm_via_nostr: true });
            } else if (props.storeId !== undefined) {
              res = await api.post(`/stores/${props.storeId}/wallet-connection/reveal`, { confirm_via_nostr: true });
            } else if (props.connectionId !== undefined) {
              res = await api.post(`/support/wallet-connections/${props.connectionId}/reveal`, { confirm_via_nostr: true });
            } else {
              emit('success');
              handleClose();
              return;
            }
            emit('success', res?.data?.data);
            handleClose();
          } catch (e) {
            error.value = t('auth.error_occurred');
          }
        } else {
          emit('success');
          handleClose();
        }
      } else if (status === 'expired' || status === 'error') {
        stopPolling();
        error.value = data?.message || t('account.challenge_expired');
      }
    } catch {
      // keep polling on network errors
    }
  };
  doPoll();
  pollingInterval = setInterval(doPoll, 1000);
}

function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
}

async function submitEmail() {
  emailError.value = '';
  emailLoading.value = true;
  try {
    await api.post('/nostr-auth/complete-registration', {
      ...(emailForm.value.user_id ? { user_id: emailForm.value.user_id } : { challenge_id: challengeId.value }),
      email: emailForm.value.email,
    });
    handleClose();
    if (props.mode === 'register' || props.mode === 'login') router.push('/login?email_verified=1');
  } catch (e: unknown) {
    const d = e && typeof e === 'object' && 'response' in e ? (e as { response: { data?: { errors?: { email?: string[] }; message?: string } } }).response?.data : undefined;
    emailError.value = d?.errors?.email?.[0] ?? d?.message ?? t('auth.error_occurred');
  } finally {
    emailLoading.value = false;
  }
}

watch(() => props.open, (open: boolean) => {
  if (!open) handleClose();
});

onUnmounted(() => stopPolling());
</script>
