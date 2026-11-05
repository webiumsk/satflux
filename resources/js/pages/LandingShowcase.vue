<template>
  <div class="landing-showcase">
    <PublicHeader />
    <div class="showcase-body">
      <div class="ambient-glow" aria-hidden="true">
      </div>
      <main id="top">
        <section class="hero" aria-labelledby="hero-title">
          <div class="wrap hero-grid">
            <div class="hero-copy">
              <p class="eyebrow">
                <span class="small-dot" aria-hidden="true">
                </span>
                {{ t('showcase.hero.eyebrow') }}
              </p>
              <h1 id="hero-title" class="hero-title">
                {{ t('showcase.hero.line1') }}
                <br>
                {{ t('showcase.hero.line2') }}
                <br>
                <span>
                  {{ t('showcase.hero.line3') }}
                </span>
              </h1>
              <p class="hero-intro">
                {{ t('showcase.hero.intro') }}
              </p>
              <div class="actions">
                <router-link class="btn btn-primary" :to="accountDestination">
                  {{ t('showcase.hero.start') }}
                  <span aria-hidden="true">
                    ↗
                  </span>
                </router-link>
                <button class="btn btn-secondary" type="button" @click="showDemo">
                  ⚡
                  {{ t('showcase.hero.demo') }}
                </button>
              </div>
              <p class="hero-note">
                <span aria-hidden="true">
                  ✓
                </span>
                {{ t('showcase.hero.note') }}
              </p>
              <div class="powered">
                <span>
                  {{ t('showcase.hero.powered') }}
                </span>
                <strong>
                  BTCPay Server + Evolu
                </strong>
                <span class="separator" aria-hidden="true">
                </span>
                <span>
                  {{ t('showcase.hero.open_source') }}
                </span>
              </div>
            </div>
            <div id="how-it-works-pos" class="hero-showcase">
              <div class="showcase-card">
                <div class="showcase-header">
                  <div
                    class="showcase-tabs"
                    role="tablist"
                    :aria-label="t('showcase.ui.preview')"
                    @keydown="onTabKeydown"
                  >
                    <button
                      id="tab-live-pos"
                      ref="demoTab"
                      type="button"
                      class="showcase-tab"
                      :class="{ active: preview === 'demo' }"
                      role="tab"
                      :aria-selected="preview === 'demo'"
                      :tabindex="preview === 'demo' ? 0 : -1"
                      aria-controls="view-live-pos"
                      @click="preview = 'demo'"
                    >
                      ⚡
                      {{ t('showcase.ui.live_pos') }}
                    </button>
                    <button
                      id="tab-overview"
                      ref="overviewTab"
                      type="button"
                      class="showcase-tab"
                      :class="{ active: preview === 'overview' }"
                      role="tab"
                      :aria-selected="preview === 'overview'"
                      :tabindex="preview === 'overview' ? 0 : -1"
                      aria-controls="view-overview"
                      @click="preview = 'overview'"
                    >
                      ▥
                      {{ t('showcase.ui.overview') }}
                    </button>
                  </div>
                  <span class="live-status-pill">
                    {{ t(preview === 'overview' ? 'showcase.ui.sample' : 'showcase.ui.demo') }}
                  </span>
                </div>
                <div
                  id="view-live-pos"
                  class="pos-terminal-stage"
                  :hidden="preview !== 'demo'"
                  role="tabpanel"
                  aria-labelledby="tab-live-pos"
                  tabindex="0"
                >
                  <div class="pos-device-shell">
                    <div class="pos-device-topbar">
                      <span>
                        SATFLUX PoS
                      </span>
                      <span class="font-mono">
                        Lightning
                      </span>
                    </div>
                    <div class="pos-iframe-wrap">
                      <iframe
                        v-if="demoLoaded && demoUrl"
                        class="pos-iframe"
                        :src="demoUrl"
                        :title="t('showcase.ui.live_pos')"
                        loading="lazy"
                        referrerpolicy="no-referrer"
                        allow="clipboard-write"
                      >
                      </iframe>
                      <div v-else class="demo-placeholder">
                        <span class="demo-symbol" aria-hidden="true">
                          ϟ
                        </span>
                        <p>
                          {{ t(demoUrl ? 'showcase.ui.demo_notice' : 'showcase.ui.demo_unavailable') }}
                        </p>
                        <button
                          v-if="demoUrl"
                          type="button"
                          class="btn btn-primary"
                          @click="demoLoaded = true"
                        >
                          {{ t('showcase.ui.load_demo') }}
                        </button>
                        <router-link v-else class="link-button" to="/documentation/point-of-sale">
                          {{ t('showcase.ui.documentation') }}
                          ↗
                        </router-link>
                      </div>
                    </div>
                  </div>
                  <div v-if="demoUrl" class="pos-stage-footer">
                    <span>
                      {{ t('showcase.ui.demo_hint') }}
                    </span>
                    <a :href="demoUrl" target="_blank" rel="noopener noreferrer">
                      {{ t('showcase.ui.open_demo') }}
                      ↗
                    </a>
                  </div>
                </div>
                <div
                  id="view-overview"
                  class="overview-screenshot-stage active"
                  :hidden="preview !== 'overview'"
                  role="tabpanel"
                  aria-labelledby="tab-overview"
                  tabindex="0"
                >
                  <button
                    class="capture-window-btn"
                    type="button"
                    aria-haspopup="dialog"
                    :aria-label="zoomLabel('overview')"
                    @click="openScreenshot('overview', $event)"
                  >
                    <img
                      :src="showcaseScreenshots.overview"
                      width="2160"
                      height="1500"
                      :alt="t('showcase.screenshots.overview')"
                      fetchpriority="high"
                    >
                    <span class="capture-zoom-hint" aria-hidden="true">
                      {{ t('showcase.ui.zoom') }}
                      ↗
                    </span>
                  </button>
                </div>
                <p class="showcase-caption">
                  {{ t('showcase.ui.sample_caption') }}
                </p>
              </div>
            </div>
          </div>
        </section>
        <div id="clarity" class="wrap trust-strip" :aria-label="t('showcase.trust.label')">
          <div v-for="(symbol, key) in trustSymbols" :key="key" class="trust-item">
            <span class="trust-symbol" aria-hidden="true">
              {{ symbol }}
            </span>
            <div>
              <strong>
                {{ t(`showcase.trust.${key}.title`) }}
              </strong>
              <br>
              {{ t(`showcase.trust.${key}.copy`) }}
            </div>
          </div>
        </div>
        <section class="wrap intro-heading" aria-labelledby="solutions-title">
          <p class="eyebrow">
            {{ t('showcase.solutions.eyebrow') }}
          </p>
          <h2 id="solutions-title">
            {{ t('showcase.solutions.title') }}
          </h2>
          <p>
            {{ t('showcase.solutions.copy') }}
          </p>
        </section>
        <section
          v-for="(story, index) in stories"
          :id="story.anchor"
          :key="story.key"
          class="story"
          :class="{ reverse: story.key === 'invoicing' }"
          :aria-labelledby="`${story.key}-title`"
        >
          <div class="wrap story-grid">
            <div class="story-copy">
              <div class="story-number">
                0
                {{ index + 1 }}
                /
                {{ t(`showcase.stories.${story.key}.name`) }}
              </div>
              <p class="eyebrow">
                {{ t(`showcase.stories.${story.key}.eyebrow`) }}
                <span v-if="story.key === 'invoicing'" class="pro-badge">
                  PRO
                </span>
              </p>
              <h2 :id="`${story.key}-title`">
                {{ t(`showcase.stories.${story.key}.title`) }}
              </h2>
              <p>
                {{ t(`showcase.stories.${story.key}.copy`) }}
              </p>
              <aside
                v-if="story.key === 'invoicing'"
                class="local-first-note"
                :aria-label="t('showcase.local_first.title')"
              >
                <strong>
                  {{ t('showcase.local_first.title') }}
                </strong>
                <p>
                  {{ t('showcase.local_first.copy') }}
                </p>
                <router-link to="/legal/privacy">
                  {{ t('showcase.local_first.privacy') }}
                  ↗
                </router-link>
              </aside>
              <ul class="benefits">
                <li
                  v-for="i in 3"
                  :id="story.key === 'events' && i === 3 ? 'how-it-works-raffle' : undefined"
                  :key="i"
                >
                  {{ t(`showcase.stories.${story.key}.benefit${i}`) }}
                </li>
              </ul>
              <button
                type="button"
                class="link-button"
                aria-haspopup="dialog"
                @click="openSolution(story.key, $event)"
              >
                {{ t(`showcase.stories.${story.key}.action`) }}
                <span aria-hidden="true">
                  ↗
                </span>
              </button>
            </div>
            <figure class="product-capture" :class="`capture-${story.key}`">
              <div class="capture-heading">
                <span>
                  {{ t(`showcase.screenshots.${story.key}`) }}
                </span>
                <span class="font-mono">
                  {{ t('showcase.ui.real_ui') }}
                </span>
              </div>
              <button
                type="button"
                class="capture-window"
                aria-haspopup="dialog"
                :aria-label="zoomLabel(story.key)"
                @click="openScreenshot(story.key, $event)"
              >
                <img
                  :src="showcaseScreenshots[story.key]"
                  width="2160"
                  height="1500"
                  :alt="t(`showcase.screenshots.${story.key}`)"
                  loading="lazy"
                >
                <span class="capture-zoom-hint" aria-hidden="true">
                  {{ t('showcase.ui.zoom') }}
                  ↗
                </span>
              </button>
              <figcaption>
                {{ t(`showcase.captions.${story.key}`) }}
              </figcaption>
            </figure>
          </div>
        </section>
        <section id="features" class="ecosystem" aria-labelledby="ecosystem-title">
          <div class="wrap">
            <div class="section-header">
              <div>
                <p class="eyebrow">
                  {{ t('showcase.tools.eyebrow') }}
                </p>
                <h2 id="ecosystem-title" ref="ecosystemTitle" tabindex="-1">
                  {{ t('showcase.tools.title') }}
                </h2>
              </div>
              <p class="tool-grid-hint">
                {{ t('showcase.tools.hint') }}
              </p>
            </div>
            <div class="ecosystem-grid">
              <button
                v-for="(tool, index) in showcaseTools"
                :id="tool.anchor"
                :key="tool.key"
                type="button"
                class="ecosystem-item"
                :data-tool="tool.key"
                :aria-expanded="activeTool === index && toolOpen"
                aria-haspopup="dialog"
                aria-controls="tool-dialog"
                @click="openTool(index, $event)"
              >
                <span class="eco-symbol" aria-hidden="true">
                  {{ tool.symbol }}
                </span>
                <strong>
                  {{ t(`showcase.tools.${tool.key}.title`) }}
                  <span v-if="tool.key === 'cashu'">
                    Beta
                  </span>
                </strong>
                <small>
                  {{ t(`showcase.tools.${tool.key}.summary`) }}
                </small>
              </button>
            </div>
          </div>
        </section>
        <section id="how-it-works" class="steps-section" aria-labelledby="steps-title">
          <div class="wrap steps-layout">
            <div class="steps-intro">
              <p class="eyebrow">
                {{ t('showcase.steps.eyebrow') }}
              </p>
              <h2 id="steps-title">
                {{ t('showcase.steps.title') }}
              </h2>
              <p>
                {{ t('showcase.steps.copy') }}
              </p>
            </div>
            <div class="steps">
              <article v-for="i in 3" :key="i">
                <div class="step-number">
                  0
                  {{ i }}
                </div>
                <h3>
                  {{ t(`showcase.steps.step${i}.title`) }}
                </h3>
                <p>
                  {{ t(`showcase.steps.step${i}.copy`) }}
                </p>
              </article>
            </div>
          </div>
        </section>
        <section id="pricing" class="wrap pricing-section" aria-labelledby="pricing-title">
          <FreeProNotice :active="promotionActive" :show-action="true" class="mb-6" />
          <div class="pricing-panel">
            <div>
              <p class="eyebrow">
                {{ t('showcase.pricing.eyebrow') }}
              </p>
              <h2 id="pricing-title">
                {{ t('showcase.pricing.title') }}
              </h2>
              <p>
                {{ promotionActive ? t('free_pro.description') : t('landing.pricing_hero_subheadline', { days: pricing.trial_days }) }}
              </p>
            </div>
            <router-link class="btn btn-secondary" to="/pricing">
              {{ t('showcase.pricing.compare') }}
              ↗
            </router-link>
          </div>
        </section>
        <section class="wrap faq-section" aria-labelledby="faq-title">
          <div class="faq-layout">
            <div class="faq-intro">
              <p class="eyebrow">
                {{ t('showcase.faq.eyebrow') }}
              </p>
              <h2 id="faq-title">
                {{ t('showcase.faq.title') }}
              </h2>
              <p>
                {{ t('showcase.faq.copy') }}
              </p>
              <router-link class="link-button" to="/faq">
                {{ t('showcase.faq.all') }}
                ↗
              </router-link>
            </div>
            <div class="faq-list">
              <details v-for="i in 3" :key="i" :open="i === 1">
                <summary>
                  {{ t(`showcase.faq.q${i}`) }}
                </summary>
                <p>
                  {{ t(`showcase.faq.a${i}`) }}
                </p>
              </details>
            </div>
          </div>
        </section>
        <section class="wrap final-cta" aria-labelledby="final-title">
          <h2 id="final-title">
            {{ t('showcase.cta.title') }}
          </h2>
          <p>
            {{ t('showcase.cta.copy') }}
          </p>
          <router-link class="btn btn-primary" :to="accountDestination">
            {{ t('showcase.hero.start') }}
            ↗
          </router-link>
        </section>
      </main>
      <ShowcaseDialog id="detail-dialog" ref="solutionDialog" v-slot="{ close }" labelledby="dialog-title">
        <div class="dialog-head">
          <p class="eyebrow">
            SATFLUX /
            {{ t('showcase.ui.solutions') }}
          </p>
          <button type="button" class="close-dialog" :aria-label="t('showcase.ui.close')" @click="close">
            ×
          </button>
        </div>
        <div class="dialog-body">
          <h2 id="dialog-title">
            {{ t(`showcase.stories.${activeSolution}.detail_title`) }}
          </h2>
          <p class="dialog-intro">
            {{ t(`showcase.stories.${activeSolution}.detail_intro`) }}
          </p>
          <div class="detail-grid">
            <article v-for="item in solutionItems[activeSolution]" :key="item" class="detail-item">
              <h3>
                {{ t(`showcase.details.${item}.title`) }}
              </h3>
              <p>
                {{ t(`showcase.details.${item}.copy`) }}
              </p>
              <p v-if="item === 'local_first'" class="mt-3">
                {{ t('showcase.details.local_first.legacy') }}
              </p>
            </article>
          </div>
          <div class="dialog-actions">
            <router-link class="btn btn-primary" :to="accountDestination" @click="close">
              {{ t('showcase.hero.start') }}
            </router-link>
            <button type="button" class="link-button" @click="showToolOverview">
              {{ t('showcase.ui.all_tools') }}
              ↗
            </button>
          </div>
        </div>
      </ShowcaseDialog>
      <ShowcaseDialog
        id="tool-dialog"
        ref="toolDialog"
        v-slot="{ close }"
        labelledby="tool-detail-title"
        describedby="tool-position"
        @closed="toolOpen = false"
        @keydown="onToolKeydown"
      >
        <div class="tool-detail-header">
          <div class="tool-heading-group">
            <p class="tool-detail-kicker">
              {{ t('showcase.tools.context') }}
            </p>
            <h3 id="tool-detail-title" ref="toolTitle" tabindex="-1" aria-live="polite" aria-atomic="true">
              {{ t(`showcase.tools.${currentTool.key}.title`) }}
            </h3>
          </div>
          <button type="button" class="tool-detail-back" @click="close">
            {{ t('showcase.ui.close') }}
            <span aria-hidden="true">
              ×
            </span>
          </button>
        </div>
        <div ref="toolBody" class="tool-dialog-body">
          <div class="tool-detail-layout">
            <div>
              <p class="tool-detail-copy">
                {{ t(`showcase.tools.${currentTool.key}.copy`) }}
              </p>
              <p class="tool-detail-note">
                {{ t(`showcase.tools.${currentTool.key}.note`) }}
              </p>
            </div>
            <div class="tool-example">
              <p class="tool-example-label">
                {{ t('showcase.tools.example') }}
              </p>
              <code v-if="currentTool.key === 'lightning'">
                MENO@satflux.org
                <br>
                OBCHOD@satflux.org
              </code>
              <p class="tool-example-text">
                {{ t(`showcase.tools.${currentTool.key}.example`) }}
              </p>
            </div>
          </div>
          <div class="tool-detail-links">
            <router-link class="link-button" :to="`/documentation/${currentTool.docs}`" @click="close">
              {{ t('showcase.ui.documentation') }}
              ↗
            </router-link>
            <a
              v-if="'secondary' in currentTool"
              class="link-button"
              :href="currentTool.secondary"
              target="_blank"
              rel="noopener noreferrer"
            >
              {{ t('showcase.tools.plugin') }}
              ↗
            </a>
          </div>
        </div>
        <nav class="tool-browser-nav" :aria-label="t('showcase.tools.navigation')">
          <button type="button" class="tool-nav-button" :disabled="activeTool === 0" @click="changeTool(-1)">
            <span class="tool-nav-direction">
              ←
              {{ t('showcase.tools.previous') }}
            </span>
            <span>
              {{ activeTool > 0 ? t(`showcase.tools.${showcaseTools[activeTool - 1]!.key}.title`) : t('showcase.tools.first') }}
            </span>
          </button>
          <p id="tool-position" role="status" aria-live="polite" aria-atomic="true">
            {{ t('showcase.tools.position', { current: activeTool + 1, total: showcaseTools.length }) }}
          </p>
          <button
            type="button"
            class="tool-nav-button tool-nav-next"
            :disabled="activeTool === showcaseTools.length - 1"
            @click="changeTool(1)"
          >
            <span class="tool-nav-direction">
              {{ t('showcase.tools.next') }}
              →
            </span>
            <span>
              {{ activeTool < showcaseTools.length - 1 ? t(`showcase.tools.${showcaseTools[activeTool + 1]!.key}.title`) : t('showcase.tools.last') }}
            </span>
          </button>
        </nav>
      </ShowcaseDialog>
      <ShowcaseDialog
        id="screenshot-dialog"
        ref="screenshotDialog"
        v-slot="{ close }"
        labelledby="screenshot-title"
        describedby="zoom-instruction"
      >
        <div class="zoom-header">
          <div class="zoom-title-group">
            <h2 id="screenshot-title">
              {{ t(`showcase.screenshots.${activeScreenshot}`) }}
            </h2>
            <p>
              {{ t('showcase.ui.sample_caption') }}
            </p>
          </div>
          <div class="zoom-actions">
            <button
              id="zoom-size-toggle"
              type="button"
              :aria-pressed="nativeZoom"
              aria-controls="zoom-image"
              @click="nativeZoom = !nativeZoom"
            >
              {{ t(nativeZoom ? 'showcase.ui.fit' : 'showcase.ui.native') }}
            </button>
            <button id="close-screenshot" type="button" :aria-label="t('showcase.ui.close')" @click="close">
              ×
            </button>
          </div>
        </div>
        <div
          class="zoom-viewport"
          :class="{ 'is-native': nativeZoom }"
          tabindex="0"
          role="region"
          :aria-label="t('showcase.ui.zoom_region')"
        >
          <img
            id="zoom-image"
            :src="showcaseScreenshots[activeScreenshot]"
            :alt="t(`showcase.screenshots.${activeScreenshot}`)"
          >
        </div>
        <p id="zoom-instruction" class="zoom-instruction">
          {{ t('showcase.ui.zoom_instruction') }}
        </p>
      </ShowcaseDialog>
    </div>
    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicHeader from '../components/layout/PublicHeader.vue';
