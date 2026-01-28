<template>
  <!-- Content Container -->
  <div>
    <!-- Warning Banner -->
    <div
      class="mb-6 bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4"
    >
      <div class="flex items-start">
        <svg
          class="w-5 h-5 text-yellow-400 mt-0.5 mr-3 flex-shrink-0"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
        <div class="flex-1">
          <p class="text-sm text-yellow-400">
            <strong>Warning:</strong> Payment button should only be used for
            tips and donations. Using the payment button for e-commerce
            integrations is not recommended since order relevant information can
            be modified by the user. For e-commerce, you should use our
            Greenfield API. If this store process commercial transactions, we
            advise you to create a separate store before using the payment
            button.
          </p>
        </div>
        <button
          v-if="showWarning"
          @click="showWarning = false"
          class="ml-3 text-yellow-400 hover:text-yellow-300"
        >
          <svg
            class="w-5 h-5"
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

    <div class="space-y-6">
      <!-- Note: Pay Button settings are not saved. Configure and copy the generated code. -->

      <!-- General Settings -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <h2 class="text-xl font-bold text-white mb-4">General Settings</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label
                for="price"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Price
              </label>
              <input
                id="price"
                v-model="form.price"
                type="text"
                placeholder="Leave empty for custom amount"
                class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
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
                placeholder="Select currency"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label
                for="defaultPaymentMethod"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Default Payment Method
              </label>
              <Select
                id="defaultPaymentMethod"
                v-model="form.defaultPaymentMethod"
                :options="paymentMethodOptions"
                placeholder="Use the store's default"
              />
            </div>

            <div>
              <label
                for="checkoutDescription"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Checkout Description
              </label>
              <input
                id="checkoutDescription"
                v-model="form.checkoutDescription"
                type="text"
                placeholder="Description shown on checkout"
                class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              />
            </div>
          </div>

          <div>
            <label
              for="orderId"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Order ID
            </label>
            <input
              id="orderId"
              v-model="form.orderId"
              type="text"
              placeholder="Optional order identifier"
              class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            />
          </div>
        </div>
      </div>

      <!-- Pay Button Configuration -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <h2 class="text-xl font-bold text-white mb-4">Pay Button</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div
              class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl"
            >
              <input
                id="customizeButtonText"
                v-model="form.customizeButtonText"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
              />
              <label
                for="customizeButtonText"
                class="ml-3 block text-sm font-medium text-white cursor-pointer"
              >
                Customize Pay Button Text
              </label>
            </div>

            <div v-if="form.customizeButtonText">
              <label
                for="buttonText"
                class="block text-sm font-medium text-gray-300 mb-1"
              >
                Pay Button Text
              </label>
              <input
                id="buttonText"
                v-model="form.buttonText"
                type="text"
                placeholder="Pay with"
                class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              />
            </div>
          </div>

          <div>
            <label
              for="buttonImageUrl"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Pay Button Image Url
            </label>
            <input
              id="buttonImageUrl"
              v-model="form.buttonImageUrl"
              type="text"
              placeholder="https://satflux.org/img/paybutton/pay.svg"
              class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2"
                  >Image Size</label
                >
                <div class="flex gap-3">
                  <button
                    v-for="size in imageSizes"
                    :key="size.value"
                    type="button"
                    @click="form.imageSize = size.value"
                    :class="[
                      'px-4 py-2 border rounded-xl text-sm font-medium transition-all',
                      form.imageSize === size.value
                        ? 'border-indigo-500 bg-indigo-500/10 text-indigo-400'
                        : 'border-gray-600 bg-gray-900 text-gray-300 hover:border-gray-500',
                    ]"
                  >
                    {{ size.label }}
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2"
                  >Button Type</label
                >
                <div class="space-y-2">
                  <label
                    class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl cursor-pointer hover:bg-gray-800 transition-colors"
                  >
                    <input
                      v-model="form.buttonType"
                      type="radio"
                      value="fixed"
                      class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600"
                    />
                    <span class="ml-3 text-sm font-medium text-white"
                      >Fixed amount</span
                    >
                  </label>
                  <label
                    class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl cursor-pointer hover:bg-gray-800 transition-colors"
                  >
                    <input
                      v-model="form.buttonType"
                      type="radio"
                      value="custom"
                      class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600"
                    />
                    <span class="ml-3 text-sm font-medium text-white"
                      >Custom amount</span
                    >
                  </label>
                  <label
                    class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl cursor-pointer hover:bg-gray-800 transition-colors"
                  >
                    <input
                      v-model="form.buttonType"
                      type="radio"
                      value="slider"
                      class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600"
                    />
                    <span class="ml-3 text-sm font-medium text-white"
                      >Slider</span
                    >
                  </label>
                </div>
              </div>

              <div
                v-if="
                  form.buttonType === 'custom' || form.buttonType === 'slider'
                "
                class="grid grid-cols-1 md:grid-cols-3 gap-6"
              >
                <div>
                  <label
                    for="minAmount"
                    class="block text-sm font-medium text-gray-300 mb-1"
                    >Min</label
                  >
                  <input
                    id="minAmount"
                    v-model.number="form.minAmount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                </div>
                <div>
                  <label
                    for="maxAmount"
                    class="block text-sm font-medium text-gray-300 mb-1"
                    >Max</label
                  >
                  <input
                    id="maxAmount"
                    v-model.number="form.maxAmount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                </div>
                <div>
                  <label
                    for="stepAmount"
                    class="block text-sm font-medium text-gray-300 mb-1"
                    >Step</label
                  >
                  <input
                    id="stepAmount"
                    v-model.number="form.stepAmount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  />
                </div>
              </div>

              <div
                v-if="
                  form.buttonType === 'custom' || form.buttonType === 'slider'
                "
                class="space-y-3"
              >
                <div
                  class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl"
                >
                  <input
                    id="simpleInputStyle"
                    v-model="form.simpleInputStyle"
                    type="checkbox"
                    class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                  />
                  <label
                    for="simpleInputStyle"
                    class="ml-3 block text-sm font-medium text-white cursor-pointer"
                  >
                    Use a simple input style
                  </label>
                </div>
                <div
                  class="flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl"
                >
                  <input
                    id="fitButtonInline"
                    v-model="form.fitButtonInline"
                    type="checkbox"
                    class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
                  />
                  <label
                    for="fitButtonInline"
                    class="ml-3 block text-sm font-medium text-white cursor-pointer"
                  >
                    Fit button inline
                  </label>
                </div>
              </div>
            </div>

            <!-- Preview -->
            <div class="mt-6 p-6 bg-gray-900 border border-gray-700 rounded-xl">
              <h3 class="text-sm font-medium text-gray-300 mb-4">Preview</h3>
              <div
                class="flex flex-col items-center gap-4 p-4 bg-gray-800 rounded"
              >
                <div
                  v-if="
                    form.buttonType === 'custom' || form.buttonType === 'slider'
                  "
                  class="flex items-center gap-2"
                >
                  <button
                    type="button"
                    class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white hover:bg-gray-600"
                  >
                    -
                  </button>
                  <input
                    type="number"
                    :value="form.minAmount || 1"
                    readonly
                    class="w-24 px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white text-center"
                  />
                  <button
                    type="button"
                    class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white hover:bg-gray-600"
                  >
                    +
                  </button>
                </div>
                <div class="text-sm text-gray-400">
                  {{ form.currency || "EUR" }}
                </div>
                <button
                  type="button"
                  class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-500 transition-colors"
                >
                  {{ form.buttonText || "Pay with" }}
                  <img
                    v-if="form.buttonImageUrl"
                    :src="form.buttonImageUrl"
                    alt="Pay Button"
                    class="ml-2"
                    :style="{ height: getImageHeight() }"
                  />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment Notifications -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <h2 class="text-xl font-bold text-white mb-4">
            Payment Notifications
          </h2>

          <div>
            <label
              for="serverIpn"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Server IPN
            </label>
            <input
              id="serverIpn"
              v-model="form.serverIpn"
              type="url"
              placeholder="https://example.com/webhook"
              class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            />
            <p class="mt-1 text-xs text-gray-500">
              The URL to post purchase data.
            </p>
          </div>

          <div>
            <label
              for="emailNotifications"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Email Notifications
            </label>
            <input
              id="emailNotifications"
              v-model="form.emailNotifications"
              type="email"
              placeholder="user@example.com"
              class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            />
            <div
              class="mt-3 flex items-center bg-gray-900 border border-gray-700 p-4 rounded-xl"
            >
              <input
                id="receiveEmailNotifications"
                v-model="form.receiveEmailNotifications"
                type="checkbox"
                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded transition-colors"
              />
              <label
                for="receiveEmailNotifications"
                class="ml-3 block text-sm font-medium text-white cursor-pointer"
              >
                Receive email notification updates
              </label>
            </div>
          </div>

          <div>
            <label
              for="browserRedirect"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Browser Redirect
            </label>
            <input
              id="browserRedirect"
              v-model="form.browserRedirect"
              type="url"
              placeholder="https://example.com/success"
              class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            />
            <p class="mt-1 text-xs text-gray-500">
              Where to redirect the customer after payment is complete.
            </p>
          </div>
        </div>
      </div>

      <!-- Advanced Options -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <h2 class="text-xl font-bold text-white mb-4">Advanced Options</h2>
          <p class="text-sm text-gray-400">
            Specify additional query string parameters that should be appended
            to the checkout page once the invoice is created. For example,
            lang=da-DK would load the checkout page in Danish by default.
          </p>
          <div>
            <label
              for="checkoutQueryString"
              class="block text-sm font-medium text-gray-300 mb-1"
            >
              Checkout Additional Query String
            </label>
            <input
              id="checkoutQueryString"
              v-model="form.checkoutQueryString"
              type="text"
              placeholder="lang=da-DK"
              class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
            />
          </div>
        </div>
      </div>

      <!-- Generated Code -->
      <div
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-xl font-bold text-white">Generated Code</h2>
              <p class="text-sm text-gray-400 mt-1">
                Code updates automatically. Copy and paste it into your website
                HTML.
              </p>
            </div>
          </div>
          <textarea
            :value="generatedCode"
            readonly
            class="w-full h-64 px-4 py-3 bg-gray-900 border border-gray-700 rounded-xl text-sm text-gray-300 font-mono resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @click="selectAll"
          ></textarea>
        </div>
      </div>

      <!-- Alternatives -->
      <div
        v-if="storeId.value"
        class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
      >
        <div class="p-6 md:p-8 space-y-6">
          <h2 class="text-xl font-bold text-white mb-4">Alternatives</h2>
          <p class="text-sm text-gray-400">
            You can also share the link/LNURL or encode it in a QR code.
          </p>

          <div class="flex gap-3 mb-4">
            <button
              type="button"
              @click="alternativeType = 'link'"
              :class="[
                'px-4 py-2 border rounded-xl text-sm font-medium transition-all',
                alternativeType === 'link'
                  ? 'border-orange-500 bg-orange-500/10 text-orange-400'
                  : 'border-gray-600 bg-gray-900 text-gray-300 hover:border-gray-500',
              ]"
            >
              Link
            </button>
            <button
              type="button"
              @click="alternativeType = 'lnurl'"
              :class="[
                'px-4 py-2 border rounded-xl text-sm font-medium transition-all',
                alternativeType === 'lnurl'
                  ? 'border-orange-500 bg-orange-500/10 text-orange-400'
                  : 'border-gray-600 bg-gray-900 text-gray-300 hover:border-gray-500',
              ]"
            >
              LNURL
            </button>
          </div>

          <div class="flex flex-col md:flex-row gap-6">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-300 mb-1">
                {{ alternativeType === "link" ? "Link URL" : "LNURL" }}
              </label>
              <input
                :value="alternativeUrl"
                readonly
                class="block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-gray-300 font-mono text-sm focus:outline-none"
              />
            </div>
            <div class="flex items-end">
              <button
                type="button"
                @click="copyAlternativeUrl"
                class="px-4 py-3 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors"
              >
                Copy
              </button>
            </div>
          </div>

          <div class="flex justify-center">
            <div class="bg-white p-4 rounded-xl">
              <img :src="qrCodeUrl" alt="QR Code" class="w-64 h-64" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { currencies } from "../../data/currencies";
