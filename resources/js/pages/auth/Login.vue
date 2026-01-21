<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-900 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Background Effects -->
    <div class="absolute inset-0 bg-gray-900">
      <div class="absolute top-0 -left-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob"></div>
      <div class="absolute bottom-0 -right-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-10 animate-blob animation-delay-2000"></div>
    </div>

    <div class="max-w-md w-full space-y-8 relative z-10">
      <div class="text-center">
        <router-link to="/" class="inline-block">
          <span class="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-500 tracking-tight">UZOL21</span>
        </router-link>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-white">
          Welcome back
        </h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          Sign in to your account
        </p>
      </div>
      
      <div class="bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 rounded-2xl p-8 shadow-xl">
        <form class="space-y-6" @submit.prevent="handleLogin">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-300">Email address</label>
            <div class="mt-1">
              <input
                id="email"
                v-model="form.email"
                name="email"
                type="email"
                autocomplete="email"
                required
                class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                placeholder="you@example.com"
              />
            </div>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
            <div class="mt-1">
              <input
                id="password"
                v-model="form.password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                placeholder="••••••••"
              />
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input
                id="remember-me"
                v-model="form.remember"
                name="remember-me"
                type="checkbox"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-600 rounded bg-gray-700"
              />
              <label for="remember-me" class="ml-2 block text-sm text-gray-300">
                Remember me
              </label>
            </div>

            <div class="text-sm">
              <router-link
                to="/password/reset"
                class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors"
              >
                Forgot password?
              </router-link>
            </div>
          </div>

          <div v-if="error" class="rounded-md bg-red-900/30 border border-red-500/30 p-4">
            <div class="text-sm text-red-200">{{ error }}</div>
          </div>

          <div class="space-y-4">
            <button
              type="submit"
              :disabled="loading"
              class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all shadow-lg shadow-indigo-600/20"
            >
              {{ loading ? "Signing in..." : "Sign in" }}
            </button>
            
            <div v-if="lnurlAuthEnabled">
              <div class="relative mb-4">
                <div class="absolute inset-0 flex items-center">
                  <div class="w-full border-t border-gray-600"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                  <span class="px-2 bg-gray-800 text-gray-400">Or continue with</span>
                </div>
              </div>
              
              <button
                type="button"
                @click="handleLnurlAuth"
                :disabled="lnurlLoading"
                class="group relative w-full flex justify-center items-center py-3 px-4 border border-gray-600 text-sm font-bold rounded-xl text-white bg-gray-700/50 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 transition-all"
              >
                <svg class="w-5 h-5 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                {{ lnurlLoading ? "Generating..." : "Lightning Login" }}
              </button>
            </div>
          </div>

          <div class="text-center text-sm">
            <span class="text-gray-400">Don't have an account?</span>
            <router-link
              to="/register"
              class="font-medium text-indigo-400 hover:text-indigo-300 ml-1 transition-colors"
            >
              Sign up
            </router-link>
          </div>
        </form>
      </div>
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
          class="fixed inset-0 bg-gray-900 bg-opacity-90 transition-opacity backdrop-blur-sm"
          @click="closeLnurlModal"
        ></div>
        <div
          class="inline-block align-bottom bg-gray-800 border border-gray-700 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
        >
          <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                <!-- QR Code Step -->
                <div v-if="!showEmailStep">
                  <h3 class="text-lg leading-6 font-bold text-white mb-4">
                    Scan with Lightning Wallet
                  </h3>
                  <div class="flex justify-center mb-6">
                     <div class="p-4 bg-white rounded-xl">
                        <canvas
                          ref="qrCanvas"
                          class="block"
                        ></canvas>
                     </div>
                  </div>
                  <p class="text-sm text-gray-400 mb-4 text-center">
                    Scan this QR code with a Lightning wallet that supports
                    LNURL-auth (e.g., Breez, Phoenix, Alby).
                  </p>
                  <div v-if="lnurlPolling" class="text-sm text-indigo-400 text-center animate-pulse">
                    Waiting for authentication...
                  </div>
                  <div v-if="lnurlError" class="text-sm text-red-400 mb-4 text-center">
                    {{ lnurlError }}
                  </div>
                </div>

                <!-- Email Input Step -->
                <div v-else>
                  <h3 class="text-lg leading-6 font-bold text-white mb-4">
                    Complete Registration
                  </h3>
                  <p class="text-sm text-gray-400 mb-6">
                    Please provide your email address to complete registration.
                  </p>
                  <form @submit.prevent="handleCompleteRegistration">
                    <div class="mb-6">
                      <label
                        for="lnurl-email"
                        class="block text-sm font-medium text-gray-300 mb-2"
                      >
                        Email address
                      </label>
                      <input
                        id="lnurl-email"
                        v-model="emailForm.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                        placeholder="your@email.com"
                      />
                      <div v-if="emailError" class="mt-2 text-sm text-red-400">
                        {{ emailError }}
                      </div>
                    </div>
                    <div class="flex justify-end gap-3">
                      <button
                        type="button"
                        @click="closeLnurlModal"
                        class="inline-flex justify-center rounded-lg border border-gray-600 px-4 py-2 bg-gray-700 text-base font-medium text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm transition-colors"
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        :disabled="emailLoading"
                        class="inline-flex justify-center rounded-lg border border-transparent px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 sm:text-sm transition-colors"
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
            class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-700"
          >
            <button
              type="button"
              @click="closeLnurlModal"
              class="w-full inline-flex justify-center rounded-lg border border-gray-600 px-4 py-2 bg-gray-700 text-base font-medium text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
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
  remember: false,
});

