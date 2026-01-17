<template>
  <div
    class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8"
  >
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Create your account
        </h2>
      </div>
      <form class="mt-8 space-y-6" @submit.prevent="handleRegister">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="email" class="sr-only">Email address</label>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="email"
              autocomplete="email"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              placeholder="Email address"
            />
          </div>
          <div>
            <label for="password" class="sr-only">Password</label>
            <input
              id="password"
              v-model="form.password"
              name="password"
              type="password"
              autocomplete="new-password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              placeholder="Password"
            />
          </div>
          <div>
            <label for="password_confirmation" class="sr-only"
              >Confirm Password</label
            >
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              name="password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              placeholder="Confirm Password"
            />
          </div>
        </div>

        <div v-if="error" class="rounded-md bg-red-50 p-4">
          <div class="text-sm text-red-800">{{ error }}</div>
        </div>

        <div v-if="success" class="rounded-md bg-green-50 p-4">
          <div class="text-sm text-green-800">{{ success }}</div>
        </div>

        <div>
          <button
            type="submit"
            :disabled="loading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            {{ loading ? "Creating account..." : "Create account" }}
          </button>
        </div>

        <div v-if="lnurlAuthEnabled" class="mt-4">
          <button
            type="button"
            @click="handleLnurlAuth"
            :disabled="lnurlLoading"
            class="group relative w-full flex justify-center py-2 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            {{ lnurlLoading ? "Generating..." : "Login with Lightning" }}
          </button>
        </div>

        <div class="text-center text-sm">
          <router-link
            to="/login"
            class="font-medium text-indigo-600 hover:text-indigo-500"
          >
            Already have an account? Sign in
          </router-link>
        </div>
      </form>
    </div>

    <!-- LNURL-auth QR Modal -->
    <div
      v-if="showLnurlModal"
      class="fixed z-50 inset-0 overflow-y-auto"
      @click.self="closeLnurlModal"
    >
      <div
        class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
      >
        <div
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
          @click="closeLnurlModal"
        ></div>
        <div
          class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
        >
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                <!-- QR Code Step -->
                <div v-if="!showEmailStep">
                  <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Scan with Lightning Wallet
                  </h3>
                  <div class="flex justify-center mb-4">
                    <canvas
                      ref="qrCanvas"
                      class="border border-gray-300"
                    ></canvas>
                  </div>
                  <p class="text-sm text-gray-500 mb-4">
                    Scan this QR code with a Lightning wallet that supports
                    LNURL-auth (e.g., Breez, Phoenix, Alby).
                  </p>
                  <div v-if="lnurlPolling" class="text-sm text-blue-600">
                    Waiting for authentication...
                  </div>
                  <div v-if="lnurlError" class="text-sm text-red-600 mb-4">
                    {{ lnurlError }}
                  </div>
                </div>

                <!-- Email Input Step -->
                <div v-else>
                  <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Complete Registration
                  </h3>
                  <p class="text-sm text-gray-500 mb-4">
                    Please provide your email address to complete registration.
                  </p>
                  <form @submit.prevent="handleCompleteRegistration">
                    <div class="mb-4">
                      <label
                        for="lnurl-email"
                        class="block text-sm font-medium text-gray-700 mb-1"
                      >
                        Email address
                      </label>
                      <input
                        id="lnurl-email"
                        v-model="emailForm.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="your@email.com"
                      />
                      <div v-if="emailError" class="mt-2 text-sm text-red-600">
                        {{ emailError }}
                      </div>
                    </div>
                    <div class="flex justify-end">
                      <button
                        type="button"
                        @click="closeLnurlModal"
                        class="mr-3 inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm"
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        :disabled="emailLoading"
                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 sm:text-sm"
                      >
                        {{ emailLoading ? "Saving..." : "Continue" }}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <div
            v-if="!showEmailStep"
            class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"
          >
            <button
              type="button"
              @click="closeLnurlModal"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onUnmounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import api from "../../services/api";
import QRCode from "qrcode";
import { bech32 } from "bech32";

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
  email: "",
  password: "",
  password_confirmation: "",
});

const error = ref("");
const success = ref("");
const loading = ref(false);

const showLnurlModal = ref(false);
const lnurlLoading = ref(false);
const lnurlPolling = ref(false);
const lnurlError = ref("");
const lnurlK1 = ref("");
const qrCanvas = ref<HTMLCanvasElement | null>(null);
const showEmailStep = ref(false);
const emailForm = ref({
  email: "",
  user_id: null as number | null,
});
const emailLoading = ref(false);
const emailError = ref("");
let pollingInterval: number | null = null;

