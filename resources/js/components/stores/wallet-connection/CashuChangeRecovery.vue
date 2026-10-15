<template>
  <section class="rounded-2xl border border-gray-700 bg-gray-800 p-5 sm:p-6 space-y-5" data-testid="cashu-change-recovery">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-white">{{ t('stores.cashu_change_title') }}</h2>
        <p class="mt-1 text-sm text-gray-400">{{ t('stores.cashu_change_intro') }}</p>
        <RouterLink to="/documentation/cashu-change-recovery" class="mt-2 inline-block text-sm text-indigo-300 underline underline-offset-2">{{ t('common.documentation') }}</RouterLink>
      </div>
      <button type="button" data-testid="cashu-change-refresh" :disabled="loading || actionBusy" class="rounded-lg border border-gray-600 px-3 py-2 text-sm text-gray-200 disabled:opacity-50" @click="refresh">{{ t('stores.cashu_payments_refresh') }}</button>
    </div>
    <p v-if="loading && !summary" role="status" class="text-sm text-gray-300">{{ t('common.loading') }}</p>
    <template v-if="summary?.status === 'available'">
      <dl class="grid gap-4 sm:grid-cols-3 text-sm">
        <div><dt class="text-gray-400">{{ t('stores.cashu_change_pending') }}</dt><dd class="mt-1 text-white font-semibold tabular-nums" data-testid="cashu-change-pending">{{ summary.pending_sat }} sat</dd></div>
        <div><dt class="text-gray-400">{{ t('stores.cashu_change_swept') }}</dt><dd class="mt-1 text-white font-semibold tabular-nums">{{ summary.swept_sat }} sat</dd></div>
        <div><dt class="text-gray-400">{{ t('stores.cashu_change_threshold') }}</dt><dd class="mt-1 text-white font-semibold tabular-nums">{{ summary.minimum_sweep_sat }} sat</dd></div>
      </dl>
      <p v-if="belowSweepThreshold" class="rounded-lg border border-amber-500/25 bg-amber-500/10 p-3 text-sm text-amber-100" data-testid="cashu-change-small-balance">{{ t('stores.cashu_change_below_threshold', { amount: summary.pending_sat, threshold: summary.minimum_sweep_sat }) }}</p>
      <dl class="grid gap-x-5 gap-y-2 sm:grid-cols-2 text-sm">
        <div v-for="field in countFields" :key="field" class="flex justify-between gap-4"><dt class="text-gray-400">{{ t(`stores.cashu_change_${field}`) }}</dt><dd class="text-gray-200 tabular-nums">{{ summary[field] ?? t('stores.cashu_wallet_safety_unknown') }}</dd></div>
      </dl>
      <p class="text-xs text-gray-400">{{ t('stores.cashu_change_records_hint') }}</p>
      <p v-if="summary.unresolved_records === 0" class="text-sm text-gray-300" data-testid="cashu-change-empty">{{ t('stores.cashu_change_none') }}</p>
      <p v-if="(summary.unsupported_unit_records ?? 0) > 0 || (summary.other_unresolved_records ?? 0) > 0 || (summary.sweeping_records ?? 0) > 0" class="text-sm text-amber-200">{{ t('stores.cashu_change_other_records') }}</p>
      <p v-if="!canRecover" class="text-sm text-gray-300" data-testid="cashu-change-owner-only">{{ t('stores.cashu_change_owner_only') }}</p>
    </template>
    <p v-else-if="summary" role="status" class="text-sm text-amber-100" data-testid="cashu-change-capability">{{ t(summary.status === 'unsupported' ? 'stores.cashu_change_unsupported' : 'stores.cashu_change_unavailable') }}</p>

    <p v-if="error" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200">{{ error }}</p>
    <p v-if="notice" role="status" class="rounded-lg border border-indigo-500/25 bg-indigo-500/10 p-3 text-sm text-indigo-100">{{ notice }}</p>

    <template v-if="canRecover">
      <div v-if="!selectedAction" class="flex flex-wrap gap-3">
        <button type="button" data-testid="cashu-change-export" :disabled="loading || actionBusy || !canExport" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="startAction('export')">{{ t('stores.cashu_change_export') }}</button>
        <button type="button" data-testid="cashu-change-verify" :disabled="loading || actionBusy || !canVerify" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 disabled:opacity-50" @click="startAction('verify')">{{ t('stores.cashu_change_verify') }}</button>
      </div>
      <div v-else class="rounded-xl border border-gray-600 bg-gray-900/40 p-4 space-y-4">
        <h3 class="font-semibold text-white">{{ t(selectedAction === 'export' ? 'stores.cashu_change_export' : 'stores.cashu_change_verify') }}</h3>
        <p class="text-sm text-amber-100">{{ t(selectedAction === 'export' ? 'stores.cashu_change_export_warning' : 'stores.cashu_change_verify_explanation') }}</p>
        <template v-if="challenge">
          <p class="text-sm text-gray-300">{{ t(selectedAction === 'export' ? 'stores.cashu_change_export_code' : 'stores.cashu_change_verify_code') }}</p>
          <EmailCodeVerification :key="challengeKey" :challenge="challenge" :confirm="performAction" :resend="resendChallenge" :allow-change-email="false" />
        </template>
        <form v-else class="space-y-4" @submit.prevent="requestChallenge">
          <label class="flex items-start gap-3 text-sm text-gray-200">
            <input v-model="confirmed" type="checkbox" :disabled="actionBusy" class="mt-1" data-testid="cashu-change-consent" />
            {{ t(selectedAction === 'export' ? 'stores.cashu_change_export_consent' : 'stores.cashu_change_verify_consent') }}
          </label>
          <div v-if="requiresPassword">
            <label for="cashu-change-password" class="block text-sm text-gray-300 mb-1">{{ t('account.current_password') }}</label>
            <input id="cashu-change-password" v-model="password" type="password" autocomplete="current-password" :disabled="actionBusy" class="w-full rounded-lg border border-gray-600 bg-gray-800 px-3 py-2 text-white" />
          </div>
          <button type="submit" :disabled="actionBusy || !confirmed || (requiresPassword && !password.trim())" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ t(actionBusy ? 'common.loading' : 'stores.cashu_change_request_code') }}</button>
        </form>
        <button type="button" :disabled="actionBusy" class="text-sm text-gray-300 underline underline-offset-2 disabled:opacity-50" @click="cancelAction">{{ t('common.cancel') }}</button>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore, type EmailChallengeSummary } from '../../../store/auth';
