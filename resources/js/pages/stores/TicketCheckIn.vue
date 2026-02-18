<template>
  <div class="min-h-screen bg-gray-950 text-white">
    <!-- Minimal public header -->
    <header class="bg-gray-900 border-b border-gray-800">
      <div
        class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between"
      >
        <div class="flex items-center gap-3">
          <div
            class="w-7 h-7 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center p-1"
          >
            <img
              src="/img/logo-satflux-white.svg"
              alt="SatFlux"
              class="w-full h-full"
            />
          </div>
          <span class="text-lg font-bold text-white tracking-tight"
            >SATFLUX</span
          >
        </div>
        <span
          v-if="eventData"
          class="text-xs text-gray-500 uppercase tracking-wider"
          >{{ t("tickets.check_in") }}</span
        >
      </div>
    </header>

    <!-- Content -->
    <div class="max-w-2xl mx-auto px-4 py-6 space-y-6">
      <!-- Event Info Header -->
      <div v-if="eventData" class="text-center pb-2">
        <h1 class="text-xl font-bold text-white mb-1">{{ eventData.title }}</h1>
        <div
          class="flex items-center justify-center gap-4 text-sm text-gray-400"
        >
          <span v-if="eventData.location" class="flex items-center gap-1">
            <svg
              class="w-3.5 h-3.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
              />
            </svg>
            {{ eventData.location }}
          </span>
          <span v-if="eventData.startDate" class="flex items-center gap-1">
            <svg
              class="w-3.5 h-3.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
            {{ formatDate(eventData.startDate) }}
          </span>
        </div>
        <!-- Stats -->
        <div
          v-if="stats.total > 0"
          class="mt-4 flex items-center justify-center gap-3"
        >
          <span class="text-sm text-gray-400"
            >{{ stats.checkedIn }} / {{ stats.total }}
            {{ t("tickets.checked_in_label") }}</span
          >
          <div class="w-24 h-2 bg-gray-800 rounded-full overflow-hidden">
            <div
              class="h-full bg-emerald-500 rounded-full transition-all duration-500"
              :style="{ width: `${(stats.checkedIn / stats.total) * 100}%` }"
            ></div>
          </div>
        </div>
      </div>

      <!-- Loading event -->
      <div v-if="loadingEvent" class="flex items-center justify-center py-16">
        <svg
          class="animate-spin h-8 w-8 text-indigo-500"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
      </div>

      <template v-else-if="eventData">
        <!-- ═══ Result Feedback (top, most visible) ═══ -->
        <transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 translate-y-4 scale-95"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="opacity-100 translate-y-0 scale-100"
          leave-to-class="opacity-0 -translate-y-2 scale-95"
        >
          <div
            v-if="lastResult"
            :class="[
              'rounded-2xl border overflow-hidden',
              lastResult.success
                ? 'bg-emerald-500/10 border-emerald-500/30'
                : 'bg-red-500/10 border-red-500/30',
            ]"
          >
            <div class="p-6">
              <div class="flex items-start gap-4">
                <div
                  :class="[
                    'flex-shrink-0 w-14 h-14 rounded-xl flex items-center justify-center',
                    lastResult.success ? 'bg-emerald-500/20' : 'bg-red-500/20',
                  ]"
                >
                  <svg
                    v-if="lastResult.success"
                    class="w-8 h-8 text-emerald-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                  <svg
                    v-else
                    class="w-8 h-8 text-red-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M6 18L18 6M6 6l12 12"
                    />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <h3
                    :class="[
                      'text-lg font-semibold mb-1',
                      lastResult.success ? 'text-emerald-400' : 'text-red-400',
                    ]"
                  >
                    {{
                      lastResult.success
                        ? t("tickets.checkin_ok")
                        : t("tickets.checkin_fail")
                    }}
                  </h3>
                  <p class="text-sm text-gray-300">{{ lastResult.message }}</p>
                  <div
                    v-if="lastResult.ticketInfo"
                    class="mt-3 space-y-1 text-sm text-gray-400"
                  >
                    <p v-if="lastResult.ticketInfo.name">
                      <span class="text-gray-500"
                        >{{ t("tickets.col_name") }}:</span
                      >
                      {{ lastResult.ticketInfo.name }}
                    </p>
                    <p v-if="lastResult.ticketInfo.email">
                      <span class="text-gray-500"
                        >{{ t("tickets.col_email") }}:</span
                      >
                      {{ lastResult.ticketInfo.email }}
                    </p>
                    <p v-if="lastResult.ticketInfo.type">
                      <span class="text-gray-500"
                        >{{ t("tickets.col_type") }}:</span
                      >
                      {{ lastResult.ticketInfo.type }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </transition>

        <!-- ═══ QR Scanner ═══ -->
        <div
          class="bg-gray-800 rounded-2xl border border-gray-700 overflow-hidden"
        >
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <h2
                class="text-base font-semibold text-white flex items-center gap-2"
              >
                <svg
                  class="w-5 h-5 text-indigo-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                  />
                </svg>
                {{ t("tickets.scan_qr") }}
              </h2>
              <button
                @click="toggleScanner"
                :class="[
                  'px-4 py-2 text-sm font-medium rounded-xl transition-all',
                  scanning
                    ? 'bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30'
                    : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30',
                ]"
              >
                {{
                  scanning
                    ? t("tickets.stop_scanning")
                    : t("tickets.start_scanning")
                }}
              </button>
            </div>

            <div
              v-show="scanning"
              class="relative rounded-xl overflow-hidden bg-black mb-4"
            >
              <div id="qr-reader" class="w-full"></div>
            </div>

            <p v-if="!scanning" class="text-sm text-gray-500 text-center py-4">
              {{ t("tickets.scan_hint") }}
            </p>
          </div>
        </div>

        <!-- ═══ Manual Entry ═══ -->
        <div
          class="bg-gray-800 rounded-2xl border border-gray-700 overflow-hidden"
        >
          <div class="p-6">
            <h2
              class="text-base font-semibold text-white flex items-center gap-2 mb-4"
            >
              <svg
                class="w-5 h-5 text-indigo-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
              {{ t("tickets.manual_entry") }}
            </h2>
            <form @submit.prevent="handleManualCheckIn" class="flex gap-3">
              <input
                ref="manualInput"
                v-model="manualCode"
                type="text"
                class="flex-1 px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-lg font-mono tracking-wider"
                :placeholder="t('tickets.enter_ticket_code')"
                autocomplete="off"
              />
              <button
                type="submit"
                :disabled="!manualCode.trim() || checkingIn"
                class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded-xl transition-all text-sm whitespace-nowrap"
              >
                <svg
                  v-if="checkingIn"
                  class="animate-spin h-5 w-5"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  ></circle>
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  ></path>
                </svg>
                <span v-else>{{ t("tickets.check_in") }}</span>
              </button>
            </form>
          </div>
        </div>

        <!-- ═══ Recent Check-ins History ═══ -->
        <div
          v-if="checkinHistory.length > 0"
          class="bg-gray-800 rounded-2xl border border-gray-700 overflow-hidden"
        >
          <div class="p-6">
            <h2 class="text-base font-semibold text-white mb-4">
              {{ t("tickets.recent_checkins") }}
            </h2>
            <div class="space-y-2">
              <div
                v-for="(entry, idx) in checkinHistory"
                :key="idx"
                :class="[
                  'flex items-center gap-3 px-4 py-3 rounded-xl',
                  entry.success
                    ? 'bg-emerald-500/5 border border-emerald-500/10'
                    : 'bg-red-500/5 border border-red-500/10',
                ]"
              >
                <svg
                  v-if="entry.success"
                  class="w-4 h-4 text-emerald-400 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <svg
                  v-else
                  class="w-4 h-4 text-red-400 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
                <span class="flex-1 text-sm text-gray-300 truncate">
                  <span
                    v-if="entry.ticketInfo?.name"
                    class="text-white font-medium"
                    >{{ entry.ticketInfo.name }}</span
                  >
                  <span v-else class="font-mono text-xs">{{ entry.code }}</span>
                </span>
                <span class="text-xs text-gray-500">{{
                  formatTime(entry.time)
                }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Error: event not found -->
      <div v-else-if="!loadingEvent" class="text-center py-16">
        <div
          class="bg-gray-800/50 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4"
        >
          <svg
            class="w-8 h-8 text-gray-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <p class="text-gray-400">{{ t("tickets.event_not_found") }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import api from "../../services/api";
import { Html5Qrcode } from "html5-qrcode";

const { t } = useI18n();
const route = useRoute();

// Route params
const storeId = route.params.id as string;
const eventId = route.params.eventId as string;

// State
interface EventInfo {
  id: string;
  title: string;
  eventType: string;
  location?: string | null;
  startDate?: string | null;
  endDate?: string | null;
  eventState: string;
  ticketsSold?: number;
  hasMaximumCapacity?: boolean;
  maximumEventCapacity?: number | null;
}

interface TicketInfo {
  name?: string;
  email?: string;
  type?: string;
}

interface CheckInResult {
  success: boolean;
  message: string;
  ticketInfo?: TicketInfo;
  code: string;
  time: Date;
}

const eventData = ref<EventInfo | null>(null);
const loadingEvent = ref(true);
const scanning = ref(false);
const checkingIn = ref(false);
const manualCode = ref("");
const manualInput = ref<HTMLInputElement | null>(null);

let html5QrCode: Html5Qrcode | null = null;
let lastScannedCode = "";
let lastScanTime = 0;

const lastResult = ref<CheckInResult | null>(null);
const checkinHistory = reactive<CheckInResult[]>([]);
const stats = reactive({ total: 0, checkedIn: 0 });

// ── Helpers ──────────────────────────────────────

function formatDate(dateStr: string): string {
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(dateStr));
  } catch {
    return dateStr;
  }
}