// Check if LNURL auth is enabled
const lnurlAuthEnabled = computed(() => {
  return import.meta.env.VITE_LNURL_AUTH_ENABLED === "true";
});

async function handleRegister() {
  error.value = "";
  success.value = "";
  loading.value = true;

  try {
    await authStore.register(
      form.value.email,
      form.value.password,
      form.value.password_confirmation
    );
    success.value =
      "Registration successful! Please check your email to verify your account.";
  } catch (err: any) {
    error.value =
      err.response?.data?.message || "Registration failed. Please try again.";
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat();
      error.value = errors.join(", ");
    }
  } finally {
    loading.value = false;
  }
}

async function handleLnurlAuth() {
  lnurlLoading.value = true;
  lnurlError.value = "";
  showEmailStep.value = false;
  emailError.value = "";

  try {
    // Request challenge
    const response = await api.post("/lnurl-auth/challenge");
    const { k1, lnurl } = response.data;

    lnurlK1.value = k1;
    showLnurlModal.value = true;
    lnurlLoading.value = false;

    // Wait for modal to be rendered, then generate QR code
    await nextTick();
    if (qrCanvas.value) {
      // Convert URL to LNURL format (bech32 encoded) for QR code
      const lnurlEncoded = encodeLnurl(lnurl);
      await QRCode.toCanvas(qrCanvas.value, lnurlEncoded, {
        width: 256,
        margin: 2,
      });
    }

    // Start polling for verification
    startPolling(k1);
  } catch (err: any) {
    lnurlError.value =
      err.response?.data?.error || "Failed to generate challenge";
    lnurlLoading.value = false;
  }
}

function startPolling(k1: string) {
  lnurlPolling.value = true;
  const startTime = Date.now();
  const timeout = 300000; // 5 minutes

  pollingInterval = window.setInterval(async () => {
    if (Date.now() - startTime > timeout) {
      lnurlError.value = "Challenge expired. Please try again.";
      stopPolling();
      return;
    }

    try {
      const statusResponse = await api.get(
        `/lnurl-auth/challenge-status/${k1}`
      );
      const { status, user_id } = statusResponse.data;

      if (status === "authenticated") {
        stopPolling();
        closeLnurlModal();
        await authStore.fetchUser();
        router.push("/");
      } else if (status === "pending_email") {
        stopPolling();
        emailForm.value.user_id = user_id;
        showEmailStep.value = true;
      } else if (status === "expired") {
        lnurlError.value = "Challenge expired. Please try again.";
        stopPolling();
      } else if (status === "error") {
        lnurlError.value = "An error occurred. Please try again.";
        stopPolling();
      }
    } catch (err: any) {
      console.error("Polling error:", err);
    }
  }, 3000);
}

async function handleCompleteRegistration() {
  if (!emailForm.value.user_id) {
    emailError.value = "Invalid session. Please try again.";
    return;
  }

  emailLoading.value = true;
  emailError.value = "";

  try {
    await api.post("/lnurl-auth/complete-registration", {
      user_id: emailForm.value.user_id,
      email: emailForm.value.email,
    });

    closeLnurlModal();
    alert(
      "Registration successful! Please check your email to verify your account."
    );
  } catch (err: any) {
    if (err.response?.data?.errors?.email) {
      emailError.value = err.response.data.errors.email[0];
    } else {
      emailError.value =
        err.response?.data?.message ||
        "Failed to complete registration. Please try again.";
    }
  } finally {
    emailLoading.value = false;
  }
}

function stopPolling() {
  if (pollingInterval !== null) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
  lnurlPolling.value = false;
}

function closeLnurlModal() {
  stopPolling();
  showLnurlModal.value = false;
  showEmailStep.value = false;
  lnurlError.value = "";
  lnurlK1.value = "";
  emailForm.value = {
    email: "",
    user_id: null,
  };
  emailError.value = "";
}

onUnmounted(() => {
  stopPolling();
});

// Convert URL to LNURL format (bech32 encoded)
function encodeLnurl(url: string): string {
  try {
    // Convert URL to uppercase for encoding (LNURL spec requirement)
    const urlUpper = url.toUpperCase();
    // Convert string to bytes
    const encoder = new TextEncoder();
    const bytes = encoder.encode(urlUpper);
    // Convert bytes to bech32 words (5-bit chunks)
    const words = bech32.toWords(Array.from(bytes));
    // Encode to bech32 with "lnurl" prefix (must be lowercase for checksum)
    const encoded = bech32.encode("lnurl", words, 1023);
    // Return uppercase LNURL prefix for QR code (some wallets expect uppercase prefix)
    return encoded.toUpperCase();
  } catch (error) {
    console.error("LNURL encoding failed:", error);
    return url;
  }
}
</script>
