<template>
  <div class="space-y-6">
    <!-- Contributions Section -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="p-6 md:p-8 space-y-6">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
          <span>{{ t("stores.crowdfund_contributions_section") }}</span>
        </h2>
        
        <div class="space-y-4">
          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input
                id="sortByPopularity"
                v-model="localContributions.sortByPopularity"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
            </div>
            <div class="ml-3 text-sm">
              <label for="sortByPopularity" class="font-medium text-gray-200 cursor-pointer">
                {{ t("stores.crowdfund_sort_popularity") }}
              </label>
              <p class="text-gray-400 text-xs mt-0.5">
                {{ t("stores.crowdfund_sort_popularity_hint") }}
              </p>
            </div>
          </div>

          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input
                id="displayRanking"
                v-model="localContributions.displayRanking"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
            </div>
            <div class="ml-3 text-sm">
              <label for="displayRanking" class="font-medium text-gray-200 cursor-pointer">
                {{ t("stores.crowdfund_display_ranking") }}
              </label>
            </div>
          </div>

          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input
                id="displayValue"
                v-model="localContributions.displayValue"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
            </div>
            <div class="ml-3 text-sm">
              <label for="displayValue" class="font-medium text-gray-200 cursor-pointer">
                {{ t("stores.crowdfund_display_value") }}
              </label>
            </div>
          </div>

          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input
                id="noAdditionalAfterTarget"
                v-model="localContributions.noAdditionalAfterTarget"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
            </div>
            <div class="ml-3 text-sm">
              <label for="noAdditionalAfterTarget" class="font-medium text-gray-200 cursor-pointer">
                {{ t("stores.crowdfund_no_extra_after_target") }}
              </label>
              <p class="text-gray-400 text-xs mt-0.5">
                {{ t("stores.crowdfund_no_extra_after_target_hint") }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Crowdfund Behavior Section -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="p-6 md:p-8 space-y-4">
        <h2 class="text-xl font-bold text-white">
          {{ t("stores.crowdfund_behavior_section") }}
        </h2>
        
        <div class="flex items-start">
          <div class="flex items-center h-5">
            <input
              id="countAllInvoices"
              v-model="localCrowdfundBehavior.countAllInvoices"
              type="checkbox"
              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
            />
          </div>
          <div class="ml-3 text-sm">
            <label for="countAllInvoices" class="font-medium text-gray-200 cursor-pointer">
              {{ t("stores.crowdfund_count_all_invoices") }}
            </label>
            <p class="text-gray-400 text-xs mt-0.5">
              {{ t("stores.crowdfund_count_all_invoices_hint") }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Checkout Section (matches BTCPay Crowdfund FormId dropdown) -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="p-6 md:p-8 space-y-4">
        <h2 class="text-xl font-bold text-white">
          {{ t("stores.crowdfund_checkout_section") }}
        </h2>

        <div class="space-y-2">
          <label
            for="crowdfundFormId"
            class="block text-sm font-medium text-gray-300"
          >
            {{ t("stores.crowdfund_checkout_request_label") }}
          </label>
          <select
            id="crowdfundFormId"
            v-model="localCheckout.formId"
            class="block w-full max-w-md px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          >
            <option value="">{{ t("stores.crowdfund_form_none") }}</option>
            <option value="Email">{{ t("stores.crowdfund_form_email") }}</option>
            <option value="Address">{{
              t("stores.crowdfund_form_address")
            }}</option>
            <option
              v-if="
                localCheckout.formId &&
                !isBuiltinCrowdfundFormId(localCheckout.formId)
              "
              :value="localCheckout.formId"
            >
              {{ t("stores.crowdfund_form_custom") }}
            </option>
          </select>
          <p class="text-gray-400 text-xs">
            {{ t("stores.crowdfund_checkout_form_hint") }}
          </p>
        </div>
      </div>
    </div>

    <!-- Additional Options (Accordion) -->
     <div class="space-y-4">
        <h3 class="text-xl font-bold text-white px-1">
          {{ t("stores.crowdfund_advanced_section") }}
        </h3>
        
        <!-- HTML Headers -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('htmlHeaders')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">{{
              t("stores.crowdfund_adv_html_headers")
            }}</span>
            <svg
              class="w-5 h-5 text-gray-400 transform transition-transform duration-200"
              :class="{ 'rotate-180': openSections.htmlHeaders }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.htmlHeaders" class="bg-gray-900 border-t border-gray-700 px-6 py-6 space-y-4">
            <div>
            <label
              for="htmlLanguage"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              {{ t("stores.crowdfund_adv_html_language") }}
            </label>
                <input
                id="htmlLanguage"
                v-model="localAdvanced.htmlLanguage"
                type="text"
                placeholder="en"
                class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
            </div>
          </div>
        </div>

        <!-- HTML Meta Tags -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('htmlMetaTags')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">{{
              t("stores.crowdfund_adv_meta_tags")
            }}</span>
            <svg
              class="w-5 h-5 text-gray-400 transform transition-transform duration-200"
              :class="{ 'rotate-180': openSections.htmlMetaTags }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.htmlMetaTags" class="bg-gray-900 border-t border-gray-700 px-6 py-6 border border-t-0 border-gray-700">
            <label
              for="htmlMetaTags"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              {{ t("stores.crowdfund_adv_meta_tags_label") }}
            </label>
            <textarea
              id="htmlMetaTags"
              v-model="localAdvanced.htmlMetaTags"
              rows="6"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white font-mono text-xs placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              :placeholder="t('stores.crowdfund_adv_meta_placeholder')"
            ></textarea>
            <p class="mt-2 text-xs text-gray-500">
              {{ t("stores.crowdfund_adv_meta_help") }}
            </p>
          </div>
        </div>

        <!-- Sound -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('sound')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">{{
              t("stores.crowdfund_adv_sound")
            }}</span>
            <svg
              class="w-5 h-5 text-gray-400 transform transition-transform duration-200"
              :class="{ 'rotate-180': openSections.sound }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.sound" class="bg-gray-900 border-t border-gray-700 px-6 py-6 space-y-4">
            <div class="flex items-center">
              <input
                id="enableSounds"
                v-model="localAdvanced.enableSounds"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
              <label for="enableSounds" class="ml-3 block text-sm font-medium text-white cursor-pointer">
                {{ t("stores.crowdfund_adv_sounds_enable") }}
              </label>
            </div>
            <div v-if="localAdvanced.enableSounds" class="space-y-2">
              <label
                for="crowdfundSoundsText"
                class="block text-sm font-medium text-gray-300"
              >
                {{ t("stores.crowdfund_adv_sounds_lines_label") }}
              </label>
              <textarea
                id="crowdfundSoundsText"
                v-model="localAdvanced.soundsText"
                rows="5"
                class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white font-mono text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                :placeholder="t('stores.crowdfund_adv_sounds_lines_placeholder')"
              />
              <p class="text-xs text-gray-500">
                {{ t("stores.crowdfund_adv_sounds_lines_help") }}
              </p>
            </div>
          </div>
        </div>

        <!-- Animation -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('animation')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">{{
              t("stores.crowdfund_adv_animation")
            }}</span>
            <svg
              class="w-5 h-5 text-gray-400 transform transition-transform duration-200"
              :class="{ 'rotate-180': openSections.animation }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
           <div v-show="openSections.animation" class="bg-gray-900 border-t border-gray-700 px-6 py-6 space-y-4">
            <div class="flex items-center">
              <input
                id="enableAnimations"
                v-model="localAdvanced.enableAnimations"
                type="checkbox"
                 class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
              <label for="enableAnimations" class="ml-3 block text-sm font-medium text-white cursor-pointer">
                {{ t("stores.crowdfund_adv_animations_enable") }}
              </label>
            </div>
            <div v-if="localAdvanced.enableAnimations" class="space-y-2">
              <label
                for="crowdfundAnimationColorsText"
                class="block text-sm font-medium text-gray-300"
              >
                {{ t("stores.crowdfund_adv_animation_colors_label") }}
              </label>
              <textarea
                id="crowdfundAnimationColorsText"
                v-model="localAdvanced.animationColorsText"
                rows="5"
                class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white font-mono text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                :placeholder="t('stores.crowdfund_adv_animation_colors_placeholder')"
              />
              <p class="text-xs text-gray-500">
                {{ t("stores.crowdfund_adv_animation_colors_help") }}
              </p>
            </div>
          </div>
        </div>

        <!-- Discussion -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('discussion')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">{{
              t("stores.crowdfund_adv_discussion")
            }}</span>
            <svg
              class="w-5 h-5 text-gray-400 transform transition-transform duration-200"
              :class="{ 'rotate-180': openSections.discussion }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
           <div v-show="openSections.discussion" class="bg-gray-900 border-t border-gray-700 px-6 py-6 space-y-4">
            <div class="flex items-center">
              <input
                id="enableDiscussion"
                v-model="localAdvanced.enableDiscussion"
                type="checkbox"
                 class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
               <div class="ml-3 text-sm">
                <label for="enableDiscussion" class="font-medium text-white cursor-pointer">
                  {{ t("stores.crowdfund_adv_disqus_enable") }}
                </label>
                <p class="text-gray-400 text-xs mt-0.5">
                  {{ t("stores.crowdfund_adv_disqus_hint") }}
                </p>
                </div>
            </div>
            <div v-if="localAdvanced.enableDiscussion" class="space-y-2">
              <label
                for="crowdfundDisqusShortname"
                class="block text-sm font-medium text-gray-300"
              >
                {{ t("stores.crowdfund_adv_disqus_shortname_label") }}
              </label>
              <input
                id="crowdfundDisqusShortname"
                v-model="localAdvanced.disqusShortname"
                type="text"
                autocomplete="off"
                class="block w-full max-w-md px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                :placeholder="t('stores.crowdfund_adv_disqus_shortname_placeholder')"
              />
            </div>
          </div>
        </div>

        <!-- Notification URL Callbacks -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('notification')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">{{
              t("stores.crowdfund_adv_notification")
            }}</span>
            <svg
              class="w-5 h-5 text-gray-400 transform transition-transform duration-200"
              :class="{ 'rotate-180': openSections.notification }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
           <div v-show="openSections.notification" class="bg-gray-900 border-t border-gray-700 px-6 py-6">
            <label
              for="callbackNotificationUrl"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              {{ t("stores.crowdfund_adv_callback_url") }}
            </label>
            <input
              id="callbackNotificationUrl"
              v-model="localAdvanced.callbackNotificationUrl"
              type="url"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              :placeholder="t('stores.crowdfund_adv_callback_placeholder')"
            />
          </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps<{
  contributions: any;
  crowdfundBehavior: any;
  checkout: any;
  advanced: any;
}>();

