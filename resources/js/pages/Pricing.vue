<template>
  <div class="min-h-screen bg-gray-950 text-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="text-center mb-14">
        <h1 class="text-4xl font-bold tracking-tight">Simple pricing</h1>
        <p class="mt-3 text-gray-400 text-lg">
          {{ promotionActive ? t("free_pro.description") : t("landing.pricing_subtitle") }}
        </p>
      </div>

      <FreeProNotice :active="promotionActive" :show-action="true" class="mb-8" />
      <div class="grid md:grid-cols-2 gap-8">
        <!-- Free -->
        <div
          class="rounded-2xl border border-gray-700 bg-gray-900/50 p-8 flex flex-col"
        >
          <h2 class="text-xl font-bold text-white">Free</h2>
          <div class="mt-4 flex items-baseline">
            <span class="text-4xl font-bold text-white">€0</span>
            <span class="text-gray-400 ml-2">/ year</span>
          </div>
          <ul class="mt-6 space-y-3 flex-1">
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              1 store
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Unlimited PoS terminals
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              1 Lightning Address
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              1 API key
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Manual CSV export
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Basic stats (totals per store)
            </li>
          </ul>
          <router-link
            v-if="!authStore.isAuthenticated"
            :to="{ name: 'register' }"
            class="mt-8 w-full py-3 px-4 rounded-xl border border-gray-600 text-gray-300 hover:bg-gray-800 text-center font-medium transition-colors"
          >
            Get started
          </router-link>
          <span
            v-else-if="currentPlanCode === 'free'"
            class="mt-8 w-full py-3 px-4 rounded-xl border border-gray-600 text-gray-400 text-center font-medium"
          >
            Current plan
          </span>
        </div>

        <!-- PRO -->
        <div
          class="rounded-2xl border-2 border-indigo-500 bg-gray-900/80 p-8 flex flex-col relative"
        >
          <div
            class="absolute top-0 right-0 rounded-bl-lg bg-indigo-600 text-white text-xs font-medium px-3 py-1"
          >
            Popular
          </div>
          <h2 class="text-xl font-bold text-white">Pro</h2>
          <div class="mt-4 flex items-baseline">
            <span class="text-4xl font-bold text-white">{{ formatSats(promotionActive ? 0 : pricing.pro.sats_per_year) }}</span>
            <span class="text-gray-400 ml-2">/ year</span>
          </div>
          <p class="text-sm text-gray-400 mt-1">{{ promotionActive ? t("free_pro.description") : t("account.pro_yearly_price", { amount: formatSats(pricing.pro.sats_per_year) }) }}</p>
          <ul class="mt-6 space-y-3 flex-1">
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              3 stores
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Unlimited PoS terminals
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Unlimited Lightning Addresses
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              3 API keys
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Manual + automatic monthly CSV exports
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Advanced stats (per store, per PoS, overall)
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Mark as Paid in Cash / Card (PoS)
            </li>
            <li class="flex items-center text-gray-300 text-sm">
              <span class="text-green-400 mr-2">✓</span>
              Priority support
            </li>
          </ul>
          <button
            v-if="canUpgradeToPro"
            type="button"
            @click="goToCheckout"
            :disabled="checkoutLoading"
            class="mt-8 w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium transition-colors disabled:opacity-50"
          >
            {{ checkoutLoading ? "Redirecting…" : "Upgrade to Pro" }}
          </button>
          <router-link
            v-else-if="!authStore.isAuthenticated"
            :to="{ name: 'register' }"
            class="mt-8 w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-center transition-colors"
          >
            Get started
          </router-link>
          <span
            v-else
            class="mt-8 w-full py-3 px-4 rounded-xl border border-indigo-500 text-indigo-400 text-center font-medium"
          >
            Current plan
          </span>
        </div>
      </div>

      <p class="mt-10 text-center text-gray-500 text-sm">
        Enterprise (unlimited stores, webhooks, multi-user) - contact sales.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import FreeProNotice from "@/components/account/FreeProNotice.vue";
import { useEffectiveAccess } from "@/composables/useEffectiveAccess";
import { isPurchasePausedError } from "@/services/apiError";
import { useI18n } from "vue-i18n";
import { computed, ref, onMounted } from "vue";
import { useAuthStore } from "../store/auth";
import { usePricing } from "@/composables/usePricing";
import { useCurrentPlan } from "../composables/useCurrentPlan";
import api from "../services/api";

const { t } = useI18n();
const { purchases, promotionActive: accountPromotionActive } = useEffectiveAccess();
const authStore = useAuthStore();
const { canUpgradeToPro, planCode: currentPlanCode } = useCurrentPlan();
const { pricing, formatSats, promotionActive: publicPromotionActive, load: loadPricing } = usePricing();
void loadPricing();
const promotionActive = computed(() => publicPromotionActive.value || accountPromotionActive.value);
const checkoutLoading = ref(false);

async function goToCheckout() {
  if (!purchases.value.pro_subscriptions) return;
  checkoutLoading.value = true;
  try {
    const { data } = await api.post("/subscriptions/checkout", { plan: "pro" });
    if (data.checkoutUrl) {
      window.location.href = data.checkoutUrl;
    }
  } catch (error) {
    alert(isPurchasePausedError(error) ? t("free_pro.purchases_paused") : t("account.checkout_failed"));
  } finally {
    checkoutLoading.value = false;
  }
}

onMounted(() => {
  window.location.replace("/#pricing");
});
</script>