import Select from "../../components/ui/Select.vue";

const props = defineProps<{
  store: any;
}>();

const showWarning = ref(true);
const copied = ref(false);
const alternativeType = ref<"link" | "lnurl">("link");

const currencyOptions = currencies.map(c => ({ label: `${c.code} - ${c.name}`, value: c.code }));

const paymentMethodOptions = [
  { label: "Use the store's default", value: "" },
  { label: "Bitcoin On-Chain", value: "BTC-OnChain" },
  { label: "Bitcoin Lightning Network", value: "BTC-LightningNetwork" },
];

const form = ref({
  price: "",
  currency: props.store?.default_currency || "EUR",
  defaultPaymentMethod: "",
  checkoutDescription: "",
  orderId: "",
  customizeButtonText: true,
  buttonText: "Pay with",
  buttonImageUrl: "https://satflux.org/img/paybutton/pay.svg",
  imageSize: "209x57",
  buttonType: "custom" as "fixed" | "custom" | "slider",
  minAmount: 1,
  maxAmount: 20,
  stepAmount: 1,
  simpleInputStyle: false,
  fitButtonInline: false,
  serverIpn: "",
  emailNotifications: "",
  receiveEmailNotifications: true,
  browserRedirect: "",
  checkoutQueryString: "",
});