const error = ref("");
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

// Check if LNURL auth is enabled (this should come from API/env config)
const lnurlAuthEnabled = computed(() => {
  return import.meta.env.VITE_LNURL_AUTH_ENABLED === "true";
});

async function handleLogin() {
  error.value = "";
  loading.value = true;

  try {
    await authStore.login(
      form.value.email,
      form.value.password,
      form.value.remember
    );
    // Redirect to dashboard after login
    const redirect = router.currentRoute.value.query.redirect as string;
    router.push(redirect || "/dashboard");
  } catch (err: any) {
    error.value =
      err.response?.data?.message || "Login failed. Please try again.";
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
  const timeout = 300000; // 5 minutes (challenge expiration time)

  pollingInterval = window.setInterval(async () => {
    // Check if challenge expired
    if (Date.now() - startTime > timeout) {
      lnurlError.value = "Challenge expired. Please try again.";
      stopPolling();
      return;
    }

    try {
      // Poll challenge status endpoint
      const statusResponse = await api.get(
        `/lnurl-auth/challenge-status/${k1}`
      );
      const { status, user_id } = statusResponse.data;

      if (status === "authenticated") {
        // User is authenticated
        stopPolling();
        closeLnurlModal();
        await authStore.fetchUser();
        router.push("/dashboard");
      } else if (status === "pending_email") {
        // User needs to provide email
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
      // status === "pending" means challenge not yet consumed, continue polling
    } catch (err: any) {
      // Network or other errors - continue polling
      console.error("Polling error:", err);
    }
  }, 3000); // Poll every 3 seconds
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

    // Registration completed, show success message and close modal
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
    // Convert string to bytes (browser-compatible)
    const encoder = new TextEncoder();
    const bytes = encoder.encode(urlUpper);
    // Convert bytes to bech32 words (5-bit chunks)
    const words = bech32.toWords(Array.from(bytes));
    // Encode to bech32 with "lnurl" prefix (must be lowercase for checksum)
    const encoded = bech32.encode("lnurl", words, 1023);
    // Return uppercase LNURL prefix for QR code (some wallets expect uppercase prefix)
    // But bech32.encode returns lowercase, so we need to capitalize the prefix
    return encoded.toUpperCase();
  } catch (error) {
    // Fallback to raw URL if encoding fails
    console.error("LNURL encoding failed:", error);
    return url;
  }
}
</script>
