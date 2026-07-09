<template>
  <div
    class="min-h-screen bg-gray-900 text-white font-sans selection:bg-indigo-500 selection:text-white"
  >
    <PublicHeader />

    <LandingHero />

    <LandingClaritySection />

    <LandingFeaturesSection />

    <LandingHowItWorks />

    <LandingInvoicingSection />

    <LandingPricingSection />

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount, nextTick } from "vue";
import { useAuthStore } from "../store/auth";
import { usePricing } from "../composables/usePricing";
import { usePlanFeatures } from "../composables/usePlanFeatures";
import { useBtcPayUrl } from "../composables/useBtcPayUrl";
import PublicHeader from "../components/layout/PublicHeader.vue";
import AppFooter from "../components/layout/AppFooter.vue";
import LandingHero from "../components/landing/LandingHero.vue";
import LandingClaritySection from "../components/landing/LandingClaritySection.vue";
import LandingFeaturesSection from "../components/landing/LandingFeaturesSection.vue";
import LandingHowItWorks from "../components/landing/LandingHowItWorks.vue";
import LandingInvoicingSection from "../components/landing/LandingInvoicingSection.vue";
import LandingPricingSection from "../components/landing/LandingPricingSection.vue";

const authStore = useAuthStore();
const { load: loadPricing } = usePricing();
const { load: loadPlanFeatures } = usePlanFeatures();
const { load: loadBtcpayConfig } = useBtcPayUrl();

function handleHashAnchorClick(e: Event) {
  const targetAnchor = e.currentTarget as HTMLAnchorElement;
  const href = targetAnchor.getAttribute("href");
  if (href && href !== "#") {
    e.preventDefault();
    const target = document.querySelector(href);
    if (target) {
      target.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }
}

let hashAnchorElements: HTMLAnchorElement[] = [];

// Ensure user, pricing and plan features are fetched after first paint
onMounted(() => {
  void nextTick().then(() => {
    requestAnimationFrame(() => {
      document.getElementById("landing-shell")?.remove();
      document.getElementById("app")?.classList.remove("sf-app-pending");
    });
  });

  const loadDeferred = () => {
    void Promise.all([loadPricing(), loadPlanFeatures(), loadBtcpayConfig()]);
    void authStore.fetchUser();
  };

  if ("requestIdleCallback" in window) {
    requestIdleCallback(loadDeferred);
  } else {
    setTimeout(loadDeferred, 0);
  }

  if (window.location.hash) {
    setTimeout(() => {
      const target = document.querySelector(window.location.hash);
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }, 100);
  }

  hashAnchorElements = [
    ...document.querySelectorAll<HTMLAnchorElement>('a[href^="#"]'),
  ];
  hashAnchorElements.forEach((anchor) => {
    anchor.addEventListener("click", handleHashAnchorClick);
  });
});

onBeforeUnmount(() => {
  hashAnchorElements.forEach((anchor) => {
    anchor.removeEventListener("click", handleHashAnchorClick);
  });
  hashAnchorElements = [];
});
</script>