import AppFooter from '../components/layout/AppFooter.vue';
import FreeProNotice from '../components/account/FreeProNotice.vue';
import ShowcaseDialog from '../components/landing/showcase/ShowcaseDialog.vue';
import { showcaseTools, showcaseScreenshots, readShowcaseDemoUrl, type ShowcaseStoryKey, type ShowcaseScreenshotKey } from '../content/landingShowcase/catalog';
import { useAuthStore } from '../store/auth';
import { usePricing } from '../composables/usePricing';
import { usePlanFeatures } from '../composables/usePlanFeatures';
import { useBtcPayUrl } from '../composables/useBtcPayUrl';
import '../styles/landing-showcase.css';
const { t } = useI18n();
const authStore = useAuthStore();
const { pricing, promotionActive, load: loadPricing } = usePricing();
const { load: loadFeatures } = usePlanFeatures();
const { load: loadBtcpay } = useBtcPayUrl();
const accountDestination = computed(() => authStore.isAuthenticated ? '/dashboard' : '/register');
const preview = ref<'overview' | 'demo'>('overview');
const demoLoaded = ref(false);
const demoUrl = readShowcaseDemoUrl();
const demoTab = ref<HTMLButtonElement>();
const overviewTab = ref<HTMLButtonElement>();
const solutionDialog = ref<InstanceType<typeof ShowcaseDialog>>();
const toolDialog = ref<InstanceType<typeof ShowcaseDialog>>();
const screenshotDialog = ref<InstanceType<typeof ShowcaseDialog>>();
const ecosystemTitle = ref<HTMLElement>();
const toolTitle = ref<HTMLElement>();
const toolBody = ref<HTMLElement>();
const activeSolution = ref<ShowcaseStoryKey>('payments');
const activeTool = ref(0);
const toolOpen = ref(false);
const activeScreenshot = ref<ShowcaseScreenshotKey>('overview');
const nativeZoom = ref(false);
const currentTool = computed(() => showcaseTools[activeTool.value]!);
const trustSymbols = { wallet: '◇', workspace: '⊞', infrastructure: 'ϟ' };
const stories: {
  key: ShowcaseStoryKey;
  anchor: string;
}[] = [{ key: 'payments', anchor: 'payments' }, { key: 'invoicing', anchor: 'invoicing' }, { key: 'events', anchor: 'how-it-works-tickets' }];
const solutionItems: Record<ShowcaseStoryKey, string[]> = {
  payments: ['pos', 'woocommerce', 'lightning', 'paybutton', 'bankqr', 'exports'],
  invoicing: ['local_first', 'companies', 'matching', 'expenses', 'recurring', 'invoice_payment', 'accounting'],
  events: ['tickets', 'checkin', 'raffle', 'crowdfund'],
};
function eventOpener(event: Event): HTMLElement | null { return event.currentTarget instanceof HTMLElement ? event.currentTarget : null; }
function zoomLabel(key: ShowcaseScreenshotKey): string { return `${t('showcase.ui.zoom')}: ${t(`showcase.screenshots.${key}`)}`; }
async function openSolution(key: ShowcaseStoryKey, event: Event): Promise<void> { const origin = eventOpener(event); activeSolution.value = key; await nextTick(); solutionDialog.value?.open(origin); }
async function openTool(index: number, event: Event): Promise<void> { const origin = eventOpener(event); activeTool.value = index; toolOpen.value = true; await nextTick(); toolDialog.value?.open(origin); toolTitle.value?.focus(); }
async function openScreenshot(key: ShowcaseScreenshotKey, event: Event): Promise<void> { const origin = eventOpener(event); activeScreenshot.value = key; nativeZoom.value = false; await nextTick(); screenshotDialog.value?.open(origin); }
async function changeTool(delta: number): Promise<void> {
  const next = activeTool.value + delta;
  if (next < 0 || next >= showcaseTools.length) return;
  activeTool.value = next;
  await nextTick();
  if (toolBody.value) toolBody.value.scrollTop = 0;
  toolTitle.value?.focus();
}