import { walletApi, type CashuChangeAction, type CashuChangeSummary } from '../../../services/api';
import { asApiError } from '../../../utils/apiError';
import EmailCodeVerification from '../../account/EmailCodeVerification.vue';

const props = defineProps<{ storeId: string }>();
const emit = defineEmits<{ changed: [] }>();
const { t } = useI18n();
const auth = useAuthStore();
const requiresPassword = computed(() => auth.user?.can_use_password_login ?? true);
const summary = ref<CashuChangeSummary | null>(null);
const loading = ref(false);
const actionBusy = ref(false);
const selectedAction = ref<CashuChangeAction | null>(null);
const challenge = ref<EmailChallengeSummary | null>(null);
const challengeKey = ref(0);
const confirmed = ref(false);
const password = ref('');
const error = ref('');
const notice = ref('');
let lifecycle = 0;
let summaryGeneration = 0;
let actionGeneration = 0;
let summaryController: AbortController | null = null;
let actionController: AbortController | null = null;
const downloads = new Map<string, ReturnType<typeof setTimeout>>();
const countFields = ['unresolved_records', 'available_records', 'exported_records', 'sweeping_records', 'manual_review_records', 'unsupported_unit_records', 'other_unresolved_records'] as const;
const amountFields = ['pending_sat', 'swept_sat', 'minimum_sweep_sat'] as const;
const canRecover = computed(() => summary.value?.status === 'available' && summary.value.can_recover === true);
const canExport = computed(() => canRecover.value && (summary.value!.available_records! + summary.value!.exported_records! + summary.value!.manual_review_records!) > 0);
const canVerify = computed(() => canRecover.value && (summary.value?.exported_records ?? 0) > 0);
const belowSweepThreshold = computed(() => {
  const amount = summary.value?.pending_sat;
  const threshold = summary.value?.minimum_sweep_sat;
  return Boolean(amount && amount !== '0' && threshold && (amount.length < threshold.length || (amount.length === threshold.length && amount < threshold)));
});

