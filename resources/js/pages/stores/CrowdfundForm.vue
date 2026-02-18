<template>
  <!-- Content Container -->
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Crowdfund Form -->
    <form id="crowdfund-form" @submit.prevent="handleSubmit" class="space-y-6">
      <!-- General Information -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <h2 class="text-xl font-bold text-white mb-4">General Information</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label
                for="appName"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                App Name <span class="text-red-400">*</span>
              </label>
              <input
                id="appName"
                v-model="form.appName"
                type="text"
                required
                class="block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              />
            </div>

            <div>
              <label
                for="displayTitle"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Display Title <span class="text-red-400">*</span>
              </label>
              <input
                id="displayTitle"
                v-model="form.displayTitle"
                type="text"
                required
                class="block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              />
            </div>
          </div>

          <div>
            <label
              for="tagline"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Tagline
            </label>
            <input
              id="tagline"
              v-model="form.tagline"
              type="text"
              class="block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">
              Featured Image URL
            </label>
            <div class="flex gap-3">
              <input
                v-model="form.featuredImageUrl"
                type="text"
                placeholder="https://example.com/image.jpg"
                class="flex-1 block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              />
              <button
                type="button"
                @click="handleBrowseImage"
                class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 hover:text-white transition-all hover:scale-105 shadow-sm"
              >
                Browse...
              </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">
              Image displayed on the crowdfund page header.
            </p>
          </div>

          <div
            class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl"
          >
            <input
              id="makePublic"
              v-model="form.makePublic"
              type="checkbox"
              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
            />
            <div class="ml-3">
              <label
                for="makePublic"
                class="block text-sm font-medium text-white cursor-pointer select-none"
              >
                Make Crowdfund Public
              </label>
              <p class="text-gray-400 text-xs mt-0.5">
                The crowdfund will be visible to anyone.
              </p>
            </div>
          </div>

          <div>
            <label
              for="description"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Description <span class="text-red-400">*</span>
            </label>
            <textarea
              id="description"
              v-model="form.description"
              rows="6"
              required
              class="block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            ></textarea>
          </div>
        </div>

        <!-- Goal Section -->

        <div class="p-6 md:p-8 space-y-6">
          <h2 class="text-xl font-bold text-white mb-4">Goal</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label
                for="targetAmount"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Target Amount
              </label>
              <input
                id="targetAmount"
                v-model="form.targetAmount"
                type="number"
                step="0.01"
                min="0"
                class="block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              />
            </div>

            <div>
              <label
                for="currency"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Currency
              </label>
              <Select
                id="currency"
                v-model="form.currency"
                :options="currencyOptions"
                :placeholder="`Use store default (${store?.default_currency || 'EUR'})`"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label
                for="startDate"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Start date
              </label>
              <DatePicker
                id="startDate"
                v-model="form.startDate"
                type="datetime"
                placeholder="Select start date..."
              />
            </div>

            <div>
              <label
                for="endDate"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                End date
              </label>
              <DatePicker
                id="endDate"
                v-model="form.endDate"
                type="datetime"
                placeholder="No end date set"
                position="right"
              />
            </div>

            <!-- Recurring Goal -->

            <div class="bg-gray-900 border border-gray-700 p-4 rounded-xl">
              <div class="flex items-center">
                <input
                  id="recurringGoal"
                  v-model="form.recurringGoal"
                  type="checkbox"
                  class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                />
                <div class="ml-3">
                  <label
                    for="recurringGoal"
                    class="block text-sm font-medium text-white select-none cursor-pointer"
                  >
                    Recurring Goal
                  </label>
                  <p class="text-gray-400 text-xs mt-0.5">
                    Reset goal after a specific period of time, based on your
                    crowdfund's start date.
                  </p>
                </div>
              </div>

              <div
                v-if="form.recurringGoal"
                class="mt-4 ml-8 grid grid-cols-1 sm:grid-cols-2 gap-4"
              >
                <div class="flex items-center gap-3">
                  <label
                    for="resetEveryAmount"
                    class="block text-sm font-medium text-gray-300 whitespace-nowrap"
                  >
                    Reset goal every
                  </label>
                  <input
                    id="resetEveryAmount"
                    v-model.number="form.resetEveryAmount"
                    type="number"
                    min="1"
                    class="block w-24 px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                  <Select
                    v-model="form.resetEveryUnit"
                    :options="unitOptions"
                    class="min-w-[120px]"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Perks Section -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <div
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
          >
            <h2 class="text-xl font-bold text-white">Perks</h2>
            <div
              class="flex bg-gray-700/50 rounded-lg p-1 self-start sm:self-auto"
            >
              <button
                type="button"
                @click="perksViewMode = 'editor'"
                :class="[
                  'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                  perksViewMode === 'editor'
                    ? 'bg-gray-600 text-white shadow-sm'
                    : 'text-gray-400 hover:text-white hover:bg-gray-600/50',
                ]"
              >
                Editor
              </button>
              <button
                type="button"
                @click="perksViewMode = 'code'"
                :class="[
                  'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                  perksViewMode === 'code'
                    ? 'bg-gray-600 text-white shadow-sm'
                    : 'text-gray-400 hover:text-white hover:bg-gray-600/50',
                ]"
              >
                Code
              </button>
            </div>
          </div>

          <!-- Editor View -->
          <div v-if="perksViewMode === 'editor'">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
              <div
                v-for="(perk, index) in perks"
                :key="index"
                class="group relative bg-gray-900 border border-gray-700 rounded-xl p-4 cursor-pointer hover:border-indigo-500 hover:shadow-lg transition-all duration-200"
                @click="editPerk(index)"
              >
                <div class="flex items-start gap-4">
                  <div
                    class="w-16 h-16 bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0 border border-gray-700 group-hover:border-indigo-500/30 transition-colors"
                  >
                    <img
                      v-if="perk.imageUrl"
                      :src="perk.imageUrl"
                      :alt="perk.title"
                      class="w-full h-full object-cover rounded-lg"
                    />
                    <svg
                      v-else
                      class="w-8 h-8 text-gray-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                      />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-white truncate pr-6">
                      {{ perk.title || "Custom" }}
                    </h3>
                    <p class="text-sm text-indigo-400 font-medium mt-1">
                      <span v-if="perk.priceType === 'Minimum'"
                        >Min. {{ perk.price || 0 }}
                        {{
                          form.currency || store?.default_currency || "EUR"
                        }}</span
                      >
                      <span v-else
                        >{{ perk.price || 0 }}
                        {{
                          form.currency || store?.default_currency || "EUR"
                        }}</span
                      >
                    </p>
                    <p
                      v-if="perk.description"
                      class="text-xs text-gray-500 mt-2 line-clamp-2"
                    >
                      {{ perk.description }}
                    </p>
                  </div>
                  <!-- Remove Button (Top Right) -->
                  <button
                    @click.stop="removePerk(index)"
                    class="absolute top-2 right-2 p-1.5 text-gray-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                    title="Remove Perk"
                  >
                    <svg
                      class="w-4 h-4"
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
                  </button>
                </div>
              </div>

              <!-- Add New Perk Card -->
              <button
                type="button"
                @click="addPerk"
                class="flex flex-col items-center justify-center gap-2 bg-gray-900 border border-gray-700 border-dashed rounded-xl p-4 cursor-pointer hover:border-indigo-500 hover:bg-gray-800/50 transition-all duration-200 min-h-[100px]"
              >
                <div
                  class="h-10 w-10 rounded-full bg-gray-800 flex items-center justify-center text-indigo-400 group-hover:text-indigo-300"
                >
                  <svg
                    class="w-6 h-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4v16m8-8H4"
                    />
                  </svg>
                </div>
                <span class="text-sm font-medium text-gray-300">Add Perk</span>
              </button>
            </div>
          </div>

          <!-- Code View -->
          <div v-else>
            <label
              for="perksJson"
              class="block text-sm font-medium text-gray-300 mb-2"
            >
              JSON Editor
            </label>
            <textarea
              id="perksJson"
              v-model="perksJson"
              rows="12"
              class="block w-full px-4 py-2 bg-gray-900 border border-gray-600 rounded-xl text-white font-mono text-xs placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              @blur="parsePerksJson"
            ></textarea>
            <p class="mt-2 text-xs text-gray-500">
              Edit raw JSON configuration for perks.
            </p>
          </div>
        </div>
      </div>

      <!-- Additional Options (Accordion) -->
      <AdditionalOptions
        v-model:contributions="form.contributions"
        v-model:crowdfundBehavior="form.crowdfundBehavior"
        v-model:checkout="form.checkout"
        v-model:advanced="form.advanced"
      />

      <!-- Archive/Unarchive & Delete Buttons -->
      <div class="border-t border-gray-700/50 pt-6 flex flex-wrap gap-3">
        <button
          type="button"
          @click="app?.archived ? $emit('unarchive') : $emit('archive')"
          :disabled="saving || props.archiving"
          class="inline-flex items-center px-4 py-2 border rounded-xl text-sm font-medium transition-colors"
          :class="
            app?.archived
              ? 'border-green-600 text-green-400 hover:bg-green-600 hover:text-white'
              : 'border-amber-600 text-amber-400 hover:bg-amber-600 hover:text-white'
          "
        >
          <svg
            class="w-4 h-4 mr-2"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              v-if="app?.archived"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
            <path
              v-else
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"
            />
          </svg>
          {{
            props.archiving
              ? app?.archived
                ? t("stores.unarchiving")
                : t("stores.archiving")
              : app?.archived
                ? t("stores.unarchive_app")
                : t("stores.archive_app")
          }}
        </button>
        <button
          type="button"
          @click="$emit('delete')"
          :disabled="saving"
          class="inline-flex items-center px-4 py-2 border border-red-600 rounded-xl text-sm font-medium text-red-400 hover:bg-red-600 hover:text-white transition-colors"
        >
          <svg
            class="w-4 h-4 mr-2"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
            />
          </svg>
          {{ t("stores.delete_app") }}
        </button>
      </div>
    </form>
  </div>

  <!-- Perk Edit Drawer -->
  <PerkEditDrawer
    :is-open="showPerkDrawer"
    :perk="currentPerk"
    :currency="form.currency || store?.default_currency || 'EUR'"
    :store-id="store?.id"
    @close="showPerkDrawer = false"
    @save="handlePerkSave"
  />
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import { useAppsStore } from "../../store/apps";
import { useFlashStore } from "../../store/flash";
import { currencies } from "../../data/currencies";
import PerkEditDrawer from "../../components/stores/PerkEditDrawer.vue";
import AdditionalOptions from "../../components/stores/AdditionalOptions.vue";
import DatePicker from "../../components/ui/DatePicker.vue";
import Select from "../../components/ui/Select.vue";

