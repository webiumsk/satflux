<template>
  <div
    class="min-h-0 flex-1 max-md:flex-none max-md:overflow-visible md:overflow-y-auto overscroll-y-contain custom-scrollbar"
  >
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <router-link
            to="/admin"
            class="text-sm text-gray-400 hover:text-amber-400 mb-2 inline-block"
          >
            ← {{ t("admin.platform_settings.back") }}
          </router-link>
          <h1 class="text-3xl font-bold text-white">
            {{ t("admin.platform_settings.title") }}
          </h1>
          <p class="text-gray-400 mt-1">
            {{ t("admin.platform_settings.description") }}
          </p>
          <nav
            class="mt-5 flex flex-wrap gap-2"
            :aria-label="t('admin.dashboard.admin_nav_label')"
          >
            <router-link
              to="/admin"
              class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border bg-gray-800 border-gray-700 text-gray-300 hover:border-gray-600 hover:text-white transition-colors"
            >
              {{ t("header.admin") }}
            </router-link>
            <router-link
              to="/admin/users"
              class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border bg-gray-800 border-gray-700 text-gray-300 hover:border-gray-600 hover:text-white transition-colors"
            >
              {{ t("admin.dashboard.user_management") }}
            </router-link>
            <router-link
              to="/admin/settings"
              class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border bg-amber-600/20 border-amber-500/40 text-amber-200 transition-colors"
            >
              {{ t("admin.dashboard.platform_settings") }}
            </router-link>
          </nav>
        </div>
        <button
          type="button"
          class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-medium disabled:opacity-50 shrink-0"
          :disabled="saving || loading"
          @click="save"
        >
          {{ saving ? t("admin.platform_settings.saving") : t("common.save") }}
        </button>
      </div>

      <div
        v-if="error"
        class="mb-6 rounded-lg border border-red-500/50 bg-red-900/20 px-4 py-3 text-red-200 text-sm"
      >
        {{ error }}
      </div>

      <div
        v-if="success"
        class="mb-6 rounded-lg border border-emerald-500/50 bg-emerald-900/20 px-4 py-3 text-emerald-200 text-sm"
      >
        {{ t("admin.platform_settings.saved") }}
      </div>

      <div v-if="loading" class="text-gray-400">{{ t("common.loading") }}</div>

      <form v-else class="flex flex-col lg:flex-row gap-6 lg:gap-8" @submit.prevent="save">
        <!-- Mobile / tablet: horizontal tabs -->
        <nav
          class="lg:hidden flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 custom-scrollbar"
          role="tablist"
          :aria-label="t('admin.platform_settings.nav_label')"
        >
          <button
            v-for="group in groups"
            :key="group"
            type="button"
            role="tab"
            class="shrink-0 px-3 py-2 rounded-lg text-sm font-medium border transition-colors whitespace-nowrap"
            :class="
              activeGroup === group
                ? 'bg-amber-600/20 border-amber-500/50 text-amber-200'
                : 'bg-gray-800 border-gray-700 text-gray-400 hover:text-white hover:border-gray-600'
            "
            :aria-selected="activeGroup === group"
            @click="setActiveGroup(group)"
          >
            {{ t(`admin.platform_settings.groups.${group}`) }}
          </button>
        </nav>

        <!-- Desktop: sidebar -->
        <aside class="hidden lg:block w-56 xl:w-64 shrink-0">
          <nav
            class="sticky top-6 space-y-1"
            role="tablist"
            :aria-label="t('admin.platform_settings.nav_label')"
          >
            <button
              v-for="group in groups"
              :key="group"
              type="button"
              role="tab"
              class="w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center justify-between gap-2"
              :class="
                activeGroup === group
                  ? 'bg-amber-600/20 text-amber-200 border border-amber-500/40'
                  : 'text-gray-400 hover:text-white hover:bg-gray-800 border border-transparent'
              "
              :aria-selected="activeGroup === group"
              @click="setActiveGroup(group)"
            >
              <span class="min-w-0 truncate">
                {{ t(`admin.platform_settings.groups.${group}`) }}
              </span>
              <span
                class="text-xs tabular-nums shrink-0 px-1.5 py-0.5 rounded bg-gray-800/80 text-gray-500"
                :class="{ 'text-amber-400/80': activeGroup === group }"
              >
                {{ fieldsForGroup(group).length }}
              </span>
            </button>
          </nav>
        </aside>

        <!-- Active section -->
        <section
          class="flex-1 min-w-0 bg-gray-800 rounded-xl border border-gray-700 p-5 sm:p-6"
          role="tabpanel"
          :aria-label="t(`admin.platform_settings.groups.${activeGroup}`)"
        >
          <h2 class="text-xl font-semibold text-white mb-1">
            {{ t(`admin.platform_settings.groups.${activeGroup}`) }}
          </h2>
          <p class="text-sm text-gray-500 mb-5">
            {{ t("admin.platform_settings.section_hint", { count: fieldsForGroup(activeGroup).length }) }}
          </p>

          <p
            v-if="activeGroup === 'invoicing'"
            class="text-xs text-amber-300/90 bg-amber-900/20 border border-amber-700/40 rounded-lg px-3 py-2 mb-5"
          >
            {{ t("admin.platform_settings.warnings.invoicing_local_first") }}
          </p>
          <p
            v-if="activeGroup === 'btcpay'"
            class="text-xs text-amber-300/90 bg-amber-900/20 border border-amber-700/40 rounded-lg px-3 py-2 mb-5"
          >
            {{ t("admin.platform_settings.warnings.btcpay_public_url") }}
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            <div
              v-for="field in fieldsForGroup(activeGroup)"
              :key="field.key"
              class="space-y-1"
              :class="{ 'md:col-span-2': field.secret || field.type === 'csv_array' }"
            >
              <label class="block text-sm font-medium text-gray-300">
                {{ fieldLabel(field.key) }}
              </label>

              <template v-if="field.secret">
                <input
                  v-model="secretDrafts[field.key]"
                  type="password"
                  autocomplete="off"
                  class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm font-mono"
                  :placeholder="
                    secretSet(field.key)
                      ? t('admin.platform_settings.secret_placeholder_set')
                      : t('admin.platform_settings.secret_placeholder')
                  "
                />
                <p v-if="secretSet(field.key)" class="text-xs text-emerald-400">
                  {{ t("admin.platform_settings.secret_configured") }}
                </p>
              </template>

              <template v-else-if="field.type === 'bool'">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input
                    v-model="form[field.key]"
                    type="checkbox"
                    class="rounded border-gray-600 bg-gray-900 text-amber-500"
                  />
                  <span class="text-sm text-gray-400">
                    {{ form[field.key] ? t("common.yes") : t("common.no") }}
                  </span>
                </label>
              </template>

              <template v-else-if="field.type === 'int' || field.type === 'nullable_int'">
                <input
                  v-model="form[field.key]"
                  type="number"
                  min="0"
                  class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm"
                />
              </template>

              <template v-else>
                <input
                  v-model="form[field.key]"
                  type="text"
                  class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm"
                  :class="{ 'font-mono text-xs': field.type === 'url' || field.type === 'uuid' }"
                />
              </template>

              <p v-if="fieldHint(field.key)" class="text-xs text-gray-500">
                {{ fieldHint(field.key) }}
              </p>
            </div>
          </div>
        </section>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import api from "../../services/api";

