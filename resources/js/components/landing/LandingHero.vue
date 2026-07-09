<template>
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
        class="absolute top-0 -left-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply opacity-20"
      ></div>
      <div
        class="absolute top-0 -right-4 w-80 h-80 bg-indigo-500 rounded-full mix-blend-multiply opacity-[0.18]"
      ></div>
      <div
        class="absolute -bottom-8 left-1/4 w-72 h-72 bg-orange-500 rounded-full mix-blend-multiply opacity-[0.12]"
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
                class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold"
              >
                {{ t("landing.hero_carousel_hint") }}
              </span>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-gray-700 p-2 text-gray-400 hover:border-indigo-500/40 hover:text-white hover:bg-gray-800/80 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
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
                  class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-gray-700 p-2 text-gray-400 hover:border-indigo-500/40 hover:text-white hover:bg-gray-800/80 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
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
                        {{ t("landing.flow_wallet_types") }}
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

          <!-- SK only: hero intro video - poster + play; iframe loads on click (no YT chrome until play) -->
          <div
            v-if="locale === 'sk'"
            class="mt-8 w-full max-w-md lg:max-w-none"
          >
            <p
              class="mb-3 text-center text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-500 lg:text-left"
            >
              {{ t("landing.hero_sk_video_heading") }}
            </p>
            <div
              class="relative aspect-video w-full overflow-hidden rounded-2xl border border-gray-700/80 bg-gray-950 shadow-xl shadow-black/40 ring-1 ring-white/5"
            >
              <iframe
                v-if="heroSkVideoPlaying"
                class="absolute inset-0 h-full w-full"
                :src="heroSkYoutubeEmbedUrl"
                :title="t('landing.hero_sk_video_iframe_title')"
                allow="
                  accelerometer;
                  autoplay;
                  clipboard-write;
                  encrypted-media;
                  gyroscope;
                  picture-in-picture;
                  web-share;
                "
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
              />
              <button
                v-else
                type="button"
                class="group absolute inset-0 z-[1] outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900"
                :aria-label="t('landing.hero_sk_video_play')"
                @click="heroSkVideoPlaying = true"
              >
                <img
                  :src="heroSkYoutubeThumbUrl"
                  alt=""
                  class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                  loading="lazy"
                  decoding="async"
                  @error="onHeroSkYoutubeThumbError"
                />
                <div
                  class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/35 to-gray-950/10"
                  aria-hidden="true"
                />
                <div
                  class="absolute bottom-[14px] left-[14px] z-[2] flex h-14 w-14 items-center justify-center rounded-full border border-white/25 bg-gray-950/55 shadow-lg shadow-black/50 backdrop-blur-md transition duration-300 group-hover:scale-110 group-hover:border-indigo-400/50 group-hover:bg-indigo-600/25 sm:bottom-4 sm:left-4 sm:h-[4.25rem] sm:w-[4.25rem]"
                  aria-hidden="true"
                >
                  <svg
                    class="ml-1 h-7 w-7 text-white drop-shadow-md sm:h-8 sm:w-8"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    aria-hidden="true"
                  >
                    <path d="M8 5v14l11-7L8 5z" />
                  </svg>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Dots, CTAs, trust - full width under copy + visual columns -->
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
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "../../store/auth";

const { t, locale } = useI18n();
const authStore = useAuthStore();

const HERO_SLIDE_COUNT = 3;
const heroSlideIndex = ref(0);
const heroTouchStartX = ref(0);

/** SK hero YouTube: poster-first so the embed UI does not show until the user plays. */
const HERO_SK_YOUTUBE_ID = "A1j4QkXk_Rk";
const heroSkVideoPlaying = ref(false);
const heroSkThumbTier = ref<"maxres" | "hq">("maxres");
const heroSkYoutubeThumbUrl = computed(
  () =>
    `https://i.ytimg.com/vi/${HERO_SK_YOUTUBE_ID}/${
      heroSkThumbTier.value === "maxres" ? "maxresdefault" : "hqdefault"
    }.jpg`,
);
const heroSkYoutubeEmbedUrl = computed(
  () =>
    `https://www.youtube-nocookie.com/embed/${HERO_SK_YOUTUBE_ID}?autoplay=1&modestbranding=1&rel=0`,
);

function onHeroSkYoutubeThumbError() {
  if (heroSkThumbTier.value === "maxres") {
    heroSkThumbTier.value = "hq";
  }
}

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
</script>

<style scoped>
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