const props = defineProps<{
  app: any;
  store: any;
  archiving?: boolean;
}>();

const emit = defineEmits<{
  delete: [];
  archive: [];
  unarchive: [];
}>();

const form = ref({
  appName: "",
  displayTitle: "",
  tagline: "",
  featuredImageUrl: "",
  makePublic: true,
  description: "",
  targetAmount: "",
  currency: "",
  startDate: "",
  endDate: "",
  recurringGoal: false,
  resetEveryAmount: 1,
  resetEveryUnit: "Day",
  contributions: {
    sortByPopularity: false,
    displayRanking: false,
    displayValue: false,
    noAdditionalAfterTarget: false,
  },
  crowdfundBehavior: {
    countAllInvoices: false,
  },
  checkout: {
    requestContributorData: false,
  },
  advanced: {
    htmlLanguage: "",
    htmlMetaTags: "",
    enableSounds: false,
    enableAnimations: false,
    enableDiscussion: false,
    callbackNotificationUrl: "",
  },
});

const perks = ref<any[]>([]);
const perksJson = ref("[]");
const perksViewMode = ref<"editor" | "code">("editor");
const showPerkDrawer = ref(false);
const editingPerkIndex = ref<number | null>(null);
const saving = ref(false);
const error = ref("");
const success = ref("");
const { t } = useI18n();
const authStore = useAuthStore();
const flashStore = useFlashStore();
const appsStore = useAppsStore();
const router = useRouter();

