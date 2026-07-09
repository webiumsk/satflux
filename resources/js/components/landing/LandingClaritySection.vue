<template>
  <section
    id="clarity"
    class="landing-clarity relative scroll-mt-20 py-16 md:py-24 bg-gray-950 border-y border-gray-800/80 overflow-hidden"
    aria-labelledby="clarity-heading"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-b from-emerald-950/20 via-transparent to-violet-950/20"
      aria-hidden="true"
    ></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <p
        class="landing-clarity-motion text-xs font-mono uppercase tracking-[0.35em] text-orange-400/90 mb-4 text-center motion-safe:animate-sf-rise"
      >
        {{ t("landing.section_clarity_kicker") }}
      </p>
      <h2
        id="clarity-heading"
        class="landing-clarity-motion mx-auto mb-10 max-w-4xl text-balance text-center text-2xl font-extrabold leading-tight motion-safe:animate-sf-rise motion-safe:delay-75 sm:text-3xl md:mb-12 md:text-4xl"
      >
        <span
          class="bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90"
        >
          {{ t("landing.section_clarity_title") }}
        </span>
      </h2>

      <!-- Editorial bridge: engine vs dashboard -->
      <div
        class="landing-clarity-motion grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-5 md:gap-4 items-stretch mb-10 md:mb-12 max-w-4xl mx-auto motion-safe:animate-sf-rise motion-safe:delay-150"
      >
        <div
          class="relative min-h-[11rem] overflow-hidden rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950/50 via-gray-900/65 to-lime-950/20 p-5 text-left shadow-xl shadow-emerald-950/20 backdrop-blur-sm md:min-h-[12rem] md:p-6 md:text-right md:flex md:flex-col md:items-end"
        >
          <img
            src="/img/btcpay.svg"
            alt=""
            width="150"
            height="150"
            class="pointer-events-none absolute left-2 bottom-3 max-h-[6.5rem] w-auto max-w-[min(52%,11rem)] select-none object-contain object-left opacity-[0.2] md:left-4 md:top-1/2 md:bottom-auto md:max-h-36 md:max-w-[min(46%,10rem)] md:-translate-y-1/2 md:opacity-[0.18]"
            aria-hidden="true"
          />
          <div class="relative z-[1]">
            <div
              class="text-[10px] font-mono uppercase tracking-widest text-lime-300/90 mb-2"
            >
              {{ t("landing.flow_btcpay") }}
            </div>
            <p
              class="text-sm text-gray-300/95 leading-relaxed max-w-xs md:ml-auto"
            >
              {{ t("landing.flow_btcpay_sub") }}
            </p>
            <div
              class="mt-4 h-1 w-12 rounded-full bg-gradient-to-r from-emerald-400/80 to-yellow-300/70 md:ml-auto"
              aria-hidden="true"
            ></div>
          </div>
        </div>

        <div
          class="flex flex-row md:flex-col items-center justify-center gap-3 py-2 md:py-0"
          aria-hidden="true"
        >
          <span
            class="hidden md:block h-12 w-px bg-gradient-to-b from-transparent via-emerald-500/45 to-transparent"
          ></span>
          <div
            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-orange-500/35 bg-gradient-to-br from-emerald-500/10 via-gray-900/40 to-violet-500/10 text-orange-400 shadow-lg shadow-black/25"
          >
            <svg
              class="w-7 h-7"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.75"
                d="M13 10V3L4 14h7v7l9-11h-7z"
              />
            </svg>
          </div>
          <span
            class="hidden md:block h-12 w-px bg-gradient-to-b from-transparent via-violet-500/45 to-transparent"
          ></span>
          <span
            class="md:hidden flex-1 h-px max-w-[3rem] bg-gradient-to-r from-transparent via-emerald-500/45 to-transparent"
          ></span>
          <span
            class="md:hidden flex-1 h-px max-w-[3rem] bg-gradient-to-l from-transparent via-violet-500/45 to-transparent"
          ></span>
        </div>

        <div
          class="relative min-h-[11rem] overflow-hidden rounded-2xl border border-violet-500/35 bg-gradient-to-br from-violet-950/45 via-indigo-950/40 to-sky-950/25 p-5 text-left shadow-xl shadow-violet-950/25 backdrop-blur-sm md:min-h-[12rem] md:p-6"
        >
          <img
            src="/img/logo-satflux-white.svg"
            alt=""
            width="1130"
            height="1521"
            class="pointer-events-none absolute right-2 bottom-3 max-h-[6.5rem] w-auto max-w-[min(52%,11rem)] select-none object-contain object-right opacity-[0.2] md:right-4 md:top-1/2 md:bottom-auto md:max-h-36 md:max-w-[min(46%,10rem)] md:-translate-y-1/2 md:opacity-[0.18]"
            aria-hidden="true"
          />
          <div class="relative z-[1]">
            <div
              class="text-[10px] font-mono uppercase tracking-widest text-violet-200/90 mb-2"
            >
              {{ t("landing.flow_satflux") }}
            </div>
            <p class="text-sm text-gray-200/95 leading-relaxed max-w-xs">
              {{ t("landing.flow_satflux_sub") }}
            </p>
            <div
              class="mt-4 h-1 w-full max-w-[11rem] rounded-full bg-gradient-to-r from-violet-400/75 via-indigo-400/80 to-sky-400/70"
              aria-hidden="true"
            />
          </div>
        </div>
      </div>

      <p
        class="landing-clarity-motion text-base sm:text-lg text-gray-400 leading-relaxed max-w-3xl mx-auto text-center border-t border-gray-800/80 pt-10 motion-safe:animate-sf-rise motion-safe:delay-200"
      >
        {{ t("landing.section_clarity_body") }}
      </p>
    </div>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from "vue-i18n";

const { t } = useI18n();
</script>

<style scoped>
/* Subtle dot grain - keeps section distinct without heavy imagery */
.landing-clarity {
  background-image: radial-gradient(
    rgba(255, 255, 255, 0.045) 1px,
    transparent 1px
  );
  background-size: 22px 22px;
}
@media (prefers-reduced-motion: reduce) {
  .landing-clarity-motion {
    animation: none !important;
    opacity: 1 !important;
    transform: none !important;
  }
}
</style>
