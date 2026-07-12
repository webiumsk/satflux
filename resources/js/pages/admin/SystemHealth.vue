<template>
  <div
    class="min-h-0 flex-1 max-md:flex-none max-md:overflow-visible md:overflow-y-auto overscroll-y-contain custom-scrollbar"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-3xl font-bold text-white">{{ t("admin.health.title") }}</h1>
          <p class="text-gray-400 mt-1">{{ t("admin.health.description") }}</p>
        </div>
        <button
          type="button"
          class="text-sm font-medium rounded-md px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white disabled:opacity-50"
          :disabled="loading"
          @click="refresh"
        >
          {{ loading ? t("common.loading") : t("admin.health.refresh") }}
        </button>
      </div>

      <div
        v-if="current"
        class="rounded-xl border p-4 mb-8"
        :class="current.healthy ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-red-500/40 bg-red-500/10'"
        role="status"
      >
        <p class="font-semibold" :class="current.healthy ? 'text-emerald-300' : 'text-red-300'">
          {{ current.healthy ? t("admin.health.all_ok") : t("admin.health.failing") }}
        </p>
        <p class="text-xs text-gray-400 mt-1">
          {{ t("admin.health.checked_at", { time: formatTime(current.checked_at) }) }}
        </p>
      </div>

      <div v-if="current" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
        <div
          v-for="(check, name) in current.checks"
          :key="name"
          class="bg-gray-800 rounded-xl p-4 border"
          :class="check.ok ? 'border-gray-700' : 'border-red-500/60'"
        >
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-white">{{ name }}</p>
            <span
              class="text-xs font-medium rounded-full px-2 py-0.5"
              :class="check.ok ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'"
            >
              {{ check.ok ? "OK" : "FAILED" }}
            </span>
          </div>
          <p class="text-xs text-gray-400 mt-2">{{ check.detail }}</p>
          <p class="text-xs text-gray-600 mt-1">{{ check.duration_ms }} ms</p>
        </div>

        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <p class="text-sm font-semibold text-white">{{ t("admin.health.error_rate") }}</p>
          <p class="text-2xl font-bold mt-1" :class="errorRateCritical ? 'text-red-400' : 'text-white'">
            {{ current.error_rate.current_hour }}
          </p>
          <p class="text-xs text-gray-400">
            {{
              t("admin.health.error_rate_detail", {
                previous: current.error_rate.previous_hour,
                threshold: current.error_rate.threshold,
              })
            }}
          </p>
        </div>
      </div>

      <h2 class="text-lg font-semibold text-white mb-3">{{ t("admin.health.history") }}</h2>
      <p v-if="history.length === 0" class="text-sm text-gray-500">
        {{ t("admin.health.history_empty") }}
      </p>
      <div v-else class="space-y-1 max-h-96 overflow-y-auto custom-scrollbar pr-2">
        <div
          v-for="snapshot in history"
          :key="snapshot.id"
          class="flex items-center gap-3 text-sm rounded-md px-3 py-1.5 bg-gray-800/60"
        >
          <span
            class="inline-block h-2.5 w-2.5 rounded-full shrink-0"
            :class="snapshot.healthy ? 'bg-emerald-400' : 'bg-red-400'"
          />
          <span class="text-gray-300 font-mono text-xs">{{ formatTime(snapshot.created_at) }}</span>
          <span v-if="!snapshot.healthy" class="text-red-300 text-xs truncate">
            {{ failedCheckNames(snapshot) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import api from "../../services/api";

type HealthCheck = { ok: boolean; detail: string; duration_ms: number };
type HealthReport = {
  healthy: boolean;
  checked_at: string;
  checks: Record<string, HealthCheck>;
  error_rate: { current_hour: number; previous_hour: number; threshold: number };
};
type HealthSnapshot = {
  id: number;
  healthy: boolean;
  checks: Record<string, HealthCheck>;
  created_at: string;
};

/** Admin system health dashboard (P1 phase 8). Polls the scheduled snapshots. */
const { t, locale } = useI18n();

const current = ref<HealthReport | null>(null);
const history = ref<HealthSnapshot[]>([]);
const loading = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;

const errorRateCritical = computed(
  () => !!current.value && current.value.error_rate.current_hour >= current.value.error_rate.threshold,
);

async function refresh() {
  loading.value = true;
  try {
    const [live, past] = await Promise.all([
      api.get<{ data: HealthReport }>("/admin/system-health"),
      api.get<{ data: HealthSnapshot[] }>("/admin/system-health/history", { params: { limit: 50 } }),
    ]);
    current.value = live.data.data;
    history.value = past.data.data;
  } catch (error) {
    console.error("System health fetch failed:", error);
  } finally {
    loading.value = false;
  }
}

function failedCheckNames(snapshot: HealthSnapshot): string {
  return Object.entries(snapshot.checks)
    .filter(([, check]) => !check.ok)
    .map(([name]) => name)
    .join(", ");
}

function formatTime(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(locale.value);
}

onMounted(() => {
  void refresh();
  // StoreReports-style polling while the page is open.
  timer = setInterval(() => {
    void refresh();
  }, 30_000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>