const planCode = computed(
  () => (authStore.user?.plan?.code ?? "free") as string,
);
const userRole = computed(() => (authStore.user?.role ?? "") as string);
const canArchiveApp = computed(
  () =>
    planCode.value === "pro" ||
    planCode.value === "enterprise" ||
    userRole.value === "admin" ||
    userRole.value === "support",
);

const currencyOptions = computed(() => [
  {
    label: `Use store default (${props.store?.default_currency || "EUR"})`,
    value: "",
  },
  ...currencies.map((c) => ({ label: `${c.code} - ${c.name}`, value: c.code })),
]);

const unitOptions = [
  { label: "Hour", value: "Hour" },
  { label: "Day", value: "Day" },
  { label: "Week", value: "Week" },
  { label: "Month", value: "Month" },
  { label: "Year", value: "Year" },
];

const currentPerk = computed(() => {
  if (editingPerkIndex.value !== null && perks.value[editingPerkIndex.value]) {
    return perks.value[editingPerkIndex.value];
  }
  return null;
});

function handleBrowseImage() {
  // TODO: Implement image upload if needed globally or just use URL
  // Ideally this would trigger a media picker or specialized upload modal
  // For now we just focus the input
  const input = document.querySelector(
    'input[placeholder="https://example.com/image.jpg"]',
  ) as HTMLInputElement;
  if (input) input.focus();
}

