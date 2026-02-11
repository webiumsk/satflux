<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h3 class="text-2xl font-bold text-white">{{ t("account.title") }}</h3>
        <p class="mt-1 text-sm text-gray-400">
          {{ t("account.manage_profile") }}
        </p>
      </div>

      <!-- Profile Information -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="px-6 py-8 sm:p-10">
          <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
            <svg
              class="w-5 h-5 mr-2 text-indigo-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              />
            </svg>
            {{ t("account.profile_information") }}
          </h4>
          <form @submit.prevent="handleUpdateProfile">
            <div class="grid grid-cols-1 gap-y-6 gap-x-6 sm:grid-cols-2">
              <div>
                <label
                  for="name"
                  class="block text-sm font-medium text-gray-300"
                  >{{ t("account.name") }}</label
                >
                <div class="mt-1">
                  <input
                    id="name"
                    v-model="profileForm.name"
                    type="text"
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                    :placeholder="t('account.name_optional')"
                  />
                </div>
              </div>
              <div>
                <label
                  for="email"
                  class="block text-sm font-medium text-gray-300"
                  >{{ t("account.email") }}</label
                >
                <div class="mt-1">
                  <input
                    id="email"
                    :value="authStore.user?.email"
                    type="email"
                    disabled
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm text-gray-400 bg-gray-800 cursor-not-allowed sm:text-sm"
                  />
                </div>
              </div>
            </div>
            <div class="mt-8 flex justify-end">
              <button
                type="submit"
                :disabled="profileLoading"
                class="inline-flex justify-center py-2.5 px-6 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
              >
                {{
                  profileLoading
                    ? t("auth.saving")
                    : t("account.update_profile")
                }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Subscription Plan -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="px-6 py-8 sm:p-10">
          <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
            <svg
              class="w-5 h-5 mr-2 text-indigo-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
              />
            </svg>
            {{ t("account.subscription_plan") }}
          </h4>

          <div
            class="bg-gradient-to-br from-gray-700/50 to-gray-800 rounded-xl p-8 border border-gray-600 relative overflow-hidden"
          >
            <!-- Decorative blob -->
            <div
              class="absolute top-0 right-0 -tr-10 w-32 h-32 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"
            ></div>

            <div
              class="flex flex-col md:flex-row md:items-center justify-between mb-8 relative z-10"
            >
              <div>
                <h5
                  class="text-2xl font-bold text-white flex items-center gap-2"
                >
                  {{ currentPlanName }}
                  <span
                    v-if="!isPaidPlan"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-600 text-gray-100"
                  >
                    {{ t("account.plan_badge_standard") }}
                  </span>
                  <span
                    v-else-if="subscriber?.isActive"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gradient-to-r from-green-500 to-emerald-600 text-white"
                  >
                    {{ t("account.plan_badge_active") }}
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-600 text-white"
                  >
                    {{ t("account.plan_badge_inactive") }}
                  </span>
                </h5>
                <p class="text-gray-400 mt-2">{{ currentPlanDescription }}</p>
                <div
                  v-if="subscriber && subscriber.periodEnd"
                  class="text-sm text-gray-400 mt-2"
                >
                  {{ t("account.next_billing", { date: formatDate(subscriber.periodEnd) }) }}
                </div>
              </div>
              <div class="mt-4 md:mt-0 md:text-right">
                <div class="text-3xl font-bold text-white">
                  {{ currentPlanPrice }}
                </div>
                <div class="text-sm text-gray-400">{{ isProPlan ? t("account.per_month_paid_yearly") : t("account.per_month") }}</div>
              </div>
            </div>

            <!-- Plan Features -->
            <div class="border-t border-gray-600 pt-6 mb-6">
              <h6
                class="text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider"
              >
                {{ t("account.current_plan_includes") }}
              </h6>
              <ul class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <li
                  v-for="feature in currentPlanFeatures"
                  :key="feature"
                  class="flex items-center text-sm text-gray-300"
                >
                  <svg
                    class="w-5 h-5 text-green-400 mr-3 flex-shrink-0"
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
                  {{ feature }}
                </li>
              </ul>
            </div>

            <!-- Billing Information -->
            <div
              v-if="isPaidPlan || creditBalance > 0"
              class="border-t border-gray-600 pt-6 mb-6"
            >
              <h6
                class="text-sm font-medium text-gray-300 mb-4 uppercase tracking-wider"
              >
                {{ t("account.billing_information") }}
              </h6>
              <div class="space-y-4">
                <div
                  v-if="isPaidPlan"
                  class="flex items-center justify-between"
                >
                  <span class="text-sm text-gray-400">{{ t("account.payment_method") }}</span>
                  <div class="flex items-center gap-2">
                    <span
                      class="px-3 py-1 rounded-full bg-gray-700 text-sm text-gray-300"
                      >{{ t("account.credit_balance", { amount: formatSats(creditBalance) }) }}</span
                    >
                    <button
                      @click="showAddCreditModal = true"
                      class="text-sm text-indigo-400 hover:text-indigo-300 flex items-center gap-1"
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
                          d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                        />
                      </svg>
                      {{ t("account.add_credit") }}
                    </button>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-400">{{ t("account.notification_email") }}</span>
                  <span class="text-sm text-gray-300 flex items-center gap-1">
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
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                      />
                    </svg>
                    {{ authStore.user?.email }}
                  </span>
                </div>
                <div
                  v-if="isPaidPlan && subscriber && subscriber.periodEnd"
                  class="flex items-center justify-between"
                >
                  <span class="text-sm text-gray-400"
                    >{{ t("account.next_charge_on", { date: formatDate(subscriber.periodEnd) }) }}</span
                  >
                  <span class="text-sm font-medium text-white">{{
                    currentPlanPrice
                  }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-400">{{ t("account.auto_renewal") }}</span>
                  <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-300">{{
                      subscriber?.autoRenew ? t("account.on") : t("account.off")
                    }}</span>
                    <div
                      class="relative inline-block w-10 h-6 transition-colors duration-200 ease-in-out rounded-full"
                      :class="
                        subscriber?.autoRenew ? 'bg-indigo-600' : 'bg-gray-600'
                      "
                    >
                      <span
                        class="absolute left-1 top-1 inline-block w-4 h-4 transform transition-transform duration-200 ease-in-out rounded-full bg-white"
                        :class="
                          subscriber?.autoRenew
                            ? 'translate-x-4'
                            : 'translate-x-0'
                        "
                      ></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Upgrade Options -->
            <div
              v-if="showUpgradeOptions"
              class="border-t border-gray-600 pt-8 mt-8"
            >
              <h6 class="text-lg font-medium text-white mb-6">
                {{ t("account.upgrade_to_unlock") }}
              </h6>
              <div
                :class="
                  showProUpgrade
                    ? 'grid grid-cols-1 md:grid-cols-2 gap-6'
                    : 'grid grid-cols-1 gap-6'
                "
              >
                <!-- Pro Plan Card -->
                <div
                  v-if="showProUpgrade"
                  class="border border-gray-600 rounded-xl p-6 bg-gray-800/80 hover:border-indigo-500 transition-colors relative group"
                >
                  <div
                    class="absolute inset-0 bg-gradient-to-br from-indigo-600/5 to-purple-600/5 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity"
                  ></div>
                  <div class="relative z-10">
                    <h6 class="text-lg font-bold text-white mb-2">{{ t("account.pro_plan") }}</h6>
                    <div class="text-2xl font-bold text-indigo-400 mb-4">
                      {{ formatSats(pricing.pro.sats_per_month_display) }}<span class="text-base font-normal text-gray-500"
                        >{{ t("account.pro_price_period") }}</span
                      >
                    </div>
                    <ul class="text-sm text-gray-400 space-y-2 mb-6">
                      <li
                        v-for="key in planFeatures.pro.feature_keys"
                        :key="key"
                        class="flex items-center"
                      >
                        <span
                          class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-2"
                        ></span
                        >{{ t("plans.features." + key) }}
                      </li>
                    </ul>
                    <button
                      @click="upgradePlan('pro')"
                      :disabled="upgrading"
                      class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold rounded-lg transition-all shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {{ upgrading ? t("stores.processing") : t("account.upgrade_to_pro") }}
                    </button>
                  </div>
                </div>

                <!-- Enterprise / Need more? Card (same as Landing) -->
                <div
                  v-if="showEnterpriseUpgrade"
                  class="border border-gray-600 rounded-xl p-6 bg-gray-800/80 hover:border-purple-500 transition-colors relative group"
                  :class="!showProUpgrade ? 'md:max-w-md mx-auto' : ''"
                >
                  <div
                    class="absolute inset-0 bg-gradient-to-br from-purple-600/5 to-pink-600/5 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity"
                  ></div>
                  <div class="relative z-10">
                    <h6 class="text-lg font-bold text-white mb-2">
                      {{ t("landing.pricing_need_more_headline") }}
                    </h6>
                    <p class="text-sm text-gray-400 mb-4">
                      {{ t("landing.pricing_need_more_text") }}
                    </p>
                    <ul class="text-sm text-gray-400 space-y-2 mb-6">
                      <li
                        v-for="key in planFeatures.enterprise.feature_keys"
                        :key="key"
                        class="flex items-center"
                      >
                        <span
                          class="w-1.5 h-1.5 rounded-full bg-purple-500 mr-2"
                        ></span
                        >{{ t("plans.features." + key) }}
                      </li>
                    </ul>
                    <a
                      href="mailto:hello@satflux.io"
                      class="block w-full text-center px-4 py-2 border border-purple-500 text-purple-400 hover:bg-purple-500/10 text-sm font-bold rounded-lg transition-all"
                    >
                      {{ t("landing.pricing_need_more_cta") }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Change Password -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="px-6 py-8 sm:p-10">
          <h4 class="text-lg font-semibold text-white mb-6 flex items-center">
            <svg
              class="w-5 h-5 mr-2 text-indigo-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
              />
            </svg>
            {{ t("account.password_settings") }}
          </h4>
          <form @submit.prevent="handleUpdatePassword">
            <div class="space-y-6">
              <div>
                <label
                  for="current_password"
                  class="block text-sm font-medium text-gray-300"
                  >{{ t("account.current_password") }}</label
                >
                <div class="mt-1">
                  <input
                    id="current_password"
                    v-model="passwordForm.current_password"
                    type="password"
                    required
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                  />
                </div>
              </div>
              <div>
                <label
                  for="password"
                  class="block text-sm font-medium text-gray-300"
                  >{{ t("account.new_password") }}</label
                >
                <div class="mt-1">
                  <input
                    id="password"
                    v-model="passwordForm.password"
                    type="password"
                    required
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                  />
                </div>
              </div>
              <div>
                <label
                  for="password_confirmation"
                  class="block text-sm font-medium text-gray-300"
                  >{{ t("account.confirm_new_password") }}</label
                >
                <div class="mt-1">
                  <input
                    id="password_confirmation"
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    required
                    class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                  />
                </div>
              </div>
            </div>
            <div class="mt-8 flex justify-end">
              <button
                type="submit"
                :disabled="passwordLoading"
                class="inline-flex justify-center py-2.5 px-6 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
              >
                {{
                  passwordLoading
                    ? t("auth.saving")
                    : t("account.update_password")
                }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Add Credit Modal -->
    <div
      v-if="showAddCreditModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="showAddCreditModal = false"
    >
      <div
        class="bg-gray-800 rounded-xl border border-gray-700 max-w-md w-full p-6"
      >
        <div class="flex items-center justify-between mb-4">
          <h5 class="text-lg font-bold text-white">{{ t("account.add_credit_modal_title") }}</h5>
          <button
            @click="showAddCreditModal = false"
            class="text-gray-400 hover:text-white"
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
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2"
              >{{ t("account.amount_sats") }}</label
            >
            <input
              v-model.number="creditAmount"
              type="number"
              min="1"
              step="1"
              class="w-full px-4 py-3 border border-gray-600 rounded-lg text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              :placeholder="t('account.amount_placeholder')"
            />
          </div>
          <div class="flex gap-3">
            <button
              @click="showAddCreditModal = false"
              class="flex-1 px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700 transition-colors"
            >
              {{ t("common.cancel") }}
            </button>
            <button
              @click="handleAddCredit"
              :disabled="!creditAmount || creditAmount < 1 || addingCredit"
              class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ addingCredit ? t("stores.processing") : t("account.add_credit_btn") }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";
import { usePricing } from "../../composables/usePricing";
import { usePlanFeatures } from "../../composables/usePlanFeatures";
import api from "../../services/api";

const { t, locale } = useI18n();
const authStore = useAuthStore();
const { pricing, formatSats, load: loadPricing } = usePricing();
const { planFeatures, load: loadPlanFeatures } = usePlanFeatures();

const profileForm = ref({
  name: "",
});

const passwordForm = ref({
  current_password: "",
  password: "",
  password_confirmation: "",
});

const profileLoading = ref(false);
const passwordLoading = ref(false);
const upgrading = ref(false);
const subscriber = ref<any>(null);
const creditBalance = ref(0);
const showAddCreditModal = ref(false);
const creditAmount = ref<number | null>(null);
const addingCredit = ref(false);
const loadingSubscription = ref(false);

// Plan information
const currentPlanName = computed(() => {
  const role = authStore.user?.role || "free";
  if (role === "enterprise") return t("account.plan_enterprise");
  if (role === "pro") return t("account.plan_pro");
  return t("account.plan_free");
});

const currentPlanPrice = computed(() => {
  const role = authStore.user?.role || "free";
  const p = pricing.value;
  if (role === "enterprise") return "—";
  if (role === "pro") return formatSats(p?.pro?.sats_per_month_display ?? 16_500);
  return formatSats(p?.free?.sats_per_year ?? 0);
});

const currentPlanDescription = computed(() => {
  const role = authStore.user?.role || "free";
  if (role === "enterprise") return t("account.plan_desc_enterprise");
  if (role === "pro") return t("account.plan_desc_pro");
  return t("account.plan_desc_free");
});

const currentPlanFeatures = computed(() => {
  const role = authStore.user?.role || "free";
  const keys =
    role === "enterprise"
      ? planFeatures.value.enterprise.feature_keys
      : role === "pro"
        ? planFeatures.value.pro.feature_keys
        : planFeatures.value.free.feature_keys;
  return keys.map((key: string) => t("plans.features." + key));
});

const isPaidPlan = computed(() => {
  const role = authStore.user?.role || "free";
  return role === "pro" || role === "enterprise";
});

const isProPlan = computed(() => authStore.user?.role === "pro");

// Upgrade options logic
const showUpgradeOptions = computed(() => {
  const role = authStore.user?.role || "free";
  // Show upgrades if user is free or pro (can upgrade to enterprise)
  // Don't show for enterprise (top tier) or admin/support (not applicable)
  return role === "free" || role === "pro";
});

const showProUpgrade = computed(() => {
  const role = authStore.user?.role || "free";
  return role === "free";
});

const showEnterpriseUpgrade = computed(() => {
  const role = authStore.user?.role || "free";
  return role === "free" || role === "pro";
});

onMounted(async () => {
  await Promise.all([loadPricing(), loadPlanFeatures()]);
  if (authStore.user) {
    profileForm.value.name = authStore.user.name || "";
  }

  // Load subscription details if user has paid plan or might have subscription
  await loadSubscriptionDetails();
});

async function loadSubscriptionDetails() {
  loadingSubscription.value = true;
  try {
    const response = await api.get("/subscriptions/details");
    subscriber.value = response.data.subscriber;
    creditBalance.value = response.data.creditBalance || 0;
  } catch (error: any) {
    // If 404, user doesn't have subscription yet - that's ok
    if (error.response?.status !== 404) {
      console.error("Error loading subscription details:", error);
    }
    // Reset values on error
    subscriber.value = null;
    creditBalance.value = 0;
  } finally {
    loadingSubscription.value = false;
  }
}

async function handleAddCredit() {
  if (!creditAmount.value || creditAmount.value < 1) return;

  addingCredit.value = true;
  try {
    const response = await api.post("/subscriptions/credits", {
      amount: creditAmount.value,
      currency: "SATS",
    });

    // Credit addition creates an invoice - redirect to payment
    if (response.data.invoiceUrl) {
      window.location.href = response.data.invoiceUrl;
    } else if (response.data.invoiceId) {
      // If only invoice ID is returned, construct URL
      const baseUrl = response.data.baseUrl || "https://satflux.org";
      window.location.href = `${baseUrl}/i/${response.data.invoiceId}`;
    } else {
      // No invoice URL - this shouldn't happen, but handle gracefully
      alert(t("account.credit_invoice_created"));
      showAddCreditModal.value = false;
      creditAmount.value = null;
    }
  } catch (error: any) {
    console.error("Failed to add credit:", error);
    alert(
      error.response?.data?.message ||
        t("account.add_credit_failed"),
    );
  } finally {
    addingCredit.value = false;
  }
}

function formatDate(timestamp: number | string): string {
  if (!timestamp) return "";
  const date =
    typeof timestamp === "number"
      ? new Date(timestamp * 1000)
      : new Date(timestamp);
  const localeTag = locale.value === "sk" ? "sk-SK" : locale.value === "es" ? "es-ES" : "en-US";
  return date.toLocaleDateString(localeTag, {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

async function upgradePlan(plan: string) {
  upgrading.value = true;
  try {
    const response = await api.post("/subscriptions/checkout", {
      plan: plan,
    });

    if (response.data.checkoutUrl) {
      window.location.href = response.data.checkoutUrl;
    } else {
      alert(t("account.checkout_failed"));
      upgrading.value = false;
    }
  } catch (error: any) {
    console.error("Failed to create checkout:", error);
    alert(
      error.response?.data?.message ||
        t("account.checkout_failed"),
    );
    upgrading.value = false;
  }
}

async function handleUpdateProfile() {
  profileLoading.value = true;
  try {
    await api.put("/user", { name: profileForm.value.name ?? null });
    await authStore.fetchUser();
    alert(t("account.profile_updated"));
  } catch (error) {
    alert(t("account.profile_update_failed"));
  } finally {
    profileLoading.value = false;
  }
}

async function handleUpdatePassword() {
  passwordLoading.value = true;
  try {
    await api.put("/user/password", passwordForm.value);
    passwordForm.value = {
      current_password: "",
      password: "",
      password_confirmation: "",
    };
    alert(t("account.password_updated"));
  } catch (error) {
    alert(t("account.password_update_failed"));
  } finally {
    passwordLoading.value = false;
  }
}
</script>