const imageSizes = [
  { value: "146x40", label: "146 x 40 px" },
  { value: "168x46", label: "168 x 46 px" },
  { value: "209x57", label: "209 x 57 px" },
];

const baseUrl = (import.meta as any).env.VITE_BTCPAY_BASE_URL || "https://satflux.org";
const storeId = computed(() => props.store?.btcpay_store_id || "");

const alternativeUrl = computed(() => {
  if (!storeId.value) return "";

  const params = new URLSearchParams();
  if (form.value.price) params.append("price", form.value.price);
  if (form.value.currency) params.append("currency", form.value.currency);
  if (form.value.checkoutDescription)
    params.append("description", form.value.checkoutDescription);
  if (form.value.orderId) params.append("orderId", form.value.orderId);

  if (alternativeType.value === "lnurl") {
    // LNURL would need to be generated server-side
    return `${baseUrl}/i/${storeId.value}?${params.toString()}`;
  }

  return `${baseUrl}/i/${storeId.value}?${params.toString()}`;
});

const qrCodeUrl = computed(() => {
  if (!alternativeUrl.value) return "";
  return `https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=${encodeURIComponent(alternativeUrl.value)}`;
});

function getImageHeight() {
  const height = form.value.imageSize.split("x")[1];
  return `${height}px`;
}

