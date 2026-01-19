<template>
  <div class="bg-white shadow rounded-lg space-y-6">
    <!-- Contributions Section -->
    <div class="p-6 space-y-4">
      <h2 class="text-xl font-semibold text-gray-900">Contributions</h2>
      
      <div class="space-y-3">
        <div class="flex items-center">
          <input
            id="sortByPopularity"
            v-model="localContributions.sortByPopularity"
            type="checkbox"
            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
          />
          <label for="sortByPopularity" class="ml-2 block text-sm text-gray-900">
            Sort contribution perks by popularity
          </label>
        </div>

        <div class="flex items-center">
          <input
            id="displayRanking"
            v-model="localContributions.displayRanking"
            type="checkbox"
            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
          />
          <label for="displayRanking" class="ml-2 block text-sm text-gray-900">
            Display contribution ranking
          </label>
        </div>

        <div class="flex items-center">
          <input
            id="displayValue"
            v-model="localContributions.displayValue"
            type="checkbox"
            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
          />
          <label for="displayValue" class="ml-2 block text-sm text-gray-900">
            Display contribution value
          </label>
        </div>

        <div class="flex items-center">
          <input
            id="noAdditionalAfterTarget"
            v-model="localContributions.noAdditionalAfterTarget"
            type="checkbox"
            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
          />
          <label for="noAdditionalAfterTarget" class="ml-2 block text-sm text-gray-900">
            Do not allow additional contributions after target has been reached
          </label>
        </div>
      </div>
    </div>

    <!-- Crowdfund Behavior Section -->
    <div class="px-6 pb-6 space-y-4">
      <h2 class="text-xl font-semibold text-gray-900">Crowdfund Behavior</h2>
      
      <div class="flex items-center">
        <input
          id="countAllInvoices"
          v-model="localCrowdfundBehavior.countAllInvoices"
          type="checkbox"
          class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
        />
        <label for="countAllInvoices" class="ml-2 block text-sm text-gray-900">
          Count all invoices created on the store as part of the goal
        </label>
      </div>
    </div>

    <!-- Checkout Section -->
    <div class="px-6 pb-6 space-y-4">
      <h2 class="text-xl font-semibold text-gray-900">Checkout</h2>
      
      <div class="flex items-center">
        <input
          id="requestContributorData"
          v-model="localCheckout.requestContributorData"
          type="checkbox"
          class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
        />
        <label for="requestContributorData" class="ml-2 block text-sm text-gray-900">
          Request contributor data on checkout
        </label>
      </div>
    </div>

    <!-- Additional Options (Accordion) -->
    <div class="px-6 pb-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">Additional Options</h2>
      
      <div class="space-y-2">
        <!-- HTML Headers -->
        <div>
          <button
            type="button"
            @click="toggleSection('htmlHeaders')"
            class="w-full flex items-center justify-between px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
          >
            <span class="font-medium text-gray-900">HTML Headers</span>
            <svg
              class="w-5 h-5 text-gray-500 transform transition-transform"
              :class="{ 'rotate-180': openSections.htmlHeaders }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.htmlHeaders" class="mt-2 px-4 pb-4">
            <label for="htmlLanguage" class="block text-sm font-medium text-gray-700">
              Language
            </label>
            <input
              id="htmlLanguage"
              v-model="localAdvanced.htmlLanguage"
              type="text"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            />
            <p class="mt-1 text-xs text-gray-500">Fix the HTML page language.</p>
          </div>
        </div>

        <!-- HTML Meta Tags -->
        <div>
          <button
            type="button"
            @click="toggleSection('htmlMetaTags')"
            class="w-full flex items-center justify-between px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
          >
            <span class="font-medium text-gray-900">HTML Meta Tags</span>
            <svg
              class="w-5 h-5 text-gray-500 transform transition-transform"
              :class="{ 'rotate-180': openSections.htmlMetaTags }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.htmlMetaTags" class="mt-2 px-4 pb-4">
            <textarea
              v-model="localAdvanced.htmlMetaTags"
              rows="6"
              class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 font-mono text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
              placeholder='<meta name="description" content="Your description">
<meta name="keywords" content="keyword1, keyword2, keyword3">
<meta name="author" content="John Doe">'
            ></textarea>
            <p class="mt-1 text-xs text-gray-500">Please insert valid HTML here. Only meta tags accepted.</p>
          </div>
        </div>

        <!-- Sound -->
        <div>
          <button
            type="button"
            @click="toggleSection('sound')"
            class="w-full flex items-center justify-between px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
          >
            <span class="font-medium text-gray-900">Sound</span>
            <svg
              class="w-5 h-5 text-gray-500 transform transition-transform"
              :class="{ 'rotate-180': openSections.sound }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.sound" class="mt-2 px-4 pb-4">
            <div class="flex items-center">
              <input
                id="enableSounds"
                v-model="localAdvanced.enableSounds"
                type="checkbox"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              />
              <label for="enableSounds" class="ml-2 block text-sm text-gray-900">
                Enable sounds on new payments
              </label>
            </div>
          </div>
        </div>

        <!-- Animation -->
        <div>
          <button
            type="button"
            @click="toggleSection('animation')"
            class="w-full flex items-center justify-between px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
          >
            <span class="font-medium text-gray-900">Animation</span>
            <svg
              class="w-5 h-5 text-gray-500 transform transition-transform"
              :class="{ 'rotate-180': openSections.animation }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.animation" class="mt-2 px-4 pb-4">
            <div class="flex items-center">
              <input
                id="enableAnimations"
                v-model="localAdvanced.enableAnimations"
                type="checkbox"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              />
              <label for="enableAnimations" class="ml-2 block text-sm text-gray-900">
                Enable background animations on new payments
              </label>
            </div>
          </div>
        </div>

        <!-- Discussion -->
        <div>
          <button
            type="button"
            @click="toggleSection('discussion')"
            class="w-full flex items-center justify-between px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
          >
            <span class="font-medium text-gray-900">Discussion</span>
            <svg
              class="w-5 h-5 text-gray-500 transform transition-transform"
              :class="{ 'rotate-180': openSections.discussion }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.discussion" class="mt-2 px-4 pb-4">
            <div class="flex items-center">
              <input
                id="enableDiscussion"
                v-model="localAdvanced.enableDiscussion"
                type="checkbox"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              />
              <label for="enableDiscussion" class="ml-2 block text-sm text-gray-900">
                Enable Disqus Comments
              </label>
            </div>
          </div>
        </div>

        <!-- Notification URL Callbacks -->
        <div>
          <button
            type="button"
            @click="toggleSection('notification')"
            class="w-full flex items-center justify-between px-4 py-3 text-left bg-gray-50 hover:bg-gray-100 rounded-md transition-colors"
          >
            <span class="font-medium text-gray-900">Notification URL Callbacks</span>
            <svg
              class="w-5 h-5 text-gray-500 transform transition-transform"
              :class="{ 'rotate-180': openSections.notification }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="openSections.notification" class="mt-2 px-4 pb-4">
            <label for="callbackNotificationUrl" class="block text-sm font-medium text-gray-700">
              Callback Notification URL
            </label>
            <input
              id="callbackNotificationUrl"
              v-model="localAdvanced.callbackNotificationUrl"
              type="url"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              placeholder="https://example.com/webhook"
            />
          </div>
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