const emit = defineEmits<{
  (e: 'update:contributions', value: any): void;
  (e: 'update:crowdfundBehavior', value: any): void;
  (e: 'update:checkout', value: any): void;
  (e: 'update:advanced', value: any): void;
}>();

const openSections = ref({
  htmlHeaders: false,
  htmlMetaTags: false,
  sound: false,
  animation: false,
  discussion: false,
  notification: false,
});

function normalizeCheckout(c: Record<string, unknown>) {
  const raw = c?.formId;
  let formId =
    raw === null || raw === undefined ? "" : String(raw);
  if (!formId && c?.requestContributorData === true) {
    formId = "Email";
  }
  return { formId };
}

const localContributions = ref({ ...props.contributions });
const localCrowdfundBehavior = ref({ ...props.crowdfundBehavior });
const localCheckout = ref(normalizeCheckout(props.checkout));
const localAdvanced = ref({ ...props.advanced });

// Watch and emit updates
watch(localContributions, (val) => {
  emit('update:contributions', val);
}, { deep: true });

watch(localCrowdfundBehavior, (val) => {
  emit('update:crowdfundBehavior', val);
}, { deep: true });

watch(localCheckout, (val) => {
  emit('update:checkout', val);
}, { deep: true });

watch(localAdvanced, (val) => {
  emit('update:advanced', val);
}, { deep: true });

// Sync from parent only when the prop object is replaced (e.g. load app config).
// Deep watch here caused an infinite loop with the local→emit watchers: checkbox
// mutates local → emit → parent updates prop → deep prop watch overwrote local → repeat.
watch(
  () => props.contributions,
  (val) => {
    if (val === localContributions.value) return;
    localContributions.value = { ...val };
  },
);

watch(
  () => props.crowdfundBehavior,
  (val) => {
    if (val === localCrowdfundBehavior.value) return;
    localCrowdfundBehavior.value = { ...val };
  },
);

watch(
  () => props.checkout,
  (val) => {
    if (val === localCheckout.value) return;
    localCheckout.value = normalizeCheckout(val as Record<string, unknown>);
  },
);

watch(
  () => props.advanced,
  (val) => {
    if (val === localAdvanced.value) return;
    localAdvanced.value = { ...val };
  },
);

function toggleSection(section: string) {
  openSections.value[section as keyof typeof openSections] = !openSections.value[section as keyof typeof openSections];
}

/** BTCPay hardcoded FormId keys; anything else is a store-specific form UUID. */
function isBuiltinCrowdfundFormId(v: string) {
  return v === "" || v === "Email" || v === "Address";
}
</script>