function onToolKeydown(event: KeyboardEvent): void {
  const dialog = event.currentTarget;
  if (!(dialog instanceof HTMLDialogElement) || !dialog.open || !toolOpen.value) return;
  if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
  const delta = event.key === 'ArrowLeft' ? -1 : event.key === 'ArrowRight' ? 1 : 0;
  const next = activeTool.value + delta;
  if (delta === 0 || next < 0 || next >= showcaseTools.length) return;
  event.preventDefault();
  void changeTool(delta);
}

async function showToolOverview(): Promise<void> { solutionDialog.value?.close(false); await nextTick(); ecosystemTitle.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }); ecosystemTitle.value?.focus({ preventScroll: true }); }
async function showDemo(): Promise<void> { preview.value = 'demo'; await nextTick(); demoTab.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }); demoTab.value?.focus({ preventScroll: true }); }
function onTabKeydown(event: KeyboardEvent): void {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key))
    return;
  event.preventDefault();
  preview.value = event.key === 'Home' ? 'demo' : event.key === 'End' ? 'overview' : preview.value === 'overview' ? 'demo' : 'overview';
  void nextTick().then(() => (preview.value === 'demo' ? demoTab.value : overviewTab.value)?.focus());
}
let idleHandle: number | undefined;
let deferredTimer: ReturnType<typeof setTimeout> | undefined;
let hashTimer: ReturnType<typeof setTimeout> | undefined;
let frameHandle: number | undefined;
function scrollToHash(): void {
  const id = window.location.hash.slice(1);
  if (!id)
    return;
  try {
    document.getElementById(decodeURIComponent(id))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  catch { /* Malformed fragments are not selectors. */ }
}
onMounted(() => {
  void nextTick().then(() => { frameHandle = requestAnimationFrame(() => { document.getElementById('landing-shell')?.remove(); document.getElementById('app')?.classList.remove('sf-app-pending'); }); });
  const loadDeferred = () => { void Promise.all([loadPricing(), loadFeatures(), loadBtcpay(), authStore.fetchUser()]); };
  if ('requestIdleCallback' in window)
    idleHandle = requestIdleCallback(loadDeferred);
  else
    deferredTimer = setTimeout(loadDeferred, 0);
  hashTimer = setTimeout(scrollToHash, 100);
  window.addEventListener('hashchange', scrollToHash);
});
onBeforeUnmount(() => {
  if (idleHandle !== undefined)
    cancelIdleCallback(idleHandle);
  if (deferredTimer !== undefined)
    clearTimeout(deferredTimer);
  if (hashTimer !== undefined)
    clearTimeout(hashTimer);
  if (frameHandle !== undefined)
    cancelAnimationFrame(frameHandle);
  window.removeEventListener('hashchange', scrollToHash);
});
</script>