function formatTime(date: Date): string {
  return new Intl.DateTimeFormat(undefined, {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  }).format(date);
}

// ── Public API Calls ─────────────────────────────

async function loadEvent() {
  loadingEvent.value = true;
  try {
    const response = await api.get(
      `/public/ticket-checkin/${storeId}/events/${eventId}`,
    );
    eventData.value = response.data.data;
    // Use ticketsSold as a basic stat approximation
    if (eventData.value) {
      stats.total = eventData.value.ticketsSold || 0;
    }
  } catch {
    eventData.value = null;
  } finally {
    loadingEvent.value = false;
  }
}

async function performCheckIn(code: string): Promise<CheckInResult> {
  const ticketCode = code.trim();
  if (!ticketCode) {
    return {
      success: false,
      message: t("tickets.empty_code"),
      code: ticketCode,
      time: new Date(),
    };
  }

  try {
    const response = await api.post(
      `/public/ticket-checkin/${storeId}/events/${eventId}/tickets/${ticketCode}/check-in`,
      {},
    );
    const data = response.data.data;
    stats.checkedIn++;
    return {
      success: true,
      message: t("tickets.checkin_success", { number: ticketCode }),
      ticketInfo: {
        name:
          data?.firstName && data?.lastName
            ? `${data.firstName} ${data.lastName}`
            : undefined,
        email: data?.email || undefined,
        type: data?.ticketTypeName || undefined,
      },
      code: ticketCode,
      time: new Date(),
    };
  } catch (err: any) {
    const msg =
      err?.response?.data?.message ||
      err?.response?.data?.detail ||
      (Array.isArray(err?.response?.data)
        ? err.response.data.map((e: any) => e.message).join(", ")
        : "") ||
      err?.message ||
      t("tickets.checkin_fail");
    return { success: false, message: msg, code: ticketCode, time: new Date() };
  }
}

