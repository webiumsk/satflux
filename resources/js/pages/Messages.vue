<template>
  <div class="min-h-0 flex-1 max-md:flex-none max-md:overflow-visible md:overflow-y-auto overscroll-y-contain custom-scrollbar">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-white">{{ t("messages.title") }}</h1>
          <p class="mt-1 text-sm text-gray-400">
            {{ t("messages.subtitle") }}
          </p>
        </div>
        <button
          v-if="available && unreadCount > 0 && messages.length > 0"
          type="button"
          :disabled="markingAll"
          class="inline-flex items-center px-4 py-2 text-sm font-medium text-indigo-400 hover:text-indigo-300 border border-indigo-500/40 rounded-lg hover:bg-indigo-500/10 transition-colors disabled:opacity-50"
          @click="markAllAsRead"
        >
          <svg
            v-if="markingAll"
            class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-400"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          {{ t("messages.mark_all_read") }}
        </button>
      </div>

      <!-- Filters stay available while a new page loads. -->
      <div class="flex flex-col sm:flex-row sm:items-end gap-4 rounded-xl border border-gray-700 bg-gray-800/50 p-4">
        <div class="flex-1">
          <label for="message-category" class="block text-sm font-medium text-gray-300 mb-2">{{ t("messages.filter_category") }}</label>
          <select
            id="message-category"
            v-model="category"
            class="w-full rounded-lg border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @change="resetFilters"
          >
            <option v-for="option in categories" :key="option" :value="option">{{ t(`messages.filter_${option}`) }}</option>
          </select>
        </div>
        <label class="inline-flex items-center gap-2 py-2 text-sm text-gray-200 cursor-pointer">
          <input v-model="unreadOnly" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500" @change="resetFilters" />
          {{ t("messages.filter_unread") }}
        </label>
      </div>

      <div v-if="actionError" role="alert" class="rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-200">{{ actionError }}</div>
      <p v-if="scanLimited && !loading && !loadError" role="status" class="text-sm text-amber-200">{{ t("messages.history_limited") }}</p>

      <!-- Loading -->
      <div v-if="loading" role="status" class="flex justify-center py-12">
        <span class="sr-only">{{ t("messages.loading") }}</span>
        <svg
          class="animate-spin h-10 w-10 text-indigo-500"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
      </div>

      <div v-else-if="loadError" role="alert" class="rounded-xl border border-red-500/40 bg-red-500/10 p-6 text-center">
        <p class="text-sm text-red-200">{{ t("messages.load_error") }}</p>
        <button type="button" class="mt-3 rounded-lg border border-gray-600 px-3 py-2 text-sm text-white hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400" @click="loadMessages(meta.current_page)">{{ t("messages.retry") }}</button>
      </div>

      <!-- Not available (no BTCPay API key or old key without notification permissions) -->
      <div
        v-else-if="!available"
        class="bg-gray-800/50 border border-amber-500/30 rounded-2xl p-12 text-center"
      >
        <div class="flex justify-center mb-4">
          <div class="w-16 h-16 rounded-full bg-amber-500/10 flex items-center justify-center">
            <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
        <h3 class="text-lg font-medium text-white mb-2">{{ t("messages.not_available_title") }}</h3>
        <p class="text-gray-400 text-sm">{{ t("messages.not_available_subtitle") }}</p>
      </div>

      <!-- Empty state -->
      <div
        v-else-if="messages.length === 0 && !hasMore"
        class="bg-gray-800/50 border border-gray-700 rounded-2xl p-12 text-center"
      >
        <div class="flex justify-center mb-4">
          <div class="w-16 h-16 rounded-full bg-gray-700/50 flex items-center justify-center">
            <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
          </div>
        </div>
        <h3 class="text-lg font-medium text-white mb-2">{{ t(filtered ? "messages.filtered_empty_title" : "messages.empty_title") }}</h3>
        <p class="text-gray-400 text-sm">{{ t(filtered ? "messages.filtered_empty_subtitle" : "messages.empty_subtitle") }}</p>
      </div>

      <!-- Message list -->
      <div v-else class="space-y-3">
        <p v-if="messages.length === 0" role="status" class="rounded-xl border border-gray-700 p-6 text-sm text-gray-300">{{ t("messages.scan_empty") }}</p>
        <article
          v-for="msg in messages"
          :key="msg.id"
          :class="[
            'rounded-xl border p-4 transition-colors',
            isCritical(msg) && !msg.read_at
              ? 'bg-red-500/10 border-red-500/60 hover:border-red-400 ring-1 ring-red-500/30'
              : msg.read_at
                ? 'bg-gray-900/40 border-gray-700'
                : 'bg-indigo-500/10 border-indigo-400/70 border-l-4',
          ]"
        >
          <div class="flex items-start gap-4">
            <div
              :class="[
                'flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center',
                getTypeIconBg(messageIconType(msg)),
              ]"
            >
              <svg class="w-5 h-5" :class="getTypeIconColor(messageIconType(msg))" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getTypeIconPath(messageIconType(msg))" />
              </svg>
            </div>
            <div class="flex-1 min-w-0 break-words [overflow-wrap:anywhere]">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  v-if="isCritical(msg)"
                  class="inline-flex items-center rounded-md bg-red-500/20 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-red-300"
                  >{{ t("messages.security_label") }}</span
                >
                <h3 class="text-sm font-semibold text-white">{{ msg.title }}</h3>
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="msg.read_at ? 'bg-gray-700/60 text-gray-300' : isCritical(msg) ? 'bg-red-500/20 text-red-200' : 'bg-indigo-500/20 text-indigo-200'"
                >
                  <span v-if="!msg.read_at" aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-current" />
                  {{ t(msg.read_at ? "messages.read" : "messages.unread") }}
                </span>
              </div>
              <p
                v-if="msg.body"
                class="mt-1 text-sm"
                :class="isCritical(msg) ? 'text-red-100/90' : 'text-gray-400 line-clamp-2'"
              >{{ msg.body }}</p>
              <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <time :datetime="msg.created_at" class="text-xs text-gray-400">{{ formatDateTime(msg.created_at) }}</time>
                <div class="flex flex-wrap items-center gap-3">
                  <button
                    v-if="!msg.read_at"
                    type="button"
                    :disabled="markingIds.has(msg.id) || markingAll"
                    class="rounded px-1 py-1 text-xs font-medium text-gray-200 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400 disabled:opacity-50"
                    @click="markMessageRead(msg)"
                  >{{ t("messages.mark_read") }}</button>
                  <a
                    v-if="safeLink(msg.link)"
                    :href="safeLink(msg.link)!.href"
                    :target="safeLink(msg.link)!.external ? '_blank' : undefined"
                    :rel="safeLink(msg.link)!.external ? 'noopener noreferrer' : undefined"
                    class="rounded px-1 py-1 text-xs font-medium text-indigo-300 hover:text-indigo-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400"
                    @click="openMessage(msg, $event)"
                  >{{ msg.link_text || t(msg.category === 'invoices' ? "messages.view_invoice" : "messages.view") }}</a>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && !loadError && available && (meta.current_page > 1 || hasMore)" class="flex justify-center gap-2 pt-4">
        <button
          type="button"
          :disabled="loading || meta.current_page <= 1"
          class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-600 text-gray-400 hover:text-white hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed"
          @click="loadPage(meta.current_page - 1)"
        >
          {{ t("messages.previous") }}
        </button>
        <span class="px-3 py-1.5 text-sm text-gray-400">
          {{ t("messages.page_label", { page: meta.current_page }) }}
        </span>
        <button
          type="button"
          :disabled="loading || !hasMore"
          class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-600 text-gray-400 hover:text-white hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed"
          @click="loadPage(meta.current_page + 1)"
        >
          {{ t(messages.length === 0 ? "messages.continue_search" : "messages.next") }}
        </button>
      </div>
    </div>
  </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import { isAxiosError } from "axios";
