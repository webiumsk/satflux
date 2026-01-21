<template>
  <div class="space-y-6">
    <!-- Contributions Section -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
      <div class="p-6 md:p-8 space-y-6">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
          <span>Contributions</span>
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
                Sort contribution perks by popularity
              </label>
              <p class="text-gray-400 text-xs mt-0.5">Show most popular perks first</p>
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
                Display contribution ranking
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
                Display contribution value
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
                Disable additional contributions after target reached
              </label>
              <p class="text-gray-400 text-xs mt-0.5">Prevent new payments once goal is met</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Crowdfund Behavior Section -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
      <div class="p-6 md:p-8 space-y-4">
        <h2 class="text-xl font-bold text-white">Crowdfund Behavior</h2>
        
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
              Count all store invoices towards goal
            </label>
             <p class="text-gray-400 text-xs mt-0.5">Include invoices created outside this crowdfund app</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Checkout Section -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
      <div class="p-6 md:p-8 space-y-4">
        <h2 class="text-xl font-bold text-white">Checkout</h2>
        
        <div class="flex items-start">
          <div class="flex items-center h-5">
            <input
              id="requestContributorData"
              v-model="localCheckout.requestContributorData"
              type="checkbox"
              class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
            />
          </div>
          <div class="ml-3 text-sm">
            <label for="requestContributorData" class="font-medium text-gray-200 cursor-pointer">
              Request contributor data
            </label>
             <p class="text-gray-400 text-xs mt-0.5">Ask for name/email during checkout</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Additional Options (Accordion) -->
     <div class="space-y-4">
        <h3 class="text-xl font-bold text-white px-1">Advanced Options</h3>
        
        <!-- HTML Headers -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('htmlHeaders')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">HTML Headers</span>
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
                 <label for="htmlLanguage" class="block text-sm font-medium text-gray-300 mb-1">
                HTML Language
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
            <span class="font-medium text-white">HTML Meta Tags</span>
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
             <label for="htmlMetaTags" class="block text-sm font-medium text-gray-300 mb-1">
                Meta Tags
              </label>
            <textarea
              id="htmlMetaTags"
              v-model="localAdvanced.htmlMetaTags"
              rows="6"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white font-mono text-xs placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder='<meta name="description" content="Your description">
<meta name="keywords" content="keyword1, keyword2">'
            ></textarea>
            <p class="mt-2 text-xs text-gray-500">Must be valid HTML meta tags.</p>
          </div>
        </div>

        <!-- Sound -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
          <button
            type="button"
            @click="toggleSection('sound')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-700/50 transition-colors"
          >
            <span class="font-medium text-white">Sound</span>
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
          <div v-show="openSections.sound" class="bg-gray-900 border-t border-gray-700 px-6 py-6">
            <div class="flex items-center">
              <input
                id="enableSounds"
                v-model="localAdvanced.enableSounds"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
              <label for="enableSounds" class="ml-3 block text-sm font-medium text-white cursor-pointer">
                Enable sounds on new payments
              </label>
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
            <span class="font-medium text-white">Animation</span>
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
           <div v-show="openSections.animation" class="bg-gray-900 border-t border-gray-700 px-6 py-6">
            <div class="flex items-center">
              <input
                id="enableAnimations"
                v-model="localAdvanced.enableAnimations"
                type="checkbox"
                 class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
              <label for="enableAnimations" class="ml-3 block text-sm font-medium text-white cursor-pointer">
                Enable background animations on new payments
              </label>
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
            <span class="font-medium text-white">Discussion</span>
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
           <div v-show="openSections.discussion" class="bg-gray-900 border-t border-gray-700 px-6 py-6">
            <div class="flex items-center">
              <input
                id="enableDiscussion"
                v-model="localAdvanced.enableDiscussion"
                type="checkbox"
                 class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-700 rounded transition-colors"
              />
               <div class="ml-3 text-sm">
                <label for="enableDiscussion" class="font-medium text-white cursor-pointer">
                    Enable Disqus Comments
                </label>
                <p class="text-gray-400 text-xs mt-0.5">Requires Disqus shortname configuration</p>
                </div>
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
            <span class="font-medium text-white">Notification URL Callbacks</span>
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
             <label for="callbackNotificationUrl" class="block text-sm font-medium text-gray-300 mb-1">
              Callback Notification URL
            </label>
            <input
              id="callbackNotificationUrl"
              v-model="localAdvanced.callbackNotificationUrl"
              type="url"
              class="block w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="https://example.com/webhook"
            />
          </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';

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

const localContributions = ref({ ...props.contributions });
const localCrowdfundBehavior = ref({ ...props.crowdfundBehavior });
const localCheckout = ref({ ...props.checkout });
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

// Watch props for external updates
watch(() => props.contributions, (val) => {
  localContributions.value = { ...val };
}, { deep: true });

watch(() => props.crowdfundBehavior, (val) => {
  localCrowdfundBehavior.value = { ...val };
}, { deep: true });

watch(() => props.checkout, (val) => {
  localCheckout.value = { ...val };
}, { deep: true });

watch(() => props.advanced, (val) => {
  localAdvanced.value = { ...val };
}, { deep: true });

function toggleSection(section: string) {
  openSections.value[section as keyof typeof openSections] = !openSections.value[section as keyof typeof openSections];
}
</script>