function addPerk() {
  editingPerkIndex.value = null;
  showPerkDrawer.value = true;
}

function editPerk(index: number) {
  editingPerkIndex.value = index;
  showPerkDrawer.value = true;
}

function handlePerkSave(perk: any) {
  if (editingPerkIndex.value !== null) {
    perks.value[editingPerkIndex.value] = perk;
  } else {
    perks.value.push(perk);
  }
  editingPerkIndex.value = null;
  showPerkDrawer.value = false;
  updatePerksJson();
}

function removePerk(index: number) {
  if (confirm("Are you sure you want to remove this perk?")) {
    perks.value.splice(index, 1);
    updatePerksJson();
  }
}

function updatePerksJson() {
  perksJson.value = JSON.stringify(perks.value, null, 2);
}

function parsePerksJson() {
  try {
    const parsed = JSON.parse(perksJson.value);
    if (Array.isArray(parsed)) {
      perks.value = parsed;
    }
  } catch (e) {
    console.error("Invalid JSON in perks code view", e);
  }
}

async function handleSubmit() {
  saving.value = true;
  error.value = "";
  success.value = "";

  try {
    // If switching from code view, parse JSON first
    if (perksViewMode.value === "code") {
      try {
        const parsed = JSON.parse(perksJson.value);
        perks.value = Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        flashStore.error("Invalid JSON in perks code view");
        error.value = "";
        saving.value = false;
        return;
      }
    }

    // Prepare perks array
    const perksArray = perks.value
      .filter((p) => p.title) // Only include perks with titles
      .map((p) => {
        // Handle inventory:
        let inventory: number | null = null;
        if (
          p.inventory !== null &&
          p.inventory !== undefined &&
          p.inventory !== ""
        ) {
          const invNum = Number(p.inventory);
          if (!isNaN(invNum) && invNum >= 0) {
            inventory = invNum;
          }
        }

        return {
          id: p.id || generatePerkId(p.title),
          title: p.title,
          disabled: p.disabled || false,
          description: p.description || null,
          categories: p.categories
            ? String(p.categories)
                .split(",")
                .map((c: string) => c.trim())
                .filter((c: string) => c)
            : null,
          image: p.image || null,
          priceType: p.priceType || "Minimum",
          price:
            p.priceType !== "Free" && p.priceType !== "Topup"
              ? String(p.price || 1)
              : null,
          buyButtonText: p.buyButtonText || "Podporiť",
          inventory: inventory,
          taxRate:
            p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== ""
              ? String(p.taxRate)
              : null,
        };
      });

    // Convert dates to UNIX timestamps (seconds)
    let startDateTimestamp: number | null = null;
    if (form.value.startDate) {
      const date = new Date(form.value.startDate);
      if (!isNaN(date.getTime())) {
        startDateTimestamp = Math.floor(date.getTime() / 1000);
      }
    }

    let endDateTimestamp: number | null = null;
    if (form.value.endDate) {
      const date = new Date(form.value.endDate);
      if (!isNaN(date.getTime())) {
        endDateTimestamp = Math.floor(date.getTime() / 1000);
      }
    }

    // Build config object
    const config: any = {
      appName: form.value.appName,
      displayTitle: form.value.displayTitle,
      tagline: form.value.tagline || null,
      featuredImageUrl: form.value.featuredImageUrl || null,
      makePublic: form.value.makePublic,
      description: form.value.description,
      targetAmount: form.value.targetAmount
        ? String(form.value.targetAmount)
        : null,
      currency: form.value.currency || null,
      startDate: startDateTimestamp,
      endDate: endDateTimestamp,
      perks: perksArray,
      contributions: form.value.contributions,
      crowdfundBehavior: form.value.crowdfundBehavior,
      checkout: form.value.checkout,
      advanced: form.value.advanced,
    };

    // Handle recurring goal settings
    if (form.value.recurringGoal) {
      // Ensure minimum value of 1 for resetEveryAmount
      const resetAmount =
        form.value.resetEveryAmount >= 1 ? form.value.resetEveryAmount : 1;

      // Map resetEveryUnit to BTCPay format (Day, Hour, Week, Month, Year)
      config.crowdfundBehavior = {
        ...config.crowdfundBehavior,
        resetEvery: form.value.resetEveryUnit,
        resetEveryAmount: resetAmount,
      };
      // Also set at root level for BTCPay API
      config.resetEvery = form.value.resetEveryUnit;
      config.resetEveryAmount = resetAmount;

      // Ensure startDate is set when recurring goal is enabled
      if (!startDateTimestamp) {
        startDateTimestamp = Math.floor(Date.now() / 1000);
        config.startDate = startDateTimestamp;
      }
    } else {
      // If not recurring, set resetEveryAmount to 0 and resetEvery to 'Never' (disable reset)
      config.resetEveryAmount = 0;
      config.resetEvery = "Never";
      // Remove from crowdfundBehavior to avoid confusion
      if (config.crowdfundBehavior) {
        delete config.crowdfundBehavior.resetEvery;
        delete config.crowdfundBehavior.resetEveryAmount;
      }
    }

    // Save via API
    const appsStore = useAppsStore();
    await appsStore.updateApp(props.store.id, props.app.id, {
      name: form.value.appName,
      config: config,
    });

    flashStore.success(t("settings.settings_updated"));
    success.value = "";
    error.value = "";
  } catch (err: any) {
    const msg = err.response?.data?.message || t("settings.failed_to_update");
    flashStore.error(msg);
    error.value = "";
  } finally {
    saving.value = false;
  }
}

