<template>
  <div
    class="min-h-screen bg-gray-900 text-white font-sans selection:bg-indigo-500 selection:text-white"
  >
    <!-- Public Header -->
    <PublicHeader />

    <!-- Hero Section -->
    <section
      class="relative overflow-hidden"
      aria-labelledby="landing-hero-heading"
    >
      <div
        class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(148,163,184,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.06)_1px,transparent_1px)] bg-[length:44px_44px] [mask-image:radial-gradient(ellipse_80%_60%_at_50%_0%,#000_40%,transparent)]"
        aria-hidden="true"
      />
      <div class="absolute inset-0 bg-gray-900">
        <div
          class="absolute top-0 -left-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"
        ></div>
        <div
          class="absolute top-0 -right-4 w-80 h-80 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-[0.18] animate-blob [animation-delay:2.5s]"
        ></div>
        <div
          class="absolute -bottom-8 left-1/4 w-72 h-72 bg-orange-500 rounded-full mix-blend-multiply filter blur-3xl opacity-[0.12] animate-blob [animation-delay:5s]"
        ></div>
      </div>

      <div
        class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24 lg:py-28"
      >
        <div
          class="grid lg:grid-cols-[1.05fr_minmax(0,440px)] xl:grid-cols-[1.1fr_minmax(0,480px)] gap-10 xl:gap-16 items-start touch-pan-y"
          @touchstart.passive="onHeroCarouselTouchStart"
          @touchend.passive="onHeroCarouselTouchEnd"
        >
          <!-- Copy column -->
          <div class="text-center lg:text-left order-2 lg:order-1">
            <p
              class="font-mono text-[11px] sm:text-xs uppercase tracking-[0.28em] text-orange-400/95 mb-5 max-w-xl mx-auto lg:mx-0 leading-relaxed text-balance"
            >
              {{ t("landing.hero_kicker") }}
            </p>
            <div
              class="inline-flex items-center px-3.5 py-1.5 rounded-full border border-indigo-500/35 bg-indigo-500/[0.08] text-indigo-200/95 text-xs sm:text-sm font-medium mb-6 backdrop-blur-sm"
            >
              <span class="relative flex h-2 w-2 mr-2">
                <span
                  class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-40"
                ></span>
                <span
                  class="relative inline-flex rounded-full h-2 w-2 bg-indigo-400"
                ></span>
              </span>
              {{ t("landing.hero_badge") }}
            </div>
            <h1
              id="landing-hero-heading"
              class="text-4xl sm:text-5xl xl:text-[3.35rem] xl:leading-[1.08] font-extrabold tracking-tight mb-5 text-balance"
            >
              <span
                class="bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90"
              >
                {{ t("landing.hero_headline") }}
              </span>
            </h1>
            <p
              class="text-lg sm:text-xl text-indigo-200/90 font-medium mb-6 max-w-2xl mx-auto lg:mx-0 leading-snug text-balance"
            >
              {{ t("landing.hero_subheadline") }}
            </p>

            <!-- Synced hero carousel: text + visuals share one index -->
            <div
              class="mb-8 max-w-xl mx-auto lg:mx-0 outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900 rounded-xl"
              role="region"
              tabindex="0"
              :aria-label="t('landing.hero_carousel_region')"
              @keydown.left.prevent="heroSlidePrev"
              @keydown.right.prevent="heroSlideNext"
            >
              <div
                class="flex flex-wrap items-center justify-between gap-3 mb-4"
              >
                <span
                  class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold"
                >
                  {{ t("landing.hero_carousel_hint") }}
                </span>
                <div class="flex items-center gap-1">
                  <button
                    type="button"
                    class="rounded-lg border border-gray-700 p-2 text-gray-400 hover:border-indigo-500/40 hover:text-white hover:bg-gray-800/80 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                    :aria-label="t('landing.hero_carousel_prev')"
                    @click="heroSlidePrev"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 19l-7-7 7-7"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded-lg border border-gray-700 p-2 text-gray-400 hover:border-indigo-500/40 hover:text-white hover:bg-gray-800/80 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                    :aria-label="t('landing.hero_carousel_next')"
                    @click="heroSlideNext"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5l7 7-7 7"
                      />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="relative min-h-[260px] sm:min-h-[240px]">
                <transition name="hero-slide" mode="out-in">
                  <div
                    v-if="heroSlideIndex === 0"
                    key="hero-copy-0"
                    class="text-left"
                  >
                    <p
                      class="text-gray-400 text-base sm:text-lg leading-relaxed"
                    >
                      {{ t("landing.hero_description") }}
                    </p>
                    <div
                      class="flex flex-wrap justify-center lg:justify-start gap-2 sm:gap-3 mt-6"
                    >
                      <span
                        v-for="chip in heroChips"
                        :key="chip"
                        class="inline-flex items-center rounded-lg border border-gray-600/60 bg-gray-800/40 px-3 py-1.5 text-xs sm:text-sm text-gray-300 backdrop-blur-sm transition-all duration-300 hover:border-indigo-500/35 hover:bg-gray-800/70 hover:-translate-y-0.5 hover:shadow-md hover:shadow-indigo-950/30"
                      >
                        <span class="mr-1.5 text-emerald-400" aria-hidden="true"
                          >✦</span
                        >
                        {{ chip }}
                      </span>
                    </div>
                  </div>
                  <div
                    v-else-if="heroSlideIndex === 1"
                    key="hero-copy-1"
                    class="text-left border-l-2 border-indigo-500/40 pl-4"
                  >
                    <p class="text-sm font-semibold text-white">
                      {{ t("landing.hero_audience_btcpay") }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1 mb-4">
                      {{ t("landing.hero_audience_btcpay_hint") }}
                    </p>
                    <p
                      class="text-sm sm:text-base text-gray-400 leading-relaxed"
                    >
                      {{ t("landing.hero_blurb_btcpay") }}
                    </p>
                  </div>
                  <div
                    v-else
                    key="hero-copy-2"
                    class="text-left border-l-2 border-orange-500/35 pl-4"
                  >
                    <p class="text-sm font-semibold text-white">
                      {{ t("landing.hero_audience_explore") }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1 mb-4">
                      {{ t("landing.hero_audience_explore_hint") }}
                    </p>
                    <p
                      class="text-sm sm:text-base text-gray-400 leading-relaxed"
                    >
                      {{ t("landing.hero_blurb_explore") }}
                    </p>
                  </div>
                </transition>
              </div>

              <p class="sr-only" aria-live="polite">
                {{
                  t("landing.hero_carousel_status", {
                    current: heroSlideIndex + 1,
                    total: HERO_SLIDE_COUNT,
                  })
                }}
              </p>
            </div>
          </div>

          <!-- Visual carousel (synced with copy) -->
          <div
            class="relative order-1 lg:order-2 mx-auto w-full max-w-md lg:max-w-none"
          >
            <div
              class="absolute -top-6 -right-6 w-32 h-32 rounded-full border border-dashed border-indigo-500/20 animate-sf-orbit opacity-60 motion-reduce:animate-none hidden sm:block"
              aria-hidden="true"
            />
            <div
              class="absolute -bottom-4 -left-2 w-24 h-24 rounded-full bg-gradient-to-tr from-orange-500/20 to-transparent blur-xl animate-sf-float motion-reduce:animate-none"
              aria-hidden="true"
            />

            <p
              class="text-center lg:text-left text-[10px] uppercase tracking-[0.2em] text-gray-500 mb-4 font-semibold min-h-[1.25rem]"
            >
              {{ heroVisualCaption }}
            </p>

            <div class="relative min-h-[300px] sm:min-h-[320px]">
              <transition name="hero-slide" mode="out-in">
                <!-- Slide 0: flow -->
                <div v-if="heroSlideIndex === 0" key="hero-vis-0">
                  <div
                    class="relative rounded-2xl border border-gray-700/80 bg-gray-950/40 backdrop-blur-md p-4 sm:p-5 shadow-2xl shadow-black/50"
                  >
                    <div class="flex justify-center mb-3">
                      <div
                        class="inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-full border border-orange-500/25 bg-gradient-to-r from-orange-500/10 to-indigo-500/10 px-3 py-1.5 text-[11px] font-bold text-white ring-1 ring-white/5"
                      >
                        <span
                          class="font-mono tracking-wide text-orange-300/95"
                          >{{ t("landing.flow_satflux") }}</span
                        >
                        <span class="text-gray-500 hidden sm:inline">·</span>
                        <span class="text-indigo-200/85 font-medium">{{
                          t("landing.flow_satflux_sub")
                        }}</span>
                      </div>
                    </div>
                    <div
                      class="flex flex-col sm:flex-row sm:items-stretch gap-3 sm:gap-2"
                    >
                      <div
                        class="flex-1 rounded-xl bg-gray-900/80 border border-gray-700/60 p-3 text-center sm:text-left"
                      >
                        <div
                          class="text-[10px] uppercase tracking-wider text-gray-500 mb-1"
                        >
                          {{ t("landing.flow_customer") }}
                        </div>
                        <div class="text-sm font-bold text-white">
                          Lightning / BTC
                        </div>
                      </div>
                      <div
                        class="hidden sm:flex items-center justify-center text-indigo-400/40 px-1"
                        aria-hidden="true"
                      >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24">
                          <path
                            d="M5 12h14M14 7l5 5-5 5"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-dasharray="6 6"
                            class="motion-safe:animate-pulse"
                          />
                        </svg>
                      </div>
                      <div
                        class="flex-[1.15] rounded-xl border border-indigo-500/35 bg-gradient-to-br from-indigo-950/90 to-gray-900/95 p-3 text-center relative overflow-hidden"
                      >
                        <div
                          class="absolute inset-0 opacity-25 bg-[length:200%_100%] animate-sf-shimmer motion-reduce:animate-none bg-gradient-to-r from-transparent via-indigo-400/15 to-transparent"
                          aria-hidden="true"
                        />
                        <div class="relative">
                          <div
                            class="text-[10px] uppercase tracking-wider text-indigo-300/90 mb-0.5"
                          >
                            {{ t("landing.flow_btcpay") }}
                          </div>
                          <div class="text-xs text-gray-400 leading-snug">
                            {{ t("landing.flow_btcpay_sub") }}
                          </div>
                        </div>
                      </div>
                      <div
                        class="hidden sm:flex items-center justify-center text-indigo-400/40 px-1"
                        aria-hidden="true"
                      >
                        <svg
                          class="w-6 h-6 motion-safe:animate-pulse"
                          fill="none"
                          viewBox="0 0 24 24"
                          style="animation-delay: 0.5s"
                        >
                          <path
                            d="M5 12h14M14 7l5 5-5 5"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-dasharray="6 6"
                          />
                        </svg>
                      </div>
                      <div
                        class="flex-1 rounded-xl bg-gray-900/80 border border-emerald-500/25 p-3 text-center sm:text-left"
                      >
                        <div
                          class="text-[10px] uppercase tracking-wider text-gray-500 mb-1"
                        >
                          {{ t("landing.flow_wallet") }}
                        </div>
                        <div class="text-sm font-bold text-emerald-300/95">
                          Blink · Aqua · Cashu
                        </div>
                      </div>
                    </div>
                    <div
                      class="sm:hidden flex justify-center py-1 text-gray-600"
                      aria-hidden="true"
                    >
                      ↓
                    </div>
                  </div>
                </div>

                <!-- Slide 1: stack mock -->
                <div
                  v-else-if="heroSlideIndex === 1"
                  key="hero-vis-1"
                  class="relative perspective-[1200px]"
                >
                  <div
                    class="absolute -inset-px rounded-2xl bg-gradient-to-r from-indigo-500 via-violet-500 to-orange-400 opacity-40 blur-sm motion-reduce:opacity-25"
                    aria-hidden="true"
                  />
                  <div
                    class="relative rounded-2xl border border-gray-600/50 bg-gray-900/90 backdrop-blur-xl overflow-hidden shadow-2xl"
                  >
                    <div
                      class="flex items-center gap-2 px-3 py-2 border-b border-gray-700/80 bg-black/20"
                    >
                      <div class="flex gap-1.5">
                        <div
                          class="w-2.5 h-2.5 rounded-full bg-red-500/90"
                        ></div>
                        <div
                          class="w-2.5 h-2.5 rounded-full bg-amber-400/90"
                        ></div>
                        <div
                          class="w-2.5 h-2.5 rounded-full bg-emerald-500/90"
                        ></div>
                      </div>
                      <span
                        class="text-[10px] font-mono text-gray-500 truncate"
                        >{{ t("landing.visual_live_badge") }}</span
                      >
                    </div>
                    <div class="p-4 space-y-3">
                      <div
                        class="rounded-xl border border-gray-700/60 bg-gray-950/50 p-3 flex justify-between items-center transform transition-transform duration-500 hover:-translate-y-0.5 hover:border-indigo-500/30 animate-sf-float motion-reduce:animate-none"
                      >
                        <div>
                          <div class="text-xs font-bold text-white">
                            {{ t("landing.visual_stores_label") }}
                          </div>
                          <div class="text-[11px] text-gray-500">
                            {{ t("landing.visual_stores_hint") }}
                          </div>
                        </div>
                        <div class="flex -space-x-1.5">
                          <span
                            class="h-7 w-7 rounded-lg bg-indigo-500/20 ring-2 ring-gray-900"
                          ></span>
                          <span
                            class="h-7 w-7 rounded-lg bg-violet-500/20 ring-2 ring-gray-900"
                          ></span>
                          <span
                            class="h-7 w-7 rounded-lg bg-orange-500/15 ring-2 ring-gray-900"
                          ></span>
                        </div>
                      </div>
                      <div
                        class="rounded-xl border border-gray-700/60 bg-gray-950/50 p-3 flex justify-between items-center ml-2 sm:ml-4 transform transition-transform duration-500 hover:-translate-y-0.5 hover:border-orange-500/25 animate-sf-float-slow motion-reduce:animate-none"
                      >
                        <div>
                          <div class="text-xs font-bold text-white">
                            {{ t("landing.visual_pos_label") }}
                          </div>
                          <div class="text-[11px] text-gray-500">
                            {{ t("landing.visual_pos_hint") }}
                          </div>
                        </div>
                        <span
                          class="text-[10px] font-mono text-orange-400/90 px-2 py-0.5 rounded bg-orange-500/10"
                          >LIVE</span
                        >
                      </div>
                      <div
                        class="rounded-xl border border-dashed border-gray-600/50 bg-gray-950/30 p-3 flex justify-between items-center ml-4 sm:ml-8 opacity-95"
                      >
                        <div>
                          <div class="text-xs font-bold text-gray-300">
                            {{ t("landing.visual_invoice_label") }} #2841
                          </div>
                          <div class="text-[11px] text-gray-500">
                            {{ t("landing.visual_invoice_hint") }}
                          </div>
                        </div>
                        <div
                          class="h-8 w-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center"
                        >
                          <svg
                            class="w-4 h-4 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                            />
                          </svg>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Slide 2: screenshot placeholder -->
                <div v-else key="hero-vis-2" class="relative">
                  <div
                    class="absolute -inset-px rounded-2xl bg-gradient-to-r from-indigo-500/40 via-violet-500/30 to-orange-400/40 opacity-60 blur-sm"
                    aria-hidden="true"
                  />
                  <div
                    class="relative flex min-h-[280px] flex-col items-center justify-center rounded-2xl border border-dashed border-gray-600/70 bg-gray-950/60 px-6 py-10 text-center backdrop-blur-sm"
                  >
                    <div
                      class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-indigo-500/25 bg-indigo-500/10 text-indigo-300"
                    >
                      <svg
                        class="h-8 w-8"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                      </svg>
                    </div>
                    <h3
                      class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-base font-bold"
                    >
                      {{ t("landing.hero_slide_preview_title") }}
                    </h3>
                    <p class="text-sm text-gray-500 max-w-xs leading-relaxed">
                      {{ t("landing.hero_slide_preview_body") }}
                    </p>
                  </div>
                </div>
              </transition>
            </div>
          </div>

          <!-- Dots, CTAs, trust — full width under copy + visual columns -->
          <div
            class="order-3 col-span-full flex w-full flex-col items-center gap-4 sm:gap-5 pt-6 lg:pt-8"
          >
            <div
              class="flex flex-wrap items-center justify-center gap-2"
              role="group"
              :aria-label="t('landing.hero_carousel_region')"
            >
              <button
                v-for="n in HERO_SLIDE_COUNT"
                :key="n"
                type="button"
                class="h-2 rounded-full transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900"
                :class="
                  heroSlideIndex === n - 1
                    ? 'w-8 bg-indigo-500'
                    : 'w-2 bg-gray-600 hover:bg-gray-500'
                "
                :aria-label="t('landing.hero_carousel_goto', { n })"
                :aria-current="heroSlideIndex === n - 1 ? true : undefined"
                @click="heroSlideGo(n - 1)"
              />
            </div>

            <div
              class="flex flex-col sm:flex-row flex-wrap gap-3 justify-center items-stretch sm:items-center"
            >
              <template v-if="!authStore.isAuthenticated">
                <router-link
                  to="/register"
                  class="inline-flex items-center justify-center px-7 py-3.5 text-base font-bold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 transition-all shadow-lg shadow-indigo-600/25 hover:shadow-indigo-500/35 hover:-translate-y-0.5"
                >
                  {{ t("landing.start_for_free") }}
                  <svg
                    class="ml-2 w-5 h-5 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 7l5 5m0 0l-5 5m5-5H6"
                    />
                  </svg>
                </router-link>
                <router-link
                  to="/login"
                  class="inline-flex items-center justify-center px-7 py-3.5 text-base font-bold rounded-xl text-white border border-gray-600 hover:bg-gray-800/80 transition-colors"
                >
                  {{ t("landing.sign_in") }}
                </router-link>
              </template>
              <router-link
                v-else
                to="/dashboard"
                class="inline-flex items-center justify-center px-7 py-3.5 text-base font-bold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 transition-all shadow-lg shadow-indigo-600/25"
              >
                <svg
                  class="w-5 h-5 mr-2 shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                  />
                </svg>
                {{ t("landing.go_to_dashboard") }}
              </router-link>
              <a
                href="#how-it-works"
                class="inline-flex items-center justify-center px-7 py-3.5 text-base font-semibold rounded-xl text-indigo-200 border border-indigo-500/35 bg-indigo-500/5 hover:bg-indigo-500/15 transition-colors"
              >
                {{ t("landing.hero_cta_how") }}
                <svg
                  class="ml-2 w-4 h-4 shrink-0 opacity-80"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 14l-7 7m0 0l-7-7m7 7V3"
                  />
                </svg>
              </a>
            </div>

            <p
              class="mx-auto flex max-w-md items-center justify-center gap-2 text-center text-xs text-gray-500 sm:text-sm"
            >
              <svg
                class="h-4 w-4 shrink-0 text-emerald-500/90"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                />
              </svg>
              <span>{{ t("landing.hero_trust_line") }}</span>
            </p>
          </div>
        </div>
      </div>
      <!-- Wave into next section -->
      <div
        class="absolute bottom-0 left-0 right-0 h-14 sm:h-20 pointer-events-none z-[1] text-gray-950"
        aria-hidden="true"
      >
        <svg
          class="w-full h-full block"
          viewBox="0 0 1440 80"
          preserveAspectRatio="none"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            fill="currentColor"
            d="M0 80V40C240 8 480 72 720 40C960 8 1200 72 1440 36V80H0Z"
          />
          <path
            fill="url(#landing-hero-wave-grad)"
            fill-opacity="0.35"
            d="M0 80V52C320 20 640 88 960 48C1180 20 1320 28 1440 44V80H0Z"
          />
          <defs>
            <linearGradient
              id="landing-hero-wave-grad"
              x1="0"
              y1="0"
              x2="1440"
              y2="0"
              gradientUnits="userSpaceOnUse"
            >
              <stop stop-color="#6366f1" />
              <stop offset="0.5" stop-color="#a855f7" />
              <stop offset="1" stop-color="#f97316" />
            </linearGradient>
          </defs>
        </svg>
      </div>
      <div
        class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent z-[2]"
        aria-hidden="true"
      ></div>
    </section>

    <!-- Clarity strip -->
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

    <!-- Features Section -->
    <section id="features" class="relative scroll-mt-20 py-24 bg-gray-900">
      <div
        class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent"
        aria-hidden="true"
      ></div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center text-center mb-16 md:mb-20">
          <h2
            class="mb-4 max-w-4xl text-balance text-3xl font-bold md:text-5xl"
          >
            <span
              class="bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90"
            >
              {{ t("landing.everything_you_need") }}
            </span>
          </h2>
          <p class="text-xl text-gray-400 max-w-2xl mx-auto leading-relaxed">
            {{ t("landing.everything_you_need_description") }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <!-- Multi-Store (Large Card) -->
          <div
            class="md:col-span-2 bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-8 border border-gray-700 shadow-xl overflow-hidden relative group transition-all duration-300 hover:-translate-y-1 hover:border-indigo-500/35 hover:shadow-2xl hover:shadow-indigo-950/50"
          >
            <div
              class="absolute top-0 right-0 p-8 opacity-10 group-hover:opacity-20 transition-opacity"
            >
              <svg
                class="w-48 h-48 text-indigo-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                />
              </svg>
            </div>
            <div class="relative z-10">
              <h3
                class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-2xl font-bold"
              >
                {{ t("landing.multi_store_management") }}
              </h3>
              <p class="text-gray-400 text-lg mb-6 max-w-md">
                {{ t("landing.multi_store_description") }}
              </p>
              <a
                href="#how-it-works"
                class="inline-flex items-center text-indigo-400 font-semibold group-hover:translate-x-1 transition-transform"
              >
                {{ t("landing.learn_more") }}
                <svg
                  class="w-4 h-4 ml-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 5l7 7-7 7"
                  />
                </svg>
              </a>
            </div>
          </div>

          <!-- POS Apps -->
          <div
            class="bg-gray-800 rounded-3xl p-8 border border-gray-700 shadow-xl relative group transition-all duration-300 hover:-translate-y-1 hover:border-indigo-500/30 hover:shadow-2xl hover:shadow-black/40"
          >
            <div
              class="absolute inset-0 bg-indigo-600/5 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity"
            ></div>
            <div
              class="w-12 h-12 bg-indigo-500/20 rounded-xl flex items-center justify-center mb-6 text-indigo-400"
            >
              <svg
                class="w-6 h-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
                />
              </svg>
            </div>
            <h3
              class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-xl font-bold"
            >
              {{ t("landing.point_of_sale") }}
            </h3>
            <p class="text-gray-400">
              {{ t("landing.point_of_sale_description") }}
            </p>
          </div>

          <!-- Invoice Management -->
          <div
            class="bg-gray-800 rounded-3xl p-8 border border-gray-700 shadow-xl relative group transition-all duration-300 hover:-translate-y-1 hover:border-indigo-500/30 hover:shadow-2xl hover:shadow-black/40"
          >
            <div
              class="absolute inset-0 bg-indigo-600/5 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity"
            ></div>
            <div
              class="w-12 h-12 bg-indigo-500/20 rounded-xl flex items-center justify-center mb-6 text-indigo-400"
            >
              <svg
                class="w-6 h-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            </div>
            <h3
              class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-xl font-bold"
            >
              {{ t("landing.invoicing") }}
            </h3>
            <p class="text-gray-400">
              {{ t("landing.invoicing_description") }}
            </p>
          </div>

          <!-- Lightning & Cashu (Large Card) -->
          <div
            class="md:col-span-2 bg-gradient-to-br from-amber-950/40 via-gray-900 to-indigo-950/50 rounded-3xl p-8 border border-gray-700 shadow-xl relative overflow-hidden group transition-all duration-300 hover:-translate-y-1 hover:border-amber-500/25 hover:shadow-2xl hover:shadow-amber-950/20"
          >
            <div
              class="absolute z-0 right-0 bottom-0 w-72 h-72 bg-amber-500/10 rounded-full blur-3xl"
            ></div>
            <div
              class="absolute z-0 left-1/3 top-0 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl"
            ></div>
            <div
              class="relative z-10 flex flex-col md:flex-row items-start md:items-center gap-8"
            >
              <div class="flex-1">
                <h3
                  class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-2xl font-bold"
                >
                  {{ t("landing.feature_lightning_ecash_title") }}
                </h3>
                <p class="text-gray-400 text-lg mb-6 leading-relaxed">
                  {{ t("landing.feature_lightning_ecash_description") }}
                </p>
                <ul class="space-y-2 text-sm text-gray-300 mb-6">
                  <li class="flex items-start gap-2">
                    <span
                      class="text-amber-400 mt-0.5 shrink-0"
                      aria-hidden="true"
                      >✓</span
                    >
                    <span>{{
                      t("landing.feature_lightning_ecash_bullet_ln")
                    }}</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span
                      class="text-amber-400 mt-0.5 shrink-0"
                      aria-hidden="true"
                      >✓</span
                    >
                    <span>{{
                      t("landing.feature_lightning_ecash_bullet_cashu")
                    }}</span>
                  </li>
                  <li class="flex items-start gap-2">
                    <span
                      class="text-amber-400 mt-0.5 shrink-0"
                      aria-hidden="true"
                      >✓</span
                    >
                    <span>{{
                      t("landing.feature_lightning_ecash_bullet_control")
                    }}</span>
                  </li>
                </ul>
                <div class="flex flex-wrap gap-2">
                  <span
                    class="px-3 py-1 rounded-md bg-gray-800/80 border border-amber-500/30 text-xs font-medium text-amber-200/90"
                    >Lightning</span
                  >
                  <span
                    class="px-3 py-1 rounded-md bg-gray-800/80 border border-indigo-500/30 text-xs font-medium text-indigo-200/90"
                    >Cashu</span
                  >
                  <span
                    class="px-3 py-1 rounded-md bg-gray-800/80 border border-gray-600 text-xs font-medium text-gray-300"
                    >SamRock</span
                  >
                </div>
              </div>
              <div class="flex shrink-0 gap-3 self-center md:self-auto">
                <div
                  class="w-14 h-14 bg-indigo-500/20 rounded-2xl flex items-center justify-center text-indigo-400 ring-1 ring-indigo-500/30"
                >
                  <svg
                    class="w-7 h-7"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z"
                    />
                  </svg>
                </div>
                <div
                  class="w-14 h-14 bg-amber-500/15 rounded-2xl flex items-center justify-center text-amber-400 ring-1 ring-amber-500/25"
                >
                  <svg
                    class="w-7 h-7"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                </div>
              </div>
            </div>
          </div>

          <!-- Tickets, LN addresses, Pay Button -->
          <div
            class="md:col-span-2 bg-gray-800 rounded-3xl p-8 border border-gray-700 shadow-xl relative group transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/25 hover:shadow-2xl hover:shadow-emerald-950/20"
          >
            <div
              class="absolute inset-0 bg-emerald-600/[0.04] rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"
            ></div>
            <div class="relative z-10">
              <div
                class="w-12 h-12 bg-emerald-500/15 rounded-xl flex items-center justify-center mb-6 text-emerald-400 ring-1 ring-emerald-500/25"
              >
                <svg
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"
                  />
                </svg>
              </div>
              <h3
                class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-2xl font-bold"
              >
                {{ t("landing.feature_checkout_tools_title") }}
              </h3>
              <p class="text-gray-400 text-lg mb-6 leading-relaxed">
                {{ t("landing.feature_checkout_tools_intro") }}
              </p>
              <ul class="space-y-2 text-sm text-gray-300">
                <li class="flex items-start gap-2">
                  <span
                    class="text-emerald-400 mt-0.5 shrink-0"
                    aria-hidden="true"
                    >✓</span
                  >
                  <span>{{
                    t("landing.feature_checkout_tools_bullet_tickets")
                  }}</span>
                </li>
                <li class="flex items-start gap-2">
                  <span
                    class="text-emerald-400 mt-0.5 shrink-0"
                    aria-hidden="true"
                    >✓</span
                  >
                  <span>{{
                    t("landing.feature_checkout_tools_bullet_ln")
                  }}</span>
                </li>
                <li class="flex items-start gap-2">
                  <span
                    class="text-emerald-400 mt-0.5 shrink-0"
                    aria-hidden="true"
                    >✓</span
                  >
                  <span>{{
                    t("landing.feature_checkout_tools_bullet_pay")
                  }}</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Crowdfund (coming soon) -->
          <div
            class="relative bg-gradient-to-br from-gray-800/90 to-gray-900 rounded-3xl p-8 border border-dashed border-violet-500/35 shadow-xl overflow-hidden group transition-all duration-300 hover:-translate-y-1 hover:border-violet-400/45 hover:shadow-2xl hover:shadow-violet-950/25"
          >
            <span
              class="absolute right-4 top-4 z-20 rounded-full bg-violet-500/20 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-violet-200 ring-1 ring-violet-400/30"
            >
              {{ t("landing.coming_soon_badge") }}
            </span>
            <div
              class="absolute inset-0 bg-violet-600/[0.04] pointer-events-none"
            ></div>
            <div class="relative z-10 pt-2">
              <div
                class="w-12 h-12 bg-violet-500/15 rounded-xl flex items-center justify-center mb-6 text-violet-300 ring-1 ring-violet-500/25"
              >
                <svg
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"
                  />
                </svg>
              </div>
              <h3
                class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 pr-24 text-xl font-bold"
              >
                {{ t("landing.feature_crowdfund_title") }}
              </h3>
              <p class="text-gray-400 leading-relaxed">
                {{ t("landing.feature_crowdfund_description") }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section
      id="how-it-works"
      class="relative scroll-mt-20 overflow-hidden bg-gray-900/85 py-20 md:py-28"
    >
      <div
        class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_100%_70%_at_50%_-10%,rgba(99,102,241,0.14),transparent_55%),radial-gradient(ellipse_55%_45%_at_85%_95%,rgba(251,146,60,0.09),transparent_60%)]"
        aria-hidden="true"
      />
      <div
        class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(148,163,184,0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.05)_1px,transparent_1px)] bg-[length:40px_40px] opacity-50 [mask-image:linear-gradient(180deg,#000_0%,#000_85%,transparent)]"
        aria-hidden="true"
      />
      <div
        class="absolute left-0 right-0 top-0 h-px bg-[linear-gradient(90deg,transparent,rgba(129,140,248,0.42),rgba(251,146,60,0.38),rgba(167,139,250,0.35),transparent)]"
        aria-hidden="true"
      />
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto mb-20 max-w-3xl text-center md:mb-28">
          <p
            class="mb-6 font-mono text-[11px] uppercase tracking-[0.3em] text-orange-400/90"
          >
            {{ t("landing.how_it_works_kicker") }}
          </p>
          <h2
            class="mb-5 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-4xl font-bold tracking-tight md:text-6xl"
          >
            {{ t("landing.how_it_works") }}
          </h2>
          <p class="text-lg text-indigo-200/85 md:text-xl md:leading-relaxed">
            {{ t("landing.how_it_works_intro") }}
          </p>
          <div
            class="mx-auto mt-8 h-1 w-24 rounded-full bg-gradient-to-r from-transparent via-indigo-500/60 to-transparent"
            aria-hidden="true"
          />
        </div>

        <div class="relative">
          <div
            class="pointer-events-none absolute bottom-0 left-6 top-0 w-px -translate-x-1/2 bg-gradient-to-b from-transparent via-indigo-500/35 via-50% to-transparent md:left-1/2"
            aria-hidden="true"
          />
          <div
            class="pointer-events-none absolute bottom-0 left-6 top-0 w-4 -translate-x-1/2 bg-gradient-to-b from-transparent via-indigo-500/25 to-transparent opacity-40 blur-md md:left-1/2"
            aria-hidden="true"
          />

          <div class="space-y-12 md:space-y-0">
            <!-- Step 1: Registration -->
            <div
              class="landing-howitworks-step flex flex-col md:flex-row items-center justify-between gap-10 py-16 md:py-24 relative group"
            >
              <!-- Dot on timeline (Desktop) -->
              <div
                class="absolute left-1/2 top-1/2 z-20 hidden h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-indigo-500/35 bg-gray-950/90 shadow-[0_0_32px_rgba(99,102,241,0.35)] backdrop-blur-sm md:flex motion-safe:transition-transform motion-safe:duration-300 group-hover:scale-110"
                aria-hidden="true"
              >
                <span
                  class="block h-3 w-3 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 shadow-[0_0_14px_rgba(167,139,250,0.85)]"
                />
              </div>
              <!-- Number on timeline (Mobile) -->
              <div
                class="absolute left-6 top-14 z-20 flex h-11 w-11 -translate-x-1/2 items-center justify-center rounded-full border-2 border-orange-400/60 bg-gray-950/95 font-mono text-sm font-bold text-orange-200 shadow-[0_0_24px_rgba(251,146,60,0.4)] ring-2 ring-orange-500/15 md:hidden"
              >
                1
              </div>

              <div
                class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1"
              >
                <div class="mb-4 flex justify-start md:justify-end">
                  <span
                    class="inline-flex rounded-lg border border-orange-500/35 bg-orange-500/10 px-2.5 py-1 font-mono text-[11px] font-bold tabular-nums tracking-[0.2em] text-orange-300/95"
                    aria-hidden="true"
                  >
                    01
                  </span>
                </div>
                <div
                  class="rounded-2xl border border-gray-700/10 p-6 shadow-xl shadow-black/25 ring-1 ring-white/[0.04] backdrop-blur-md transition-all duration-300 group-hover:border-orange-500/25 group-hover:bg-gray-950/55 group-hover:shadow-orange-950/10 md:p-8"
                >
                  <h3
                    class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
                  >
                    {{ t("landing.step1_title") }}
                  </h3>
                  <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                    {{ t("landing.step1_text") }}
                  </p>
                </div>
              </div>

              <div
                class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2"
              >
                <router-link
                  to="/register"
                  class="group block w-full max-w-md overflow-hidden rounded-2xl border border-gray-700 shadow-2xl transition-colors duration-500 hover:border-orange-500/40 hover:shadow-[0_0_32px_-6px_rgba(251,146,60,0.18)]"
                >
                  <img
                    src="/img/satflux-register.webp"
                    :alt="t('landing.alt_register')"
                    class="block h-auto w-full"
                    width="488"
                    height="608"
                    loading="lazy"
                    decoding="async"
                  />
                </router-link>
              </div>
            </div>

            <!-- Step 2: Wallet Connection -->
            <div
              class="landing-howitworks-step flex flex-col md:flex-row items-center justify-between gap-10 py-16 md:py-24 relative group"
            >
              <div
                class="absolute left-1/2 top-1/2 z-20 hidden h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-indigo-500/35 bg-gray-950/90 shadow-[0_0_32px_rgba(99,102,241,0.35)] backdrop-blur-sm md:flex motion-safe:transition-transform motion-safe:duration-300 group-hover:scale-110"
                aria-hidden="true"
              >
                <span
                  class="block h-3 w-3 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 shadow-[0_0_14px_rgba(167,139,250,0.85)]"
                />
              </div>
              <div
                class="absolute left-6 top-14 z-20 flex h-11 w-11 -translate-x-1/2 items-center justify-center rounded-full border-2 border-orange-400/60 bg-gray-950/95 font-mono text-sm font-bold text-orange-200 shadow-[0_0_24px_rgba(251,146,60,0.4)] ring-2 ring-orange-500/15 md:hidden"
              >
                2
              </div>

              <div
                class="md:w-5/12 flex justify-center md:justify-end pl-16 md:pl-0 pr-0 md:pr-16 order-2 md:order-1"
              >
                <div class="flex flex-col gap-10 w-full max-w-sm mt-12 md:mt-0">
                  <!-- Blink Card (Shifted left) -->
                  <div
                    class="relative group/card cursor-pointer transform md:-translate-x-12 transition-transform duration-500 hover:scale-105"
                  >
                    <!-- Card Content -->
                    <div
                      class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl flex items-center gap-6 transition-all duration-300 group-hover/card:border-orange-500/50 group-hover/card:bg-gray-800/80"
                    >
                      <img
                        src="/img/wallets/blink-64.webp"
                        :alt="t('landing.blink_wallet_title')"
                        class="w-20 h-20 rounded-2xl object-contain p-2 bg-white/5 border border-white/10 shadow-inner"
                      />
                      <div>
                        <h4
                          class="mb-1 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-2xl font-bold"
                        >
                          {{ t("landing.blink_wallet_title") }}
                        </h4>
                        <p class="text-sm text-orange-400 font-medium">
                          {{ t("landing.blink_wallet_tagline") }}
                        </p>
                      </div>
                    </div>

                    <!-- Hover Details (Tooltip/Overlay) -->
                    <div
                      class="absolute inset-0 bg-gray-900/95 backdrop-blur-xl rounded-2xl p-6 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300 flex flex-col justify-center border border-orange-500/50 z-20 pointer-events-none group-hover/card:pointer-events-auto shadow-[0_0_30px_rgba(249,115,22,0.3)]"
                    >
                      <div class="space-y-4">
                        <div>
                          <span
                            class="text-green-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1"
                          >
                            <svg
                              class="w-4 h-4"
                              fill="none"
                              stroke="currentColor"
                              viewBox="0 0 24 24"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="3"
                                d="M5 13l4 4L19 7"
                              ></path>
                            </svg>
                            {{ t("landing.wallet_pros") }}
                          </span>
                          <p class="text-gray-300 text-sm leading-relaxed">
                            {{ t("landing.blink_pros") }}
                          </p>
                        </div>
                        <div>
                          <span
                            class="text-red-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1"
                          >
                            <svg
                              class="w-4 h-4"
                              fill="none"
                              stroke="currentColor"
                              viewBox="0 0 24 24"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="3"
                                d="M6 18L18 6M6 6l12 12"
                              ></path>
                            </svg>
                            {{ t("landing.wallet_cons") }}
                          </span>
                          <p class="text-gray-300 text-sm leading-relaxed">
                            {{ t("landing.blink_cons") }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Aqua Card (Shifted right/default) -->
                  <div
                    class="relative group/card cursor-pointer transition-transform duration-500 hover:scale-105"
                  >
                    <!-- Card Content -->
                    <div
                      class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl flex items-center gap-6 transition-all duration-300 group-hover/card:border-blue-500/50 group-hover/card:bg-gray-800/80"
                    >
                      <div
                        class="w-20 h-20 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center p-2 shadow-inner"
                      >
                        <img
                          src="/img/wallets/aqua-64.webp"
                          :alt="t('landing.aqua_wallet_title')"
                          class="w-full h-full object-contain"
                        />
                      </div>
                      <div>
                        <h4
                          class="mb-1 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-2xl font-bold"
                        >
                          {{ t("landing.aqua_wallet_title") }}
                        </h4>
                        <p class="text-sm text-blue-400 font-medium">
                          {{ t("landing.aqua_wallet_tagline") }}
                        </p>
                      </div>
                    </div>

                    <!-- Hover Details -->
                    <div
                      class="absolute inset-0 bg-gray-900/95 backdrop-blur-xl rounded-2xl p-6 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300 flex flex-col justify-center border border-blue-500/50 z-20 pointer-events-none group-hover/card:pointer-events-auto shadow-[0_0_30px_rgba(59,130,246,0.3)]"
                    >
                      <div class="space-y-4">
                        <div>
                          <span
                            class="text-green-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1"
                          >
                            <svg
                              class="w-4 h-4"
                              fill="none"
                              stroke="currentColor"
                              viewBox="0 0 24 24"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="3"
                                d="M5 13l4 4L19 7"
                              ></path>
                            </svg>
                            {{ t("landing.wallet_pros") }}
                          </span>
                          <p class="text-gray-300 text-sm leading-relaxed">
                            {{ t("landing.aqua_pros") }}
                          </p>
                        </div>
                        <div>
                          <span
                            class="text-red-400 font-bold text-xs uppercase tracking-wider flex items-center gap-2 mb-1"
                          >
                            <svg
                              class="w-4 h-4"
                              fill="none"
                              stroke="currentColor"
                              viewBox="0 0 24 24"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="3"
                                d="M6 18L18 6M6 6l12 12"
                              ></path>
                            </svg>
                            {{ t("landing.wallet_cons") }}
                          </span>
                          <p class="text-gray-300 text-sm leading-relaxed">
                            {{ t("landing.aqua_cons") }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Cashu Card (Beta): hover panel grows with content and expands beyond card -->
                  <div
                    class="group/card cursor-pointer md:translate-x-6 transition-transform duration-500"
                  >
                    <div class="relative overflow-visible">
                      <div
                        class="relative z-10 flex w-full items-center gap-6 rounded-2xl border border-gray-700 bg-gray-800 p-6 shadow-xl ring-1 ring-amber-500/20 transition-all duration-300 group-hover/card:border-emerald-500/50 group-hover/card:bg-gray-800/80"
                      >
                        <div
                          class="w-20 h-20 shrink-0 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-2 shadow-inner flex items-center justify-center"
                        >
                          <svg
                            class="h-11 w-11 text-emerald-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="1.5"
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                          </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                          <h4
                            class="mb-1 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-2xl font-bold"
                          >
                            {{ t("landing.cashu_wallet_title") }}
                          </h4>
                          <p class="text-sm font-medium text-emerald-400/90">
                            {{ t("landing.cashu_wallet_tagline") }}
                          </p>
                        </div>
                      </div>
                      <span
                        class="pointer-events-none absolute right-3 top-3 z-40 rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-gray-900 shadow-lg ring-2 ring-gray-900 transition-all duration-300 group-hover/card:right-4 group-hover/card:top-4"
                        aria-label="Beta"
                      >
                        {{ t("landing.cashu_beta_badge") }}
                      </span>
                      <div
                        class="pointer-events-none absolute left-0 right-0 top-0 z-30 min-h-full h-auto rounded-2xl border border-emerald-500/50 bg-gray-900/95 opacity-0 shadow-lg shadow-emerald-950/20 ring-1 ring-emerald-500/20 backdrop-blur-xl transition-all duration-300 ease-out group-hover/card:pointer-events-auto group-hover/card:opacity-100 group-hover/card:-inset-x-4 group-hover/card:-top-3 group-hover/card:min-h-[calc(100%+0.75rem)] group-hover/card:shadow-[0_0_42px_rgba(16,185,129,0.35)] group-hover/card:ring-2 md:group-hover/card:-inset-x-5"
                      >
                        <div class="flex flex-col gap-3 p-5 md:p-6">
                          <p
                            class="border-b border-amber-500/25 pb-2 text-[11px] font-bold uppercase leading-snug tracking-wide text-amber-300"
                          >
                            {{ t("landing.cashu_beta_notice") }}
                          </p>
                          <div class="space-y-4">
                            <div>
                              <span
                                class="mb-1.5 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-green-400"
                              >
                                <svg
                                  class="h-4 w-4 shrink-0"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="3"
                                    d="M5 13l4 4L19 7"
                                  ></path>
                                </svg>
                                {{ t("landing.wallet_pros") }}
                              </span>
                              <p class="text-sm leading-relaxed text-gray-300">
                                {{ t("landing.cashu_pros") }}
                              </p>
                            </div>
                            <div>
                              <span
                                class="mb-1.5 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-red-400"
                              >
                                <svg
                                  class="h-4 w-4 shrink-0"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="3"
                                    d="M6 18L18 6M6 6l12 12"
                                  ></path>
                                </svg>
                                {{ t("landing.wallet_cons") }}
                              </span>
                              <p class="text-sm leading-relaxed text-gray-300">
                                {{ t("landing.cashu_cons") }}
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div
                class="md:w-5/12 text-left pl-16 md:pl-16 order-1 md:order-2"
              >
                <div class="mb-4 flex justify-start">
                  <span
                    class="inline-flex rounded-lg border border-orange-500/35 bg-orange-500/10 px-2.5 py-1 font-mono text-[11px] font-bold tabular-nums tracking-[0.2em] text-orange-300/95"
                    aria-hidden="true"
                    >02</span
                  >
                </div>
                <div
                  class="rounded-2xl border border-gray-700/10 p-6 shadow-xl shadow-black/25 ring-1 ring-white/[0.04] backdrop-blur-md transition-all duration-300 group-hover:border-orange-500/25 group-hover:bg-gray-950/55 group-hover:shadow-orange-950/10 md:p-8"
                >
                  <h3
                    class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
                  >
                    {{ t("landing.step2_title") }}
                  </h3>
                  <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                    {{ t("landing.step2_text") }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Step 3: PoS Terminal (Interactive) -->
            <div
              class="landing-howitworks-step flex flex-col md:flex-row items-center justify-between gap-10 py-16 md:min-h-[min(100svh,920px)] md:py-20 relative group"
            >
              <div
                class="absolute left-1/2 top-1/2 z-20 hidden h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-indigo-500/35 bg-gray-950/90 shadow-[0_0_32px_rgba(99,102,241,0.35)] backdrop-blur-sm md:flex motion-safe:transition-transform motion-safe:duration-300 group-hover:scale-110"
                aria-hidden="true"
              >
                <span
                  class="block h-3 w-3 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 shadow-[0_0_14px_rgba(167,139,250,0.85)]"
                />
              </div>
              <div
                class="absolute left-6 top-14 z-20 flex h-11 w-11 -translate-x-1/2 items-center justify-center rounded-full border-2 border-orange-400/60 bg-gray-950/95 font-mono text-sm font-bold text-orange-200 shadow-[0_0_24px_rgba(251,146,60,0.4)] ring-2 ring-orange-500/15 md:hidden"
              >
                3
              </div>

              <div
                class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1"
              >
                <div class="mb-4 flex justify-start md:justify-end">
                  <span
                    class="inline-flex rounded-lg border border-orange-500/35 bg-orange-500/10 px-2.5 py-1 font-mono text-[11px] font-bold tabular-nums tracking-[0.2em] text-orange-300/95"
                    aria-hidden="true"
                    >03</span
                  >
                </div>
                <div
                  class="rounded-2xl border border-gray-700/10 p-6 shadow-xl shadow-black/25 ring-1 ring-white/[0.04] backdrop-blur-md transition-all duration-300 group-hover:border-orange-500/25 group-hover:bg-gray-950/55 group-hover:shadow-orange-950/10 md:p-8"
                >
                  <h3
                    class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
                  >
                    {{ t("landing.step3_title") }}
                  </h3>
                  <p
                    class="mb-6 text-gray-400 text-lg md:text-xl leading-relaxed"
                  >
                    {{ t("landing.step3_text") }}
                  </p>
                  <p class="hidden text-sm text-emerald-400/85 md:block">
                    {{ t("landing.how_it_works_demo_real") }}
                  </p>
                  <div class="mt-4 flex justify-center md:hidden">
                    <button
                      type="button"
                      class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-3 text-base font-bold text-white shadow-lg transition-all hover:bg-indigo-500"
                      @click="showPosModal = true"
                    >
                      {{ t("landing.how_it_works_open_pos_mobile") }}
                    </button>
                  </div>
                </div>
              </div>

              <div
                class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2"
              >
                <div
                  class="hidden md:block w-full max-w-[380px] bg-gray-900 rounded-[2.5rem] border-[8px] border-gray-800 shadow-2xl overflow-hidden h-[800px] relative"
                >
                  <!-- Phone Notches -->
                  <div
                    class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-6 bg-gray-800 rounded-b-xl z-20"
                  ></div>
                  <!-- Iframe -->
                  <iframe
                    :src="posDemoUrl"
                    title="SATFLUX PoS Demo"
                    class="w-full h-full border-0 bg-gray-900"
                    allow="payment"
                    loading="lazy"
                  />
                  <!-- Overlay hint -->
                  <div
                    class="absolute bottom-4 left-0 right-0 text-center pointer-events-none"
                  >
                    <span
                      class="inline-block px-3 py-1 bg-black/50 text-white/50 text-xs rounded-full backdrop-blur-sm"
                      >{{ t("landing.live_demo") }}</span
                    >
                  </div>
                </div>
                <!-- Mobile Visual (Placeholder card instead of full iframe) -->
                <div
                  class="md:hidden w-full max-w-xs rounded-2xl border border-gray-700/80 bg-gray-950/50 p-4 shadow-inner ring-1 ring-white/[0.04]"
                >
                  <div
                    class="mb-2 text-center font-mono text-[10px] uppercase tracking-wider text-gray-500"
                  >
                    {{ t("landing.live_demo") }}
                  </div>
                  <div class="grid grid-cols-3 gap-2">
                    <div
                      v-for="n in 9"
                      :key="n"
                      class="flex aspect-square items-center justify-center rounded-lg border border-gray-700/50 bg-gray-800/60 text-sm font-medium text-gray-500"
                    >
                      {{ n }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 4: Integrations -->
            <div
              class="landing-howitworks-step flex flex-col md:flex-row items-center justify-between gap-10 py-16 md:py-24 relative group"
            >
              <div
                class="absolute left-1/2 top-1/2 z-20 hidden h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-indigo-500/35 bg-gray-950/90 shadow-[0_0_32px_rgba(99,102,241,0.35)] backdrop-blur-sm md:flex motion-safe:transition-transform motion-safe:duration-300 group-hover:scale-110"
                aria-hidden="true"
              >
                <span
                  class="block h-3 w-3 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 shadow-[0_0_14px_rgba(167,139,250,0.85)]"
                />
              </div>
              <div
                class="absolute left-6 top-14 z-20 flex h-11 w-11 -translate-x-1/2 items-center justify-center rounded-full border-2 border-orange-400/60 bg-gray-950/95 font-mono text-sm font-bold text-orange-200 shadow-[0_0_24px_rgba(251,146,60,0.4)] ring-2 ring-orange-500/15 md:hidden"
              >
                4
              </div>

              <div
                class="md:w-5/12 flex justify-start pl-16 md:justify-end pr-8 md:pr-16 order-2 md:order-1"
              >
                <div
                  class="flex max-w-md flex-wrap justify-start gap-3 md:justify-end"
                >
                  <div
                    class="flex h-14 min-w-[6.5rem] cursor-default items-center justify-center rounded-xl border border-gray-500/40 bg-gray-100 px-4 shadow-lg transition-transform delay-75 group-hover:-translate-y-1 md:px-5"
                  >
                    <img
                      src="/img/integrations/woo-wordmark.svg"
                      alt="WooCommerce"
                      title="WooCommerce"
                      class="h-4 w-auto max-w-[6.75rem] object-contain object-center"
                      width="184"
                      height="48"
                      loading="lazy"
                      decoding="async"
                    />
                  </div>
                  <div
                    class="flex h-14 min-w-[6.5rem] items-center justify-center rounded-xl border border-white/15 bg-[#95BF47] px-5 shadow-lg transition-transform delay-100 group-hover:-translate-y-1"
                  >
                    <img
                      src="/img/integrations/shopify.svg"
                      alt="Shopify"
                      title="Shopify"
                      class="h-8 w-auto max-w-[5.5rem] object-contain object-center [filter:brightness(0)_invert(1)]"
                      width="88"
                      height="32"
                      loading="lazy"
                      decoding="async"
                    />
                  </div>
                  <div
                    class="flex h-14 min-w-[6.5rem] items-center justify-center rounded-xl border border-white/15 bg-[#F46F25] px-5 shadow-lg transition-transform delay-150 group-hover:-translate-y-1"
                  >
                    <img
                      src="/img/integrations/magento.svg"
                      alt="Magento"
                      title="Magento"
                      class="h-8 w-auto max-w-[5rem] object-contain object-center [filter:brightness(0)_invert(1)]"
                      width="72"
                      height="36"
                      loading="lazy"
                      decoding="async"
                    />
                  </div>
                  <div
                    class="flex h-14 min-w-[6.5rem] items-center justify-center rounded-xl border border-white/15 bg-[#0678BE] px-5 shadow-lg transition-transform delay-200 group-hover:-translate-y-1"
                  >
                    <img
                      src="/img/integrations/drupal.svg"
                      alt="Drupal"
                      title="Drupal"
                      class="h-8 w-auto max-w-[4.5rem] object-contain object-center [filter:brightness(0)_invert(1)]"
                      width="72"
                      height="36"
                      loading="lazy"
                      decoding="async"
                    />
                  </div>
                  <div
                    class="mt-1 w-full rounded-xl border border-gray-700/60 bg-gray-950/60 p-3 text-center font-mono text-xs text-gray-400"
                  >
                    {{ t("landing.api_key_example") }}
                  </div>
                </div>
              </div>

              <div
                class="md:w-5/12 text-left pl-16 md:pl-16 order-1 md:order-2"
              >
                <div class="mb-4 flex justify-start">
                  <span
                    class="inline-flex rounded-lg border border-orange-500/35 bg-orange-500/10 px-2.5 py-1 font-mono text-[11px] font-bold tabular-nums tracking-[0.2em] text-orange-300/95"
                    aria-hidden="true"
                    >04</span
                  >
                </div>
                <div
                  class="rounded-2xl border border-gray-700/10 p-6 shadow-xl shadow-black/25 ring-1 ring-white/[0.04] backdrop-blur-md transition-all duration-300 group-hover:border-orange-500/25 group-hover:bg-gray-950/55 group-hover:shadow-orange-950/10 md:p-8"
                >
                  <h3
                    class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
                  >
                    {{ t("landing.step4_title") }}
                  </h3>
                  <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                    {{ t("landing.step4_text") }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Step 5: LN Address -->
            <div
              class="landing-howitworks-step flex flex-col md:flex-row items-center justify-between gap-10 py-16 md:py-24 relative group"
            >
              <div
                class="absolute left-1/2 top-1/2 z-20 hidden h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-indigo-500/35 bg-gray-950/90 shadow-[0_0_32px_rgba(99,102,241,0.35)] backdrop-blur-sm md:flex motion-safe:transition-transform motion-safe:duration-300 group-hover:scale-110"
                aria-hidden="true"
              >
                <span
                  class="block h-3 w-3 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 shadow-[0_0_14px_rgba(167,139,250,0.85)]"
                />
              </div>
              <div
                class="absolute left-6 top-14 z-20 flex h-11 w-11 -translate-x-1/2 items-center justify-center rounded-full border-2 border-orange-400/60 bg-gray-950/95 font-mono text-sm font-bold text-orange-200 shadow-[0_0_24px_rgba(251,146,60,0.4)] ring-2 ring-orange-500/15 md:hidden"
              >
                5
              </div>

              <div
                class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1"
              >
                <div class="mb-4 flex justify-start md:justify-end">
                  <span
                    class="inline-flex rounded-lg border border-orange-500/35 bg-orange-500/10 px-2.5 py-1 font-mono text-[11px] font-bold tabular-nums tracking-[0.2em] text-orange-300/95"
                    aria-hidden="true"
                    >05</span
                  >
                </div>
                <div
                  class="rounded-2xl border border-gray-700/10 p-6 shadow-xl shadow-black/25 ring-1 ring-white/[0.04] backdrop-blur-md transition-all duration-300 group-hover:border-orange-500/25 group-hover:bg-gray-950/55 group-hover:shadow-orange-950/10 md:p-8"
                >
                  <h3
                    class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
                  >
                    {{ t("landing.step5_title") }}
                  </h3>
                  <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                    {{
                      t("landing.step5_text", {
                        domain: displayLightningDomain || "…",
                      })
                    }}
                  </p>
                </div>
              </div>

              <div
                class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2"
              >
                <div
                  class="bg-gradient-to-r from-purple-600 to-indigo-600 p-1 rounded-2xl w-full max-w-sm shadow-xl shadow-purple-900/20 transform group-hover:scale-105 transition-transform duration-500"
                >
                  <div
                    class="bg-gray-900 rounded-xl p-8 text-center relative overflow-hidden"
                  >
                    <!-- Shine effect -->
                    <div
                      class="absolute inset-0 bg-gradient-to-tr from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"
                    ></div>
                    <div
                      class="w-16 h-16 bg-gray-800 rounded-full mb-4 flex items-center justify-center shadow-inner mx-auto"
                    >
                      <svg
                        class="w-8 h-8 text-yellow-400"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </div>
                    <div class="text-white font-mono text-lg md:text-xl">
                      satoshi<span
                        v-if="displayLightningDomain"
                        class="text-indigo-400"
                        >@{{ displayLightningDomain }}</span
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 6: Ticket Sale -->
            <div
              class="landing-howitworks-step flex flex-col md:flex-row items-center justify-between gap-10 py-16 md:py-24 relative group"
            >
              <div
                class="absolute left-1/2 top-1/2 z-20 hidden h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-indigo-500/35 bg-gray-950/90 shadow-[0_0_32px_rgba(99,102,241,0.35)] backdrop-blur-sm md:flex motion-safe:transition-transform motion-safe:duration-300 group-hover:scale-110"
                aria-hidden="true"
              >
                <span
                  class="block h-3 w-3 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 shadow-[0_0_14px_rgba(167,139,250,0.85)]"
                />
              </div>
              <div
                class="absolute left-6 top-14 z-20 flex h-11 w-11 -translate-x-1/2 items-center justify-center rounded-full border-2 border-orange-400/60 bg-gray-950/95 font-mono text-sm font-bold text-orange-200 shadow-[0_0_24px_rgba(251,146,60,0.4)] ring-2 ring-orange-500/15 md:hidden"
              >
                6
              </div>

              <div
                class="md:w-5/12 flex justify-start pl-16 md:justify-end pr-8 md:pr-16 order-2 md:order-1"
              >
                <div
                  class="w-full max-w-md bg-gray-800 rounded-2xl border border-gray-700 p-6 flex flex-col items-center gap-4 transform group-hover:scale-105 transition-transform duration-500 relative overflow-hidden"
                >
                  <!-- Background ticket watermark -->
                  <div class="absolute top-0 right-0 p-4 opacity-5">
                    <svg
                      class="w-36 h-36 text-orange-400"
                      fill="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        d="M15.58 16.8L12 14.5l-3.58 2.3 1.08-4.12L6.21 10l4.25-.26L12 5.8l1.54 3.94 4.25.26-3.29 2.68 1.08 4.12zM22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24z"
                      />
                    </svg>
                  </div>

                  <!-- Event card header -->
                  <div
                    class="w-full bg-gradient-to-r from-orange-500/20 to-yellow-500/20 rounded-xl border border-orange-500/30 p-4 relative z-10"
                  >
                    <div class="flex items-center gap-3 mb-2">
                      <div
                        class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center"
                      >
                        <svg
                          class="w-5 h-5 text-orange-400"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                          />
                        </svg>
                      </div>
                      <div>
                        <div class="text-white font-bold text-sm">
                          {{ t("landing.step6_badge_event") }}
                        </div>
                        <div class="text-orange-300/70 text-xs">
                          14 Mar 2026 &middot; 18:00
                        </div>
                      </div>
                    </div>
                    <div class="flex gap-4 text-xs">
                      <div class="flex items-center gap-1.5">
                        <svg
                          class="w-3.5 h-3.5 text-green-400"
                          fill="currentColor"
                          viewBox="0 0 20 20"
                        >
                          <path
                            d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 100 4v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2a2 2 0 100-4V6z"
                          />
                        </svg>
                        <span class="text-gray-300"
                          >{{ t("landing.step6_badge_tickets") }}:
                          <span class="text-green-400 font-bold"
                            >142</span
                          ></span
                        >
                      </div>
                      <div class="flex items-center gap-1.5">
                        <svg
                          class="w-3.5 h-3.5 text-indigo-400"
                          fill="currentColor"
                          viewBox="0 0 20 20"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                            clip-rule="evenodd"
                          />
                        </svg>
                        <span class="text-gray-300"
                          >{{ t("landing.step6_badge_checkin") }}:
                          <span class="text-indigo-400 font-bold"
                            >89</span
                          ></span
                        >
                      </div>
                    </div>
                  </div>

                  <!-- QR code / scan area -->
                  <div
                    class="w-full bg-gray-900 rounded-xl border border-gray-700 p-4 flex items-center gap-4 relative z-10"
                  >
                    <div
                      class="w-16 h-16 bg-white rounded-lg p-1.5 flex-shrink-0"
                    >
                      <!-- Stylized QR code -->
                      <svg viewBox="0 0 100 100" class="w-full h-full">
                        <rect
                          x="0"
                          y="0"
                          width="100"
                          height="100"
                          fill="white"
                        />
                        <rect
                          x="5"
                          y="5"
                          width="25"
                          height="25"
                          rx="3"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="10"
                          y="10"
                          width="15"
                          height="15"
                          rx="2"
                          fill="white"
                        />
                        <rect
                          x="13"
                          y="13"
                          width="9"
                          height="9"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="70"
                          y="5"
                          width="25"
                          height="25"
                          rx="3"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="75"
                          y="10"
                          width="15"
                          height="15"
                          rx="2"
                          fill="white"
                        />
                        <rect
                          x="78"
                          y="13"
                          width="9"
                          height="9"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="5"
                          y="70"
                          width="25"
                          height="25"
                          rx="3"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="10"
                          y="75"
                          width="15"
                          height="15"
                          rx="2"
                          fill="white"
                        />
                        <rect
                          x="13"
                          y="78"
                          width="9"
                          height="9"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="35"
                          y="5"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="48"
                          y="5"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="35"
                          y="18"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="5"
                          y="35"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="18"
                          y="35"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="35"
                          y="35"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#f59e0b"
                        />
                        <rect
                          x="48"
                          y="35"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="61"
                          y="35"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="5"
                          y="48"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="35"
                          y="48"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="48"
                          y="48"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#f59e0b"
                        />
                        <rect
                          x="61"
                          y="48"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="5"
                          y="61"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="48"
                          y="61"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="70"
                          y="48"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="83"
                          y="48"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="70"
                          y="61"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="83"
                          y="61"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="70"
                          y="70"
                          width="8"
                          height="8"
                          rx="1"
                          fill="#1a1a2e"
                        />
                        <rect
                          x="83"
                          y="83"
                          width="12"
                          height="12"
                          rx="2"
                          fill="#f59e0b"
                        />
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="text-xs text-gray-400 mb-1.5">
                        {{ t("landing.step6_scan_label") }}
                      </div>
                      <div class="flex items-center gap-2">
                        <div
                          class="flex-1 bg-gray-800 rounded-lg px-3 py-2 border border-gray-600 text-xs text-gray-500 font-mono truncate"
                        >
                          TKT-2026-00482
                        </div>
                        <div
                          class="w-8 h-8 bg-green-500/20 border border-green-500/40 rounded-lg flex items-center justify-center flex-shrink-0"
                        >
                          <svg
                            class="w-4 h-4 text-green-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2.5"
                              d="M5 13l4 4L19 7"
                            />
                          </svg>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Price in sats -->
                  <div
                    class="w-full flex items-center justify-between px-1 relative z-10"
                  >
                    <span class="text-xs text-gray-500 font-mono"
                      >21,000 sats / ticket</span
                    >
                    <span
                      class="text-xs font-bold text-orange-400 bg-orange-400/10 px-2.5 py-1 rounded-full border border-orange-400/20"
                      >&#9889; Lightning</span
                    >
                  </div>
                </div>
              </div>

              <div
                class="md:w-5/12 text-left pl-16 md:pl-16 order-1 md:order-2"
              >
                <div class="mb-4 flex justify-start">
                  <span
                    class="inline-flex rounded-lg border border-orange-500/35 bg-orange-500/10 px-2.5 py-1 font-mono text-[11px] font-bold tabular-nums tracking-[0.2em] text-orange-300/95"
                    aria-hidden="true"
                  >
                    06
                  </span>
                </div>
                <div
                  class="rounded-2xl border border-gray-700/10 p-6 shadow-xl shadow-black/25 ring-1 ring-white/[0.04] backdrop-blur-md transition-all duration-300 group-hover:border-orange-500/25 group-hover:bg-gray-950/55 group-hover:shadow-orange-950/10 md:p-8"
                >
                  <h3
                    class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
                  >
                    {{ t("landing.step6_title") }}
                  </h3>
                  <p
                    class="mb-6 text-gray-400 text-lg md:text-xl leading-relaxed"
                  >
                    {{ t("landing.step6_text") }}
                  </p>
                  <div class="space-y-3 border-t border-gray-700/50 pt-6">
                    <p
                      class="text-sm font-medium uppercase tracking-wider text-gray-500"
                    >
                      {{ t("landing.step6_plugins_title") }}
                    </p>
                    <div class="flex flex-wrap gap-3">
                      <a
                        href="https://github.com/webiumsk/btcpay-greenfield-tickets/releases"
                        target="_blank"
                        rel="noopener noreferrer"
                        title="BTCPay Satoshi Tickets for WooCommerce - GitHub Releases"
                        class="group inline-flex flex-wrap items-center gap-2 rounded-xl border border-gray-600 bg-gray-800/80 px-4 py-2.5 transition-all hover:border-orange-500/50 hover:bg-gray-800"
                      >
                        <span
                          class="font-semibold text-white transition-colors group-hover:text-orange-400"
                          >{{ t("landing.step6_plugin_woocommerce") }}</span
                        >
                        <img
                          src="https://img.shields.io/badge/license-MIT-blue.svg"
                          alt="MIT"
                          class="h-4"
                        />
                        <img
                          src="https://img.shields.io/github/v/release/webiumsk/btcpay-greenfield-tickets?label=release"
                          alt="release"
                          class="h-4"
                        />
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 7: Accounting Exports -->
            <div
              class="landing-howitworks-step flex flex-col md:flex-row items-center justify-between gap-10 py-16 md:py-24 relative group"
            >
              <div
                class="absolute left-1/2 top-1/2 z-20 hidden h-12 w-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-indigo-500/35 bg-gray-950/90 shadow-[0_0_32px_rgba(99,102,241,0.35)] backdrop-blur-sm md:flex motion-safe:transition-transform motion-safe:duration-300 group-hover:scale-110"
                aria-hidden="true"
              >
                <span
                  class="block h-3 w-3 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 shadow-[0_0_14px_rgba(167,139,250,0.85)]"
                />
              </div>
              <div
                class="absolute left-6 top-14 z-20 flex h-11 w-11 -translate-x-1/2 items-center justify-center rounded-full border-2 border-orange-400/60 bg-gray-950/95 font-mono text-sm font-bold text-orange-200 shadow-[0_0_24px_rgba(251,146,60,0.4)] ring-2 ring-orange-500/15 md:hidden"
              >
                7
              </div>

              <div
                class="md:w-5/12 text-left md:text-right pl-16 md:pl-0 pr-0 md:pr-16 order-1 md:order-1"
              >
                <div class="mb-4 flex justify-start md:justify-end">
                  <span
                    class="inline-flex rounded-lg border border-orange-500/35 bg-orange-500/10 px-2.5 py-1 font-mono text-[11px] font-bold tabular-nums tracking-[0.2em] text-orange-300/95"
                    aria-hidden="true"
                  >
                    07
                  </span>
                </div>
                <div
                  class="rounded-2xl border border-gray-700/10 p-6 shadow-xl shadow-black/25 ring-1 ring-white/[0.04] backdrop-blur-md transition-all duration-300 group-hover:border-orange-500/25 group-hover:bg-gray-950/55 group-hover:shadow-orange-950/10 md:p-8"
                >
                  <h3
                    class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
                  >
                    {{ t("landing.step7_title") }}
                  </h3>
                  <p class="text-gray-400 text-lg md:text-xl leading-relaxed">
                    {{ t("landing.step7_text") }}
                  </p>
                </div>
              </div>

              <div
                class="md:w-5/12 flex justify-start pl-16 md:pl-16 order-2 md:order-2"
              >
                <div
                  class="w-full max-w-md bg-gray-800 rounded-2xl border border-gray-700 p-6 flex flex-col items-center gap-6 transform group-hover:scale-105 transition-transform duration-500 relative overflow-hidden"
                >
                  <div class="absolute top-0 right-0 p-4 opacity-10">
                    <svg
                      class="w-32 h-32 text-indigo-500"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                  <div class="grid grid-cols-2 gap-4 w-full relative z-10">
                    <div
                      class="bg-gray-700/50 p-4 rounded-xl border border-gray-600 flex flex-col items-center hover:bg-green-500/10 hover:border-green-500/50 transition-colors"
                    >
                      <span class="font-bold text-white text-lg mb-1">CSV</span>
                      <span class="text-xs text-gray-400">{{
                        t("landing.export_csv_label")
                      }}</span>
                    </div>
                    <div
                      class="bg-gray-700/50 p-4 rounded-xl border border-gray-600 flex flex-col items-center hover:bg-red-500/10 hover:border-red-500/50 transition-colors"
                    >
                      <span class="font-bold text-white text-lg mb-1">PDF</span>
                      <span class="text-xs text-gray-400">{{
                        t("landing.export_pdf_label")
                      }}</span>
                    </div>
                  </div>
                  <div
                    class="w-full bg-gray-900 rounded-lg p-3 border border-gray-700 flex items-center justify-between text-xs text-gray-400 font-mono"
                  >
                    <span>export_2026_02.csv</span>
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                      ></path>
                    </svg>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- PoS Demo Modal (mobile) -->
    <Teleport to="body">
      <transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showPosModal"
          class="fixed inset-0 z-50 overflow-y-auto"
          role="dialog"
          aria-modal="true"
          aria-labelledby="pos-modal-title"
        >
          <div
            class="fixed inset-0 bg-gray-900/90 backdrop-blur-sm"
            @click="showPosModal = false"
          />
          <div class="flex min-h-full items-center justify-center p-4">
            <div
              class="relative w-full max-w-lg transform rounded-2xl bg-gray-800 border border-gray-700 shadow-2xl transition-all"
            >
              <div
                class="flex items-center justify-between px-4 py-3 border-b border-gray-700"
              >
                <h2
                  id="pos-modal-title"
                  class="bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-lg font-bold"
                >
                  {{ t("landing.how_it_works_demo_title") }}
                </h2>
                <button
                  type="button"
                  @click="showPosModal = false"
                  class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  :aria-label="t('landing.how_it_works_close_modal')"
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
              <div
                class="p-2 bg-gray-900 rounded-b-2xl"
                style="min-height: 500px"
              >
                <iframe
                  :src="posDemoUrl"
                  title="SATFLUX PoS Demo"
                  class="w-full h-[500px] rounded-lg border-0"
                  allow="payment"
                />
              </div>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- Pricing Section -->
    <section
      id="pricing"
      class="relative scroll-mt-20 py-24 bg-gray-900 overflow-hidden"
    >
      <div
        class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent z-10"
        aria-hidden="true"
      ></div>
      <div
        class="absolute inset-0 bg-indigo-900/10 skew-y-3 transform origin-bottom-right pointer-events-none z-0"
        aria-hidden="true"
      ></div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Pricing Hero -->
        <div class="text-center mb-16">
          <h2
            class="mb-4 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-3xl font-bold md:text-5xl"
          >
            {{ t("landing.pricing_hero_headline") }}
          </h2>
          <p class="text-xl text-gray-400 max-w-2xl mx-auto">
            {{ t("landing.pricing_hero_subheadline") }}
          </p>
        </div>

        <div
          class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto items-start"
        >
          <!-- Free Plan -->
          <div
            class="bg-gray-800/80 backdrop-blur rounded-2xl p-8 border border-gray-700 hover:border-gray-500 transition-colors"
          >
            <h3
              class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-xl font-bold"
            >
              {{ t("landing.pricing_free_name") }}
            </h3>
            <p class="text-gray-400 text-sm mb-4">
              {{ t("landing.pricing_free_tagline") }}
            </p>
            <div class="flex items-baseline mb-6">
              <span class="text-4xl font-extrabold text-white">{{
                formatSats(pricing.free.sats_per_year)
              }}</span>
              <span class="text-gray-400 ml-2">{{
                t("landing.pricing_free_price_period")
              }}</span>
            </div>
            <p class="text-gray-400 text-sm mb-6">
              {{ t("landing.pricing_free_description") }}
            </p>
            <ul class="space-y-3 mb-8">
              <li
                v-for="key in planFeatures.free.feature_keys"
                :key="key"
                class="flex items-start text-gray-300 text-sm"
              >
                <svg
                  class="w-4 h-4 text-green-400 mr-3 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  ></path>
                </svg>
                <span>{{ t("plans.features." + key) }}</span>
              </li>
            </ul>
            <router-link
              v-if="!authStore.isAuthenticated"
              to="/register"
              class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
            >
              {{ t("landing.pricing_free_cta") }}
            </router-link>
            <router-link
              v-else
              to="/stores"
              class="block w-full text-center px-6 py-3 rounded-lg border border-gray-600 text-white hover:bg-gray-700 font-bold transition-colors"
            >
              {{ t("landing.pricing_free_cta_logged_in") }}
            </router-link>
          </div>

          <!-- Pro Plan (Highlighted) -->
          <div
            class="bg-gray-800 rounded-2xl p-8 border-2 border-indigo-500 shadow-2xl relative transform md:scale-[1.02] z-10"
          >
            <div
              class="absolute top-0 right-0 -mt-4 mr-4 bg-indigo-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide"
            >
              {{ t("landing.pricing_pro_badge") }}
            </div>
            <h3
              class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-2xl font-bold"
            >
              {{ t("landing.pricing_pro_name") }}
            </h3>
            <p class="text-gray-400 text-sm mb-4">
              {{ t("landing.pricing_pro_tagline") }}
            </p>
            <div class="flex items-baseline flex-wrap gap-x-1 mb-6">
              <span
                v-if="
                  pricing.pro.sats_per_month_display !== BETA_PRO_SATS_PER_MONTH
                "
                class="text-xl font-medium text-gray-500 line-through mr-2"
                >{{ formatSats(pricing.pro.sats_per_month_display) }}</span
              >
              <span class="text-5xl font-extrabold text-white">{{
                formatSats(BETA_PRO_SATS_PER_MONTH)
              }}</span>
              <span class="text-indigo-300">{{
                t("landing.pricing_pro_price_period")
              }}</span>
              <span class="text-indigo-300/80 text-sm">{{
                t("landing.pricing_pro_price_note")
              }}</span>
            </div>
            <p class="text-gray-400 text-sm mb-6">
              {{ t("landing.pricing_pro_description") }}
            </p>
            <ul class="space-y-3 mb-8">
              <li
                v-for="key in planFeatures.pro.feature_keys"
                :key="key"
                class="flex items-start text-white font-medium text-sm"
              >
                <svg
                  class="w-5 h-5 text-indigo-400 mr-3 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  ></path>
                </svg>
                <span>{{ t("plans.features." + key) }}</span>
              </li>
            </ul>
            <button
              v-if="authStore.isAuthenticated"
              @click="handleUpgrade('pro')"
              :disabled="subscribing"
              class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{
                subscribing
                  ? t("stores.processing")
                  : t("landing.pricing_pro_cta")
              }}
            </button>
            <router-link
              v-else
              to="/register"
              class="block w-full text-center px-6 py-4 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all"
            >
              {{ t("landing.pricing_pro_cta_guest") }}
            </router-link>
          </div>
        </div>

        <!-- Need more? Enterprise teaser -->
        <div class="mt-12 max-w-2xl mx-auto">
          <div class="bg-gray-800/50 rounded-2xl p-6 border border-gray-700">
            <h3
              class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-lg font-bold"
            >
              {{ t("landing.pricing_need_more_headline") }}
            </h3>
            <p class="text-gray-400 text-sm mb-4">
              {{ t("landing.pricing_need_more_text") }}
            </p>
            <ul class="space-y-2 mb-6 text-gray-300 text-sm">
              <li
                v-for="key in planFeatures.enterprise.feature_keys"
                :key="key"
                class="flex items-center gap-2"
              >
                <svg
                  class="w-4 h-4 text-indigo-400 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  ></path>
                </svg>
                <span>{{ t("plans.features." + key) }}</span>
              </li>
            </ul>
            <a
              href="mailto:hello@satflux.io"
              class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 font-medium text-sm transition-colors"
            >
              {{ t("landing.pricing_need_more_cta") }}
            </a>
          </div>
        </div>

        <!-- Error message -->
        <div v-if="subscribeError" class="mt-8 max-w-md mx-auto">
          <div
            class="bg-red-900/50 border border-red-500/50 rounded-lg p-4 text-center"
          >
            <p class="text-sm text-red-200">{{ subscribeError }}</p>
          </div>
        </div>

        <!-- Your payments never stop -->
        <div class="mt-16 max-w-3xl mx-auto">
          <div
            class="bg-indigo-900/20 border border-indigo-500/30 rounded-xl p-6 text-center"
          >
            <div class="flex items-center justify-center mb-3">
              <svg
                class="w-6 h-6 text-indigo-400 mr-2"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                />
              </svg>
              <h3
                class="bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-lg font-bold"
              >
                {{ t("landing.pricing_trust_headline") }}
              </h3>
            </div>
            <p class="text-gray-300 text-sm leading-relaxed">
              {{ t("landing.pricing_trust_text") }}
            </p>
          </div>
        </div>

        <!-- FAQ -->
        <div class="mt-16 max-w-2xl mx-auto">
          <h3
            class="mb-6 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 text-center text-xl font-bold"
          >
            {{ t("landing.pricing_faq_title") }}
          </h3>
          <div class="space-y-4">
            <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
              <h4
                class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 font-semibold"
              >
                {{ t("landing.pricing_faq_custody_q") }}
              </h4>
              <p class="text-gray-400 text-sm">
                {{ t("landing.pricing_faq_custody_a") }}
              </p>
            </div>
            <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
              <h4
                class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 font-semibold"
              >
                {{ t("landing.pricing_faq_switch_q") }}
              </h4>
              <p class="text-gray-400 text-sm">
                {{ t("landing.pricing_faq_switch_a") }}
              </p>
            </div>
            <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
              <h4
                class="mb-2 bg-clip-text text-transparent bg-gradient-to-br from-white via-white to-indigo-300/90 font-semibold"
              >
                {{ t("landing.pricing_faq_expire_q") }}
              </h4>
              <p class="text-gray-400 text-sm">
                {{ t("landing.pricing_faq_expire_a") }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from "../store/auth";
import { usePricing, BETA_PRO_SATS_PER_MONTH } from "../composables/usePricing";
import { usePlanFeatures } from "../composables/usePlanFeatures";
import { onMounted, ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import PublicHeader from "../components/layout/PublicHeader.vue";
import AppFooter from "../components/layout/AppFooter.vue";
import api from "../services/api";
import { useBtcPayUrl } from "../composables/useBtcPayUrl";

const { t } = useI18n();
const {
  btcPayUrl,
  load: loadBtcpayConfig,
  displayLightningDomain,
} = useBtcPayUrl();
const { pricing, formatSats, load: loadPricing } = usePricing();
const { planFeatures, load: loadPlanFeatures } = usePlanFeatures();

//const router = useRouter();
const authStore = useAuthStore();
const subscribing = ref(false);
const subscribeError = ref("");
const showPosModal = ref(false);

const HERO_SLIDE_COUNT = 3;
const heroSlideIndex = ref(0);
const heroTouchStartX = ref(0);

const heroChips = computed(() => [
  t("landing.hero_chip_custody"),
  t("landing.hero_chip_surface"),
  t("landing.hero_chip_exports"),
]);

const heroVisualCaption = computed(() => {
  if (heroSlideIndex.value === 0) return t("landing.flow_caption");
  if (heroSlideIndex.value === 1) return t("landing.visual_live_badge");
  return t("landing.hero_visual_caption_preview");
});

function heroSlideNext() {
  heroSlideIndex.value = (heroSlideIndex.value + 1) % HERO_SLIDE_COUNT;
}

function heroSlidePrev() {
  heroSlideIndex.value =
    (heroSlideIndex.value - 1 + HERO_SLIDE_COUNT) % HERO_SLIDE_COUNT;
}

function heroSlideGo(i: number) {
  heroSlideIndex.value = Math.max(0, Math.min(HERO_SLIDE_COUNT - 1, i));
}

function onHeroCarouselTouchStart(e: TouchEvent) {
  heroTouchStartX.value = e.changedTouches[0]?.screenX ?? 0;
}

function onHeroCarouselTouchEnd(e: TouchEvent) {
  const endX = e.changedTouches[0]?.screenX ?? 0;
  const delta = endX - heroTouchStartX.value;
  if (delta < -48) heroSlideNext();
  else if (delta > 48) heroSlidePrev();
}

/** Prefer `VITE_POS_DEMO_URL`; otherwise uses `BTCPAY_BASE_URL` from the server via `/api/config`. */
const posDemoUrl = computed(() => {
  const vite = (import.meta.env.VITE_POS_DEMO_URL as string) || "";
  if (vite) return vite;
  const b = btcPayUrl.value;
  return b ? `${b}/` : "";
});

// Ensure user, pricing and plan features are fetched on mount; anchor smooth-scroll
onMounted(async () => {
  await Promise.all([loadPricing(), loadPlanFeatures(), loadBtcpayConfig()]);
  if (!authStore.user) {
    try {
      await authStore.fetchUser();
    } catch {
      // User not authenticated, which is fine
    }
  }

  if (window.location.hash) {
    setTimeout(() => {
      const target = document.querySelector(window.location.hash);
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }, 100);
  }

  const anchors = document.querySelectorAll('a[href^="#"]');
  anchors.forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      const targetAnchor = e.currentTarget as HTMLAnchorElement;
      const href = targetAnchor.getAttribute("href");
      if (href && href !== "#") {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      }
    });
  });
});

async function handleUpgrade(plan: string) {
  // Both Pro and Enterprise can use the same checkout flow now
  subscribing.value = true;
  subscribeError.value = "";

  try {
    // Call checkout endpoint with plan name
    // Backend will look up storeUuid, offeringId, and planId from config
    const response = await api.post("/subscriptions/checkout", {
      plan: plan,
    });

    if (response.data.checkoutUrl) {
      window.location.href = response.data.checkoutUrl;
    } else {
      subscribeError.value = t("landing.failed_to_create_checkout");
      subscribing.value = false;
    }
  } catch (error: any) {
    console.error("Failed to create checkout:", error);
    subscribeError.value =
      error.response?.data?.message || t("landing.failed_to_create_checkout");
    subscribing.value = false;
  }
}
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

.hero-slide-enter-active,
.hero-slide-leave-active {
  transition:
    opacity 0.22s ease,
    transform 0.22s ease;
}
.hero-slide-enter-from {
  opacity: 0;
  transform: translateX(14px);
}
.hero-slide-leave-to {
  opacity: 0;
  transform: translateX(-14px);
}
@media (prefers-reduced-motion: reduce) {
  .hero-slide-enter-active,
  .hero-slide-leave-active {
    transition: opacity 0.12s ease;
  }
  .hero-slide-enter-from,
  .hero-slide-leave-to {
    transform: none;
  }
}
</style>