function setResult(result: CheckInResult) {
  lastResult.value = result;
  checkinHistory.unshift(result);
  if (checkinHistory.length > 30) checkinHistory.pop();

  // Auto-clear last result after 8s
  setTimeout(() => {
    if (lastResult.value?.time === result.time) {
      lastResult.value = null;
    }
  }, 8000);
}

async function handleManualCheckIn() {
  if (!manualCode.value.trim() || checkingIn.value) return;
  checkingIn.value = true;
  try {
    const result = await performCheckIn(manualCode.value);
    setResult(result);
    if (result.success) manualCode.value = "";
    await nextTick();
    manualInput.value?.focus();
  } finally {
    checkingIn.value = false;
  }
}

// ── QR Scanner ───────────────────────────────────

async function toggleScanner() {
  if (scanning.value) {
    await stopScanner();
  } else {
    await startScanner();
  }
}

async function startScanner() {
  scanning.value = true;
  await nextTick();

  try {
    html5QrCode = new Html5Qrcode("qr-reader");
    await html5QrCode.start(
      { facingMode: "environment" },
      {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1,
      },
      onScanSuccess,
      () => {
        /* no QR found - ignore */
      },
    );
  } catch (err: any) {
    scanning.value = false;
    setResult({
      success: false,
      message:
        t("tickets.camera_error") + (err?.message ? `: ${err.message}` : ""),
      code: "",
      time: new Date(),
    });
  }
}

async function stopScanner() {
  if (html5QrCode) {
    try {
      await html5QrCode.stop();
      html5QrCode.clear();
    } catch {
      // ignore
    }
    html5QrCode = null;
  }
  scanning.value = false;
}

async function onScanSuccess(decodedText: string) {
  // Debounce: same code within 5 seconds
  const now = Date.now();
  if (decodedText === lastScannedCode && now - lastScanTime < 5000) return;
  lastScannedCode = decodedText;
  lastScanTime = now;

  checkingIn.value = true;
  try {
    const result = await performCheckIn(decodedText);
    setResult(result);
  } finally {
    checkingIn.value = false;
  }
}

// ── Lifecycle ────────────────────────────────────

onMounted(() => {
  loadEvent();
});

onBeforeUnmount(() => {
  stopScanner();
});
</script>

<style scoped>
#qr-reader {
  min-height: 300px;
}
#qr-reader video {
  border-radius: 0.75rem;
}
</style>