const generatedCode = computed(() => {
  // Don't generate code if store is not loaded yet
  if (!props.store || !props.store.btcpay_store_id) {
    return "<!-- Loading store information... -->";
  }

  try {
    return generateHtmlCode();
  } catch (err: any) {
    console.error("Error generating code:", err);
    return `<!-- Error: ${err.message || "Failed to generate code"} -->`;
  }
});

function generateHtmlCode(): string {
  if (!storeId.value) {
    throw new Error("Store ID is required");
  }

  const formClass = form.value.fitButtonInline
    ? "btcpay-form--inline"
    : "btcpay-form--block";

  let html = `<style>
.btcpay-form {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.btcpay-form--inline {
  flex-direction: row;
}
.btcpay-form--block {
  flex-direction: column;
}
.btcpay-form--inline .submit {
  margin-left: 15px;
}
.btcpay-form--block select {
  margin-bottom: 10px;
}
.btcpay-form .btcpay-custom-container {
  text-align: center;
}
.btcpay-custom {
  display: flex;
  align-items: center;
  justify-content: center;
}
.btcpay-form .plus-minus {
  cursor: pointer;
  font-size: 25px;
  line-height: 25px;
  background: #DFE0E1;
  height: 30px;
  width: 45px;
  border: none;
  border-radius: 60px;
  margin: auto 5px;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  color: #000;
  font-weight: bold;
  padding: 0;
  user-select: none;
}
.btcpay-form .plus-minus:hover {
  background: #C8C9CA;
}
.btcpay-form .plus-minus:active {
  background: #B0B1B2;
}
.btcpay-form select {
  -moz-appearance: none;
  -webkit-appearance: none;
  appearance: none;
  color: currentColor;
  background: transparent;
  border: 1px solid transparent;
  display: block;
  padding: 1px;
  margin-left: auto;
  margin-right: auto;
  font-size: 11px;
  cursor: pointer;
}
.btcpay-form select:hover {
  border-color: #ccc;
}
.btcpay-form option {
  color: #000;
  background: rgba(0,0,0,.1);
}
.btcpay-input-price {
  -moz-appearance: textfield;
  border: none;
  box-shadow: none;
  text-align: center;
  font-size: 25px;
  margin: auto;
  border-radius: 5px;
  line-height: 35px;
  background: #fff;
  padding: 0;
  outline: none;
}
.btcpay-input-price::-webkit-outer-spin-button,
.btcpay-input-price::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
<form method="POST" action="${baseUrl}/api/v1/invoices" class="btcpay-form ${formClass}">
  <input type="hidden" name="storeId" value="${storeId.value}" />
`;

  // Add price if fixed amount
  if (form.value.buttonType === "fixed" && form.value.price) {
    html += `  <input type="hidden" name="price" value="${form.value.price}" />
  <input type="hidden" name="currency" value="${form.value.currency || "EUR"}" />
`;
  }

  // Add custom amount input if custom or slider
  if (
    form.value.buttonType === "custom" ||
    form.value.buttonType === "slider"
  ) {
    if (form.value.simpleInputStyle) {
      html += `  <input type="number" name="price" min="${form.value.minAmount}" max="${form.value.maxAmount}" step="${form.value.stepAmount}" value="${form.value.minAmount}" class="btcpay-input-price" required />
  <input type="hidden" name="currency" value="${form.value.currency || "EUR"}" />
`;
    } else {
      // Common currencies for select dropdown
      const commonCurrencies = ["USD", "GBP", "EUR", "BTC"];
      const selectedCurrency = form.value.currency || "EUR";

      html += `  <div class="btcpay-custom-container">
    <div class="btcpay-custom">
      <button class="plus-minus" type="button" data-type="-" data-step="${form.value.stepAmount || 1}" data-min="${form.value.minAmount || 1}" data-max="${form.value.maxAmount || ""}">-</button>
      <input class="btcpay-input-price" type="number" name="price" min="${form.value.minAmount || 1}" max="${form.value.maxAmount || ""}" step="${form.value.stepAmount || 1}" value="${form.value.minAmount || 1}" data-price="${form.value.minAmount || 1}" style="width:3em;" />
      <button class="plus-minus" type="button" data-type="+" data-step="${form.value.stepAmount || 1}" data-min="${form.value.minAmount || 1}" data-max="${form.value.maxAmount || ""}">+</button>
    </div>
    <select name="currency">
`;
      for (const curr of commonCurrencies) {
        html += `      <option value="${curr}"${curr === selectedCurrency ? " selected" : ""}>${curr}</option>
`;
      }
      html += `    </select>
  </div>
`;
    }
  } else {
    // For fixed amount, add currency as hidden field
    html += `  <input type="hidden" name="currency" value="${form.value.currency || "EUR"}" />
`;
  }

  // Add optional fields
  if (form.value.defaultPaymentMethod) {
    html += `  <input type="hidden" name="defaultPaymentMethod" value="${form.value.defaultPaymentMethod}" />
`;
  }
  if (form.value.checkoutDescription) {
    html += `  <input type="hidden" name="checkoutDesc" value="${form.value.checkoutDescription}" />
`;
  }
  if (form.value.orderId) {
    html += `  <input type="hidden" name="orderId" value="${form.value.orderId}" />
`;
  }
  if (form.value.serverIpn) {
    html += `  <input type="hidden" name="notificationURL" value="${form.value.serverIpn}" />
`;
  }
  if (form.value.emailNotifications) {
    html += `  <input type="hidden" name="buyerEmail" value="${form.value.emailNotifications}" />
`;
  }
  if (form.value.browserRedirect) {
    html += `  <input type="hidden" name="redirectURL" value="${form.value.browserRedirect}" />
`;
  }
  if (form.value.checkoutQueryString) {
    html += `  <input type="hidden" name="checkoutQueryString" value="${form.value.checkoutQueryString}" />
`;
  }

  // Add submit button
  if (form.value.customizeButtonText && form.value.buttonImageUrl) {
    const [width] = form.value.imageSize.split("x");
    html += `  <input type="image" class="submit" name="submit" src="${form.value.buttonImageUrl}" style="width:${width}px" alt="${form.value.buttonText || "Pay with BTCPay Server"}" />
`;
  } else {
    // Default BTCPay button
    html += `  <input type="image" class="submit" name="submit" src="${baseUrl}/img/paybutton/pay.svg" style="width:209px" alt="Pay with BTCPay Server, a Self-Hosted Bitcoin Payment Processor" />
`;
  }

  html += "</form>\n";
  html += "<script>\n";
  html += "    function handlePlusMinus(event) {\n";
  html += "        event.preventDefault();\n";
  html += "        const root = event.target.closest('.btcpay-form');\n";
  html += "        const el = root.querySelector('.btcpay-input-price');\n";
  html += "        const step = parseInt(event.target.dataset.step) || 1;\n";
  html += "        const min = parseInt(event.target.dataset.min) || 1;\n";
  html += "        const max = parseInt(event.target.dataset.max);\n";
  html += "        const type = event.target.dataset.type;\n";
  html += "        const price = parseInt(el.value) || min;\n";
  html += "        if (type === '-') {\n";
  html += "            el.value = price - step < min ? min : price - step;\n";
  html += "        } else if (type === '+') {\n";
  html += "            el.value = price + step > max ? max : price + step;\n";
  html += "        }\n";
  html += "    }\n";
  html +=
    '    document.querySelectorAll(".btcpay-form .plus-minus").forEach(function(el) {\n';
  html += "        if (!el.dataset.initialized) {\n";
  html += "            el.addEventListener('click', handlePlusMinus);\n";
  html += "            el.dataset.initialized = true;\n";
  html += "        }\n";
  html += "    });\n";
  html += "    \n";
  html += "    function handlePriceInput(event) {\n";
  html += "        event.preventDefault();\n";
  html += "        const root = event.target.closest('.btcpay-form');\n";
  html += "        const price = parseInt(event.target.dataset.price);\n";
  html +=
    "        if (isNaN(event.target.value)) root.querySelector('.btcpay-input-price').value = price;\n";
  html +=
    "        const min = parseInt(event.target.getAttribute('min')) || 1;\n";
  html += "        const max = parseInt(event.target.getAttribute('max'));\n";
  html += "        if (event.target.value < min) {\n";
  html += "            event.target.value = min;\n";
  html += "        } else if (event.target.value > max) {\n";
  html += "            event.target.value = max;\n";
  html += "        }\n";
  html += "    }\n";
  html +=
    '    document.querySelectorAll(".btcpay-form .btcpay-input-price").forEach(function(el) {\n';
  html += "        if (!el.dataset.initialized) {\n";
  html += "            el.addEventListener('input', handlePriceInput);\n";
  html += "            el.dataset.initialized = true;\n";
  html += "        }\n";
  html += "    });\n";
  html += "<" + "/script>";

  return html;
}

function copyCode() {
  if (!generatedCode.value) return;

  navigator.clipboard
    .writeText(generatedCode.value)
    .then(() => {
      copied.value = true;
      setTimeout(() => {
        copied.value = false;
      }, 2000);
    })
    .catch((err) => {
      console.error("Failed to copy:", err);
    });
}

function selectAll(event: Event) {
  const target = event.target as HTMLTextAreaElement;
  target.select();
}

function copyAlternativeUrl() {
  if (!alternativeUrl.value) return;

  navigator.clipboard.writeText(alternativeUrl.value).then(() => {
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  });
}

defineExpose({
  copyCode,
  generatedCode,
});
</script>