import api from "../services/api";
import { formatDateTime } from "../utils/formatDateTime";

const { t } = useI18n();
const router = useRouter();

interface Message {
  id: string | number;
  type: string;
  category?: "critical" | "invoices" | "other";
  title: string;
  body: string | null;
  link: string | null;
  link_text: string | null;
  read_at: string | null;
  created_at: string;
}

const categories = ["all", "critical", "invoices", "other"] as const;
const category = ref<(typeof categories)[number]>("all");
const unreadOnly = ref(false);
const filtered = computed(() => category.value !== "all" || unreadOnly.value);
const loading = ref(true);
const loadError = ref(false);
const actionError = ref("");
const scanLimited = ref(false);
const hasMore = ref(false);
const pageCursors = ref<(string | null)[]>([null]);
const markingAll = ref(false);
const markingIds = ref(new Set<string | number>());
const messages = ref<Message[]>([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const unreadCount = ref(0);
const available = ref(true);
let requestVersion = 0;
let countVersion = 0;
let active = true;

const isCritical = (msg: Message) => msg.category === "critical" || msg.type === "security";
const messageIconType = (msg: Message) => msg.category === "invoices" ? "invoice" : isCritical(msg) ? "security" : msg.type;

const loadMessages = async (page = 1) => {
  const version = ++requestVersion;
  const cursor = pageCursors.value[page - 1];
  loading.value = true;
  loadError.value = false;
  meta.value.current_page = page;
  try {
    const { data } = await api.get("/messages", { params: {
      page, per_page: 20, category: category.value, unread: unreadOnly.value ? 1 : 0,
      ...(cursor ? { cursor } : {}),
    } });
    if (!active || version !== requestVersion) return;
    messages.value = data.data ?? [];
    meta.value = data.meta ?? meta.value;
    scanLimited.value = data.meta?.scan_limited === true;
    hasMore.value = data.meta?.has_more === true && typeof data.meta?.next_cursor === "string";
    pageCursors.value = pageCursors.value.slice(0, page);
    if (hasMore.value) pageCursors.value[page] = data.meta.next_cursor;
    available.value = data.available !== false;
    // A read action may empty the final page of the unread filter.
    if (messages.value.length === 0 && page > 1 && !hasMore.value && unreadOnly.value) {
      await loadMessages(page - 1);
    }
  } catch (error) {
    if (!active || version !== requestVersion) return;
    if (cursor && isAxiosError<{ errors?: { cursor?: unknown } }>(error)
      && error.response?.status === 422 && Array.isArray(error.response.data?.errors?.cursor)) {
      // Cursor expiry or changed feed options require a fresh first page, once.
      pageCursors.value = [null];
      await loadMessages(1);
      return;
    }
    loadError.value = true;
  } finally {
    if (active && version === requestVersion) loading.value = false;
  }
};

const loadMessageCount = async () => {
  const version = ++countVersion;
  try {
    const { data } = await api.get("/messages/count");
    if (active && version === countVersion) unreadCount.value = data.data?.unread ?? 0;
  } catch {
    // Keep the last confirmed count; the list has its own availability/error state.
  }
};

const safeLink = (link: string | null): { href: string; external: boolean } | null => {
  if (!link || Array.from(link).some(char => char === "\\" || char.charCodeAt(0) <= 32 || char.charCodeAt(0) === 127)) return null;
  if (!/^https?:\/\//i.test(link) && !/^\/(?!\/)/.test(link)) return null;
  try {
    const url = new URL(link, window.location.origin);
    if (!["http:", "https:"].includes(url.protocol) || url.username || url.password) return null;
    const external = url.origin !== window.location.origin;
    return { href: external ? url.href : `${url.pathname}${url.search}${url.hash}`, external };
  } catch {
    return null;
  }
};

const markMessageRead = async (msg: Message) => {
  if (msg.read_at || markingIds.value.has(msg.id) || markingAll.value) return;
  markingIds.value.add(msg.id);
  actionError.value = "";
  try {
    await api.patch(`/messages/${encodeURIComponent(msg.id)}/read`);
    window.dispatchEvent(new CustomEvent("messages-updated"));
    if (!active) return;
    msg.read_at = new Date().toISOString();
    await Promise.all([loadMessageCount(), ...(unreadOnly.value ? [loadMessages(meta.value.current_page)] : [])]);
  } catch {
    if (active) actionError.value = t("messages.read_error");
  } finally {
    markingIds.value.delete(msg.id);
  }
};

const openMessage = (msg: Message, event: MouseEvent) => {
  const destination = safeLink(msg.link);
  if (!destination) return;
  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    void markMessageRead(msg);
    return;
  }
  event.preventDefault();
  if (destination.external) window.open(destination.href, "_blank", "noopener,noreferrer");
  else void router.push(destination.href);
  void markMessageRead(msg);
};

const markAllAsRead = async () => {
  if (markingAll.value) return;
  markingAll.value = true;
  actionError.value = "";
  try {
    await api.post("/messages/mark-all-read");
    if (!active) return;
    window.dispatchEvent(new CustomEvent("messages-updated"));
    // Reload confirmed state rather than claiming every message was marked locally.
    await Promise.all([loadMessages(1), loadMessageCount()]);
  } catch {
    if (active) actionError.value = t("messages.read_error");
  } finally {
    markingAll.value = false;
  }
};

const resetFilters = () => {
  pageCursors.value = [null];
  void loadMessages(1);
};

const loadPage = (page: number) => {
  void loadMessages(page);
};

onBeforeUnmount(() => { active = false; requestVersion += 1; countVersion += 1; });

const getTypeIconBg = (type: string) => {
  const map: Record<string, string> = {
    success: "bg-green-500/20",
    warning: "bg-amber-500/20",
    invoice: "bg-orange-500/20",
    subscription: "bg-purple-500/20",
    support: "bg-blue-500/20",
    security: "bg-red-500/20",
  };
  return map[type] ?? "bg-indigo-500/20";
};

const getTypeIconColor = (type: string) => {
  const map: Record<string, string> = {
    success: "text-green-400",
    warning: "text-amber-400",
    invoice: "text-orange-400",
    subscription: "text-purple-400",
    support: "text-blue-400",
    security: "text-red-400",
  };
  return map[type] ?? "text-indigo-400";
};

const getTypeIconPath = (type?: string) => {
  if (type === "security") {
    // Shield with exclamation
    return "M12 9v2m0 4h.01M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z";
  }
  // Bell icon path
  return "M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9";
};

onMounted(async () => {
  await Promise.all([loadMessages(), loadMessageCount()]);
});
</script>
