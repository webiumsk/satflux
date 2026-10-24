<template>
  <div class="space-y-5">
    <div>
      <label
        for="ln-quick-address"
        class="block text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider"
      >
        {{ t("stores.ln_quick_address_label") }}
      </label>
      <input
        id="ln-quick-address"
        v-model="address"
        type="text"
        autocomplete="off"
        spellcheck="false"
        class="block w-full rounded-xl border-gray-600 bg-gray-900/50 text-white placeholder-gray-600 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-3"
        :placeholder="t('stores.ln_quick_address_placeholder')"
      />
      <p class="mt-2 text-sm text-gray-500 leading-relaxed">
        {{ t("stores.ln_quick_address_hint") }}
      </p>
    </div>

    <!-- Detected target chip -->
    <div
      v-if="route"
      class="flex items-center gap-2 rounded-xl border border-gray-700 bg-gray-900/40 px-4 py-3"
    >
      <span class="text-xs text-gray-500 uppercase tracking-wider">{{
        t("stores.wallet_detected_label")
      }}</span>
      <WalletTypeIcon
        :type="effectiveTarget === 'probe' ? 'lnaddress' : effectiveTarget"
        :brand="route.brand"
        size="sm"
        show-label
      />
      <span v-if="effectiveTarget === 'probe'" class="text-xs text-gray-500">
        {{ t("stores.ln_quick_probe_note") }}
      </span>
    </div>
    <p
      v-else-if="address.trim() !== ''"
      class="text-sm text-amber-300/90"
    >
      {{ t("stores.ln_quick_invalid_address") }}
    </p>

    <!-- A confirmed missing verification capability may offer the Cashu path. -->
    <p
      v-if="effectiveTarget === 'cashu' && probeState === 'cashu'"
      class="text-sm text-gray-400 leading-relaxed"
    >
      {{ t("stores.ln_quick_probe_cashu_fallback") }}
    </p>
    <p
      v-if="effectiveTarget === 'cashu' && cashuMintUrl"
      class="break-all text-sm text-gray-400"
    >
      {{ t("stores.ln_quick_cashu_mint", { mint: cashuMintUrl }) }}
    </p>

    <!-- Compact Cashu beta consent (only when routed to CashuMelt) -->
    <label
      v-if="effectiveTarget === 'cashu'"
      class="flex items-start gap-3 cursor-pointer select-none rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3"
    >
      <input
        v-model="cashuConsent"
        type="checkbox"
        class="mt-1 h-4 w-4 rounded border-gray-500 bg-gray-800 text-indigo-600 focus:ring-indigo-500"
      />
      <span class="text-sm text-amber-100 leading-relaxed">{{
        t("stores.ln_quick_cashu_consent")
      }}</span>
    </label>

    <div
      v-if="errorMessage"
      role="alert"
      class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300"
    >
      {{ errorMessage }}
    </div>

    <button
      type="button"
      :disabled="!canSubmit || submitting"
      class="px-6 py-3 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all w-full sm:w-auto"
      @click="submit"
    >
      <span v-if="probing">{{ t("stores.ln_quick_probe_checking") }}</span>
      <span v-else-if="submitting">{{ t("common.loading") }}</span>
      <span v-else>{{ t("stores.ln_quick_connect_button") }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { walletApi } from "../../../services/api";
import {
  routeLightningAddress,
  type LightningAddressTarget,
} from "../../../utils/lightningAddressRouting";
import { normalizeLnAddressConnectionString } from "../../../utils/lnAddressWalletBrands";
import { getApiErrorMessage } from "../../../composables/useApiError";
import { asApiError } from "../../../utils/apiError";
import WalletTypeIcon from "../../WalletTypeIcon.vue";

const props = defineProps<{
  storeId: string;
  /** Prefill when editing an existing Lightning-address connection. */
  initialAddress?: string;
}>();

const emit = defineEmits<{
  /** Fired after a successful save; target tells the parent where the store landed. */
  submitted: [target: Exclude<LightningAddressTarget, "probe">];
  /** The wallet-change grant expired: the parent re-runs the email code and restores the address. */
  "confirmation-required": [address: string];
}>();

const { t } = useI18n();

const address = ref(props.initialAddress ?? "");
const cashuConsent = ref(false);
const submitting = ref(false);
const probing = ref(false);
const errorMessage = ref("");
const cashuMintUrl = ref<string | null>(null);

/** Result of the server-side LUD-21 probe for the current address. */
const probeState = ref<"idle" | "lnaddress" | "cashu">("idle");

/** Bumped when the address, store or mounted form changes. */
let addressRevision = 0;

const route = computed(() => routeLightningAddress(address.value));

// Capabilities and consent belong to this exact address and store.
watch([address, () => props.storeId], () => {
  addressRevision += 1;
  probeState.value = "idle";
  cashuConsent.value = false;
  cashuMintUrl.value = null;
  errorMessage.value = "";
}, { flush: "sync" });

watch(() => props.initialAddress, (value) => {
  address.value = value ?? "";
}, { flush: "sync" });

onBeforeUnmount(() => { addressRevision += 1; });

/**
 * Where the current address would land: probe results upgrade 'probe' to
 * 'lnaddress' (LUD-21 supported) or downgrade it to 'cashu'.
 */
const effectiveTarget = computed((): LightningAddressTarget | null => {
  if (!route.value) return null;
  if (route.value.target !== "probe") return route.value.target;
  if (probeState.value === "lnaddress") return "lnaddress";
  if (probeState.value === "cashu") return "cashu";
  return "probe";
});

const canSubmit = computed(() => {
  if (!effectiveTarget.value) return false;
  if (effectiveTarget.value === "cashu" && (!cashuConsent.value || !cashuMintUrl.value)) return false;
  return true;
});

/** Accept only a complete server-provided alternative, never a client default mint. */
function offerCashu(alternatives: unknown): void {
  probeState.value = "idle";
  cashuConsent.value = false;
  cashuMintUrl.value = null;
  const alternative = Array.isArray(alternatives)
    ? alternatives.find((item) => item && item.type === "cashu") as Record<string, unknown> | undefined
    : undefined;
  if (alternative?.available === true && alternative.requires_consent === true
      && typeof alternative.mint_url === "string") {
    try {
      const url = new URL(alternative.mint_url);
      if (url.protocol === "https:" && !url.username && !url.password && !url.hash) {
        cashuMintUrl.value = alternative.mint_url;
        probeState.value = "cashu";
        return;
      }
    } catch {
      // An incomplete/invalid alternative must not lead to a wallet update.
    }
  }
  errorMessage.value = t("stores.ln_quick_cashu_unavailable");
}

async function submit() {
  const r = route.value;
  if (!r || submitting.value || !canSubmit.value) return;

  const revision = addressRevision;
  const storeId = props.storeId;
  const isCurrent = () => revision === addressRevision && storeId === props.storeId;

  submitting.value = true;
  errorMessage.value = "";
  try {
    let target = effectiveTarget.value;

    if (target === "probe") {
      probing.value = true;
      try {
        const probe = await walletApi.connection.lnaddressProbe(storeId, r.address);
        // The address changed while the probe was in flight - drop the stale
        // verdict instead of applying it to the new address.
        if (!isCurrent()) {
          return;
        }
        if (probe.lud21 && probe.reason === "ok") {
          probeState.value = "lnaddress";
        } else if (!probe.lud21 && probe.reason === "no_verify" && probe.outcome === "unsupported") {
          offerCashu(probe.alternatives);
          // The merchant must review the server-selected mint and consent
          // in a separate submission, including after a changed capability.
          return;
        } else {
          errorMessage.value = t("stores.ln_quick_probe_retry");
          return;
        }
        target = probeState.value;
      } finally {
        probing.value = false;
      }
    }

    if (!isCurrent()) return;
    if (target === "cashu") {
      if (!cashuConsent.value || !cashuMintUrl.value) return;
      await walletApi.cashu.updateSettings(storeId, {
        mint_url: cashuMintUrl.value,
        lightning_address: r.address,
        enabled: true,
      });
    } else if (target === "lnaddress") {
      await walletApi.connection.create(storeId, {
        type: "lnaddress",
        secret: r.connectionSecret ?? normalizeLnAddressConnectionString(r.address),
      });
    } else if (target === "blink") {
      await walletApi.connection.create(storeId, {
        type: "blink",
        secret: r.connectionSecret!,
      });
    } else {
      return;
    }
    if (isCurrent()) emit("submitted", target);
  } catch (rawError) {
    if (!isCurrent()) return;
    const err = asApiError(rawError);
    if (err.response?.data?.code === "wallet_change_confirmation_required") {
      emit("confirmation-required", address.value);
      return;
    }
    if (err.response?.status === 422 && err.response.data?.code === "lud21_unsupported") {
      offerCashu(err.response.data.alternatives);
      return;
    }
    const validationErrors = err.response?.data?.errors;
    if (err.response?.status === 422 && validationErrors) {
      const first = Object.values(validationErrors)[0];
      errorMessage.value = (Array.isArray(first) ? first[0] : first) ?? "";
    }
    if (!errorMessage.value) {
      errorMessage.value = getApiErrorMessage(
        rawError,
        t("stores.ln_quick_connect_error"),
      );
    }
  } finally {
    submitting.value = false;
  }
}
</script>