function emptySummary(status: 'unsupported' | 'unavailable' = 'unavailable'): CashuChangeSummary {
  return { version: null, status, can_recover: false, pending_sat: null, swept_sat: null, minimum_sweep_sat: null,
    unresolved_records: null, available_records: null, exported_records: null, sweeping_records: null,
    manual_review_records: null, unsupported_unit_records: null, other_unresolved_records: null };
}
function validAmount(value: unknown): value is string {
  return typeof value === 'string' && /^(0|[1-9]\d*)$/.test(value) && (value.length < 19 || (value.length === 19 && value <= '9223372036854775807'));
}
function normaliseSummary(raw: unknown): CashuChangeSummary {
  if (!raw || typeof raw !== 'object') return emptySummary();
  const value = raw as Record<string, unknown>;
  if (value.status === 'unsupported') return emptySummary('unsupported');
  if (value.status !== 'available' || value.version !== 1 || typeof value.can_recover !== 'boolean' ||
      !amountFields.every(field => validAmount(value[field])) || !countFields.every(field => typeof value[field] === 'number' && Number.isInteger(value[field]) && Number(value[field]) >= 0 && Number(value[field]) <= 2147483647)) return emptySummary();
  return { version: 1, status: 'available', can_recover: value.can_recover,
    pending_sat: value.pending_sat as string, swept_sat: value.swept_sat as string, minimum_sweep_sat: value.minimum_sweep_sat as string,
    unresolved_records: value.unresolved_records as number, available_records: value.available_records as number,
    exported_records: value.exported_records as number, sweeping_records: value.sweeping_records as number,
    manual_review_records: value.manual_review_records as number, unsupported_unit_records: value.unsupported_unit_records as number,
    other_unresolved_records: value.other_unresolved_records as number };
}
function errorCode(raw: unknown): string {
  const code = asApiError(raw).response?.data?.code;
  return typeof code === 'string' ? code : 'cashu_change_unavailable';
}
const errorKeys: Record<string, string> = {
  cashu_change_unsupported: 'cashu_change_unsupported', cashu_change_unavailable: 'cashu_change_operation_failed',
  cashu_change_recovery_refused: 'cashu_change_refused', cashu_change_empty: 'cashu_change_empty',
  cashu_change_forbidden: 'cashu_change_owner_only', cashu_change_password_invalid: 'invalid_password',
  cashu_change_rate_limited: 'cashu_change_rate_limited', code_mismatch: 'cashu_change_code_invalid',
  challenge_missing: 'cashu_change_code_expired', challenge_expired: 'cashu_change_code_expired',
  challenge_locked: 'cashu_change_code_locked', resend_cooldown: 'cashu_change_code_cooldown', send_limit_reached: 'cashu_change_code_limit',
};
function safeMessage(raw: unknown): string { return t(`stores.${errorKeys[errorCode(raw)] ?? 'cashu_change_operation_failed'}`); }
function safeChallengeError(raw: unknown) {
  const e = asApiError(raw);
  const counter = (value: unknown) => typeof value === 'number' && Number.isInteger(value) && value >= 0 && value <= 2147483647 ? value : undefined;
  return { response: { status: e.response?.status, data: { code: errorCode(raw), message: safeMessage(raw), retry_after: counter(e.response?.data?.retry_after), attempts_left: counter(e.response?.data?.attempts_left) } } };
}
function current(sid: string, generation: number, actionVersion?: number): boolean {
  return sid === props.storeId && generation === lifecycle && (actionVersion === undefined || actionVersion === actionGeneration);
}
async function refresh() {
  summaryController?.abort();
  summaryController = new AbortController();
  const signal = summaryController.signal;
  const generation = ++summaryGeneration;
  const context = lifecycle;
  const sid = props.storeId;
  loading.value = true;
  try {
    const result = await walletApi.cashu.change.getSummary(sid, signal);
    if (current(sid, context) && generation === summaryGeneration) summary.value = normaliseSummary(result);
  } catch (raw) {
    if (current(sid, context) && generation === summaryGeneration) summary.value = emptySummary(errorCode(raw) === 'cashu_change_unsupported' ? 'unsupported' : 'unavailable');
  } finally {
    if (current(sid, context) && generation === summaryGeneration) loading.value = false;
  }
}
function cancelAction() {
  actionGeneration++;
  actionController?.abort();
  selectedAction.value = null; challenge.value = null; password.value = ''; confirmed.value = false; actionBusy.value = false;
}
function startAction(action: CashuChangeAction) {
  if (!(action === 'export' ? canExport.value : canVerify.value) || actionBusy.value || loading.value) return;
  cancelAction(); error.value = ''; notice.value = ''; selectedAction.value = action;
}
function projectChallenge(value: EmailChallengeSummary): EmailChallengeSummary {
  if (!value || typeof value.email !== 'string' || typeof value.purpose !== 'string' || !Number.isFinite(Date.parse(value.expires_at)) || !Number.isFinite(Date.parse(value.resend_available_at)) || !Number.isInteger(value.attempts_left)) throw new Error('Invalid challenge');
  return { email: value.email, purpose: value.purpose, expires_at: value.expires_at, resend_available_at: value.resend_available_at, attempts_left: value.attempts_left, sends_left: value.sends_left };
}
async function requestChallenge() {
  const action = selectedAction.value;
  if (!action || !(action === 'export' ? canExport.value : canVerify.value) || actionBusy.value || !confirmed.value || (requiresPassword.value && !password.value.trim())) return;
  const sid = props.storeId; const context = lifecycle; const version = actionGeneration;
  actionController = new AbortController(); actionBusy.value = true; error.value = '';
  try {
    const result = await walletApi.cashu.change.requestConfirmation(sid, action, requiresPassword.value ? { password: password.value } : {}, actionController.signal);
    if (!current(sid, context, version)) return;
    challenge.value = projectChallenge(result); challengeKey.value++;
  } catch (raw) { if (current(sid, context, version)) error.value = safeMessage(raw); }
  finally { if (current(sid, context, version)) { password.value = ''; actionBusy.value = false; } }
}
async function resendChallenge(): Promise<EmailChallengeSummary | null> {
  const action = selectedAction.value;
  if (!action || actionBusy.value || !canRecover.value) throw safeChallengeError(null);
  const sid = props.storeId; const context = lifecycle; const version = actionGeneration;
  actionController = new AbortController(); actionBusy.value = true;
  try {
    const result = await walletApi.cashu.change.resendConfirmation(sid, action, actionController.signal);
    if (!current(sid, context, version)) return null;
    const next = projectChallenge(result); challenge.value = next; return next;
  } catch (raw) {
    if (!current(sid, context, version)) return null;
    if (['challenge_missing', 'challenge_expired', 'challenge_locked'].includes(errorCode(raw))) {
      challenge.value = null; selectedAction.value = null; confirmed.value = false; error.value = safeMessage(raw);
      return null;
    }
    throw safeChallengeError(raw);
  }
  finally { if (current(sid, context, version)) actionBusy.value = false; }
}
function revokeDownloads() {
  for (const [url, timer] of downloads) { clearTimeout(timer); URL.revokeObjectURL(url); }
  downloads.clear();
}
function download(blob: Blob) {
  if (!(blob instanceof Blob) || blob.size === 0 || blob.type.split(';')[0] !== 'application/json') throw new Error('Invalid attachment');
  const url = URL.createObjectURL(blob);
  try {
    const anchor = document.createElement('a'); anchor.href = url; anchor.download = 'cashumelt-change.json';
    document.body.appendChild(anchor);
    try { anchor.click(); } finally { anchor.remove(); }
    const timer = setTimeout(() => { URL.revokeObjectURL(url); downloads.delete(url); }, 1000);
    downloads.set(url, timer);
  } catch { URL.revokeObjectURL(url); throw new Error('Download unavailable'); }
}
async function performAction(code: string) {
  const action = selectedAction.value;
  if (!action || !challenge.value || !confirmed.value || !(action === 'export' ? canExport.value : canVerify.value) || actionBusy.value) throw safeChallengeError(null);
  const sid = props.storeId; const context = lifecycle; const version = actionGeneration;
  actionController = new AbortController(); actionBusy.value = true; error.value = ''; notice.value = '';
  try {
    if (action === 'export') {
      const blob = await walletApi.cashu.change.exportTokens(sid, code, actionController.signal);
      if (!current(sid, context, version)) return;
      download(blob);
    } else {
      const verified = await walletApi.cashu.change.verify(sid, code, actionController.signal);
      if (!current(sid, context, version)) return;
      if (!verified) throw new Error('Verification unavailable');
    }
    challenge.value = null; selectedAction.value = null; confirmed.value = false;
    notice.value = t(action === 'export' ? 'stores.cashu_change_exported' : 'stores.cashu_change_verified');
    await refresh();
    if (current(sid, context, version)) emit('changed');
  } catch (raw) {
    if (!current(sid, context, version)) return;
    if (errorCode(raw) === 'code_mismatch') throw safeChallengeError(raw);
    error.value = safeMessage(raw);
    challenge.value = null; selectedAction.value = null; confirmed.value = false;
    await refresh();
    if (current(sid, context, version)) emit('changed');
  } finally { if (current(sid, context, version)) actionBusy.value = false; }
}
function cleanup() {
  lifecycle++; summaryGeneration++; cancelAction(); summaryController?.abort(); revokeDownloads();
  summary.value = null; loading.value = false; error.value = ''; notice.value = '';
}
watch(() => props.storeId, () => { cleanup(); void refresh(); }, { immediate: true });
onBeforeUnmount(cleanup);
defineExpose({ refresh });
</script>