function generatePerkId(title: string): string {
  if (!title) return "";
  return title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

onMounted(() => {
  // Load existing data
  if (props.app?.config) {
    const config = props.app.config;
    form.value.appName = config.appName || props.app.name || "";
    form.value.displayTitle = config.displayTitle || config.title || "";
    form.value.tagline = config.tagline || "";
    form.value.featuredImageUrl =
      config.featuredImageUrl || config.featuredImage || "";
    form.value.makePublic =
      config.makePublic !== undefined ? config.makePublic : true;
    form.value.description = config.description || "";
    form.value.targetAmount = config.targetAmount || config.goal || "";
    form.value.currency = config.currency || "";

    // Handle dates - convert from UNIX timestamp or ISO string to datetime-local format
    if (config.startDate) {
      let startDate: Date;
      if (typeof config.startDate === "number") {
        // UNIX timestamp in seconds, convert to milliseconds
        startDate = new Date(config.startDate * 1000);
      } else {
        startDate = new Date(config.startDate);
      }
      if (!isNaN(startDate.getTime())) {
        const offset = startDate.getTimezoneOffset() * 60000;
        const localDate = new Date(startDate.getTime() - offset);
        form.value.startDate = localDate.toISOString().slice(0, 16);
      }
    }
    if (config.endDate) {
      let endDate: Date;
      if (typeof config.endDate === "number") {
        // UNIX timestamp in seconds, convert to milliseconds
        endDate = new Date(config.endDate * 1000);
      } else {
        endDate = new Date(config.endDate);
      }
      if (!isNaN(endDate.getTime())) {
        const offset = endDate.getTimezoneOffset() * 60000;
        const localDate = new Date(endDate.getTime() - offset);
        form.value.endDate = localDate.toISOString().slice(0, 16);
      }
    }

    // Handle recurring goal settings
    if (
      config.resetEveryAmount &&
      config.resetEveryAmount > 0 &&
      config.resetEvery &&
      config.resetEvery !== "Never"
    ) {
      form.value.recurringGoal = true;
      form.value.resetEveryAmount = config.resetEveryAmount;
      form.value.resetEveryUnit = config.resetEvery;
    } else {
      form.value.recurringGoal = false;
      form.value.resetEveryAmount = 1;
      form.value.resetEveryUnit = "Day";
    }

    if (config.contributions) {
      form.value.contributions = {
        sortByPopularity: config.contributions.sortByPopularity || false,
        displayRanking: config.contributions.displayRanking || false,
        displayValue: config.contributions.displayValue || false,
        noAdditionalAfterTarget:
          config.contributions.noAdditionalAfterTarget || false,
      };
    }
    if (config.crowdfundBehavior) {
      form.value.crowdfundBehavior = {
        countAllInvoices: config.crowdfundBehavior.countAllInvoices || false,
      };
    }
    if (config.checkout) {
      form.value.checkout = {
        requestContributorData: config.checkout.requestContributorData || false,
      };
    }
    if (config.advanced) {
      form.value.advanced = {
        htmlLanguage: config.advanced.htmlLanguage || config.htmlLang || "",
        htmlMetaTags: config.advanced.htmlMetaTags || config.htmlMetaTags || "",
        enableSounds:
          config.advanced.enableSounds !== undefined
            ? config.advanced.enableSounds
            : false,
        enableAnimations:
          config.advanced.enableAnimations !== undefined
            ? config.advanced.enableAnimations
            : false,
        enableDiscussion:
          config.advanced.enableDiscussion !== undefined
            ? config.advanced.enableDiscussion
            : false,
        callbackNotificationUrl:
          config.advanced.callbackNotificationUrl ||
          config.notificationUrl ||
          "",
      };
    }

    // Load perks - could be in 'perks', 'items', or 'template' field
    let perksArray: any[] = [];
    const perksSource = config.perks || config.items || config.template;

    if (perksSource) {
      if (Array.isArray(perksSource)) {
        perksArray = perksSource;
      } else if (typeof perksSource === "string") {
        try {
          const parsed = JSON.parse(perksSource);
          perksArray = Array.isArray(parsed) ? parsed : [];
        } catch (e) {
          console.warn("Failed to parse perks JSON", e);
        }
      }
    }

    if (perksArray && perksArray.length > 0) {
      perks.value = perksArray.map((p: any) => {
        // Handle inventory
        let inventory: number | null = null;
        if (
          p.inventory !== null &&
          p.inventory !== undefined &&
          p.inventory !== ""
        ) {
          const invNum = Number(p.inventory);
          if (!isNaN(invNum) && invNum >= 0) {
            inventory = invNum;
          }
        }

        return {
          id: p.id || "",
          title: p.title || "",
          priceType: p.priceType || "Minimum",
          price: p.price ? parseFloat(String(p.price)) : 1,
          taxRate:
            p.taxRate !== null && p.taxRate !== undefined && p.taxRate !== ""
              ? parseFloat(String(p.taxRate))
              : null,
          image: p.image || "",
          description: p.description || "",
          categories: p.categories
            ? Array.isArray(p.categories)
              ? p.categories.join(", ")
              : String(p.categories)
            : null,
          inventory: inventory,
          buyButtonText: p.buyButtonText || "Podporiť",
          disabled: p.disabled !== undefined ? p.disabled : false,
        };
      });
      updatePerksJson();
    }
  } else {
    // If no config, initialize with defaults
    form.value.appName = props.app.name || "";
    form.value.currency = props.store?.default_currency || "EUR";
    perks.value = [];
    updatePerksJson();
  }
});

// Expose saving, error, and success state to parent component
defineExpose({
  saving,
  error,
  success,
});
</script>