type PlatformField = {
  key: string;
  group: string;
  type: string;
  secret: boolean;
};

type PlatformPayload = {
  groups: string[];
  fields: PlatformField[];
  values: Record<string, unknown>;
};

const { t, te } = useI18n();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const error = ref("");
const success = ref(false);
const groups = ref<string[]>([]);
const fields = ref<PlatformField[]>([]);
const form = reactive<Record<string, unknown>>({});
const secretDrafts = reactive<Record<string, string>>({});
const setFlags = reactive<Record<string, boolean>>({});
const activeGroup = ref("auth");

function fieldLabel(key: string): string {
  const i18nKey = `admin.platform_settings.fields.${key.replace(/\./g, "_")}`;
  if (te(i18nKey)) {
    return t(i18nKey);
  }
  return key.split(".").slice(-1).join(" ");
}

function fieldHint(key: string): string {
  const i18nKey = `admin.platform_settings.hints.${key.replace(/\./g, "_")}`;
  return te(i18nKey) ? t(i18nKey) : "";
}

function fieldsForGroup(group: string): PlatformField[] {
  return fields.value.filter((f) => f.group === group);
}

function setActiveGroup(group: string): void {
  if (!groups.value.includes(group)) {
    return;
  }
  activeGroup.value = group;
  void router.replace({ query: { ...route.query, section: group } });
}

function secretSetFlagKey(key: string): string {
  return `${key.replace(/\./g, "_")}_set`;
}

function secretSet(key: string): boolean {
  return Boolean(setFlags[secretSetFlagKey(key)]);
}

function resolveInitialGroup(available: string[]): string {
  const fromQuery = route.query.section;
  if (typeof fromQuery === "string" && available.includes(fromQuery)) {
    return fromQuery;
  }
  return available[0] ?? "auth";
}

function applyPayload(data: PlatformPayload): void {
  groups.value = data.groups;
  fields.value = data.fields;
  activeGroup.value = resolveInitialGroup(data.groups);

  for (const field of data.fields) {
    if (field.secret) {
      setFlags[secretSetFlagKey(field.key)] = Boolean(
        data.values[secretSetFlagKey(field.key)],
      );
      secretDrafts[field.key] = "";
    } else {
      form[field.key] = data.values[field.key] ?? "";
    }
  }
}

async function load(): Promise<void> {
  loading.value = true;
  error.value = "";
  try {
    const response = await api.get<{ data: PlatformPayload }>(
      "/admin/platform-settings",
    );
    applyPayload(response.data.data);
  } catch (e: unknown) {
    error.value =
      e instanceof Error ? e.message : t("admin.platform_settings.load_error");
  } finally {
    loading.value = false;
  }
}

async function save(): Promise<void> {
  saving.value = true;
  error.value = "";
  success.value = false;

  const payload: Record<string, unknown> = {};

  for (const field of fields.value) {
    if (field.secret) {
      const draft = (secretDrafts[field.key] ?? "").trim();
      if (draft !== "") {
        payload[field.key] = draft;
      }
      continue;
    }

    if (field.type === "bool") {
      payload[field.key] = Boolean(form[field.key]);
    } else if (field.type === "int") {
      payload[field.key] = Number(form[field.key] ?? 0);
    } else if (field.type === "nullable_int") {
      const raw = form[field.key];
      payload[field.key] =
        raw === "" || raw === null || raw === undefined ? null : Number(raw);
    } else {
      payload[field.key] = form[field.key] ?? "";
    }
  }

  try {
    const response = await api.patch<{ data: PlatformPayload }>(
      "/admin/platform-settings",
      payload,
    );
    applyPayload(response.data.data);
    success.value = true;
    setTimeout(() => {
      success.value = false;
    }, 3000);
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } };
    error.value =
      err.response?.data?.message ?? t("admin.platform_settings.save_error");
  } finally {
    saving.value = false;
  }
}

watch(
  () => route.query.section,
  (section) => {
    if (typeof section === "string" && groups.value.includes(section)) {
      activeGroup.value = section;
    }
  },
);

onMounted(() => {
  void load();
});
</script>
