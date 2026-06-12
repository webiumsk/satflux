<template>
  <div
    ref="rootRef"
    class="landing-pay-button-embed w-full max-w-sm rounded-xl border border-gray-700 bg-gray-900 p-5 shadow-xl"
  >
    <form
      method="POST"
      :action="invoiceActionUrl"
      class="btcpay-form btcpay-form--block"
    >
      <input
        type="hidden"
        name="storeId"
        value="AZi7YMtvvWrcc4uWbzD9V5f9niQ4Q6ehzAXsBvPEncVa"
      />
      <div class="btcpay-custom-container">
        <label class="sr-only" for="landing-pay-price">{{ t('landing.pay_button_amount_label') }}</label>
        <div class="btcpay-custom">
          <button
            class="plus-minus"
            type="button"
            data-type="-"
            data-step="1"
            data-min="1"
            data-max="20000"
            :aria-label="t('landing.pay_button_decrease')"
          >
            -
          </button>
          <input
            id="landing-pay-price"
            class="btcpay-input-price"
            type="number"
            name="price"
            min="1"
            max="20000"
            step="1"
            value="1"
            data-price="1"
            style="width: 3em"
          />
          <button
            class="plus-minus"
            type="button"
            data-type="+"
            data-step="1"
            data-min="1"
            data-max="20000"
            :aria-label="t('landing.pay_button_increase')"
          >
            +
          </button>
        </div>
        <label class="sr-only" for="landing-pay-currency">{{ t('landing.pay_button_currency_label') }}</label>
        <select id="landing-pay-currency" name="currency">
          <option value="USD">USD</option>
          <option value="GBP">GBP</option>
          <option value="EUR" selected>EUR</option>
          <option value="BTC">BTC</option>
        </select>
      </div>
      <button
        type="submit"
        class="submit"
        name="submit"
        title="Pay with BTCPay Server, a Self-Hosted Bitcoin Payment Processor"
      >
        <span class="submit-text">Pay with</span>
        <img
          :src="payButtonLogoUrl"
          alt=""
          class="submit-logo"
        />
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { useBtcPayUrl } from "../../composables/useBtcPayUrl";

const { t } = useI18n();

const FALLBACK_BTCPAY_BASE = "https://satflux.org";

const rootRef = ref<HTMLElement | null>(null);
const { btcPayUrl, load } = useBtcPayUrl();

const btcpayBase = computed(
  () => (btcPayUrl.value || FALLBACK_BTCPAY_BASE).replace(/\/$/, ""),
);

const invoiceActionUrl = computed(
  () => `${btcpayBase.value}/api/v1/invoices`,
);

const payButtonLogoUrl = computed(
  () => `${btcpayBase.value}/img/paybutton/logo.svg`,
);

function initBtcpayFormHandlers(root: HTMLElement) {
  function getLastValidPrice(el: HTMLInputElement, min: number): number {
    const stored = parseInt(el.dataset.lastValid || "", 10);
    if (!Number.isNaN(stored)) {
      return stored;
    }
    const initial = parseInt(el.dataset.price || "", 10);
    return Number.isNaN(initial) ? min : initial;
  }

  function setLastValidPrice(el: HTMLInputElement, value: number) {
    el.dataset.lastValid = String(value);
    el.value = String(value);
  }

  function handlePlusMinus(event: Event) {
    event.preventDefault();
    const target = event.target as HTMLButtonElement;
    const formRoot = target.closest(".btcpay-form");
    if (!formRoot) return;
    const el = formRoot.querySelector<HTMLInputElement>(".btcpay-input-price");
    if (!el) return;
    const step = parseInt(target.dataset.step || "", 10) || 1;
    const min = parseInt(target.dataset.min || "", 10) || 1;
    const max = parseInt(target.dataset.max || "", 10);
    const type = target.dataset.type;
    const price = parseInt(el.value, 10) || getLastValidPrice(el, min);
    let next = price;
    if (type === "-") {
      next = price - step < min ? min : price - step;
    } else if (type === "+") {
      next =
        !Number.isNaN(max) && price + step > max ? max : price + step;
    }
    setLastValidPrice(el, next);
  }

  function handlePriceInput(event: Event) {
    event.preventDefault();
    const el = event.target as HTMLInputElement;
    const formRoot = el.closest(".btcpay-form");
    if (!formRoot) return;
    const min = parseInt(el.getAttribute("min") || "", 10) || 1;
    const max = parseInt(el.getAttribute("max") || "", 10);
    const lastValid = getLastValidPrice(el, min);

    if (Number.isNaN(parseInt(el.value, 10))) {
      setLastValidPrice(el, lastValid);
      return;
    }

    let value = parseInt(el.value, 10);
    if (value < min) {
      value = min;
    } else if (!Number.isNaN(max) && value > max) {
      value = max;
    }
    setLastValidPrice(el, value);
  }

  root.querySelectorAll<HTMLButtonElement>(".btcpay-form .plus-minus").forEach((el) => {
    if (el.dataset.initialized) return;
    el.addEventListener("click", handlePlusMinus);
    el.dataset.initialized = "true";
  });

  root.querySelectorAll<HTMLInputElement>(".btcpay-form .btcpay-input-price").forEach((el) => {
    if (el.dataset.initialized) return;
    const min = parseInt(el.getAttribute("min") || "", 10) || 1;
    const initial = parseInt(el.value, 10);
    el.dataset.lastValid = String(Number.isNaN(initial) ? min : initial);
    el.addEventListener("input", handlePriceInput);
    el.dataset.initialized = "true";
  });
}

onMounted(async () => {
  await load();
  if (rootRef.value) {
    initBtcpayFormHandlers(rootRef.value);
  }
});
</script>

<style scoped>
.landing-pay-button-embed :deep(.btcpay-form) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  gap: 0.75rem;
}

.landing-pay-button-embed :deep(.btcpay-form--block) {
  flex-direction: column;
}

.landing-pay-button-embed :deep(.btcpay-form .btcpay-custom-container) {
  text-align: center;
  width: 100%;
}

.landing-pay-button-embed :deep(.btcpay-custom) {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.landing-pay-button-embed :deep(.btcpay-form .plus-minus) {
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  background: rgb(55 65 81);
  height: auto;
  width: auto;
  min-width: 2.5rem;
  min-height: 2.5rem;
  border: 1px solid rgb(75 85 99);
  border-radius: 0.375rem;
  margin: 0;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  color: #fff;
  font-weight: 600;
  padding: 0.5rem 0.75rem;
  user-select: none;
}

.landing-pay-button-embed :deep(.btcpay-form .plus-minus:hover) {
  background: rgb(75 85 99);
}

.landing-pay-button-embed :deep(.btcpay-form .plus-minus:active) {
  background: rgb(31 41 55);
}

.landing-pay-button-embed :deep(.btcpay-input-price) {
  -moz-appearance: textfield;
  appearance: textfield;
  border: 1px solid rgb(75 85 99);
  box-shadow: none;
  text-align: center;
  font-size: 1rem;
  margin: 0;
  border-radius: 0.375rem;
  line-height: 1.5;
  background: rgb(31 41 55);
  color: #fff;
  padding: 0.5rem 0.75rem;
  outline: none;
}

.landing-pay-button-embed :deep(.btcpay-input-price::-webkit-outer-spin-button),
.landing-pay-button-embed :deep(.btcpay-input-price::-webkit-inner-spin-button) {
  -webkit-appearance: none;
  margin: 0;
}

.landing-pay-button-embed :deep(.btcpay-form select) {
  -moz-appearance: none;
  -webkit-appearance: none;
  appearance: none;
  color: rgb(156 163 175);
  background: transparent;
  border: none;
  display: block;
  padding: 0;
  margin: 0 auto 0.25rem;
  font-size: 0.875rem;
  cursor: pointer;
  text-align: center;
}

.landing-pay-button-embed :deep(.btcpay-form select:hover) {
  color: rgb(209 213 219);
}

.landing-pay-button-embed :deep(.btcpay-form option) {
  color: #000;
  background: #fff;
}

.landing-pay-button-embed :deep(.btcpay-form .submit) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 168px;
  min-height: 46px;
  border-radius: 4px;
  border: none;
  background-color: #0f3b21;
  cursor: pointer;
  padding: 0 0.5rem;
}

.landing-pay-button-embed :deep(.submit-text) {
  color: #fff;
  font-size: 0.875rem;
  line-height: 1;
}

.landing-pay-button-embed :deep(.submit-logo) {
  height: 46px;
  display: inline-block;
  padding: 5% 0 5% 5px;
  vertical-align: middle;
}
</style>
