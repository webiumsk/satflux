<template>
  <div
    ref="rootRef"
    class="landing-raffle-presenter relative isolate w-full max-w-md overflow-hidden rounded-xl border border-gray-600/80 bg-[#1a1d21] p-5 shadow-xl transform transition-transform duration-500"
  >
    <div
      v-if="showConfetti"
      class="raffle-confetti-layer pointer-events-none absolute inset-0 z-30"
      aria-hidden="true"
    >
      <div class="raffle-confetti-veil absolute inset-0" />
      <div class="raffle-confetti-flash absolute inset-0" />
      <span
        v-for="(particle, i) in confettiParticles"
        :key="i"
        class="raffle-confetti-piece absolute rounded-[2px]"
        :class="particle.sizeClass"
        :style="{
          left: `${particle.left}%`,
          top: `${particle.top}%`,
          '--tx': particle.tx,
          '--ty': particle.ty,
          '--rot': particle.rot,
          '--delay': `${particle.delay}ms`,
          '--piece-color': particle.color,
          '--piece-glow': particle.glow,
        }"
      />
    </div>

    <div class="relative z-20">
      <div
        class="flex min-h-[3.25rem] items-center justify-center rounded-lg border px-4 py-2.5 text-center text-sm font-semibold leading-snug transition-colors duration-500"
        :class="statusBannerClass"
      >
        {{ statusBannerText }}
      </div>

      <div
        class="relative mt-4 flex min-h-[9.5rem] flex-col items-center justify-center rounded-lg border px-6 py-8 text-center transition-colors duration-500"
        :class="displayBoxClass"
      >
        <p
          class="text-[11px] font-medium uppercase tracking-[0.2em] text-gray-400"
        >
          {{ t("landing.step8_presenter_last_drawn") }}
        </p>
        <p
          class="mt-4 font-mono text-6xl font-bold leading-none text-[#ffcc00] transition-all duration-200 sm:text-7xl"
          :class="numberClass"
          aria-hidden="true"
        >
          #{{ displayNumber }}
        </p>
      </div>

      <div class="mt-5">
        <p
          class="mb-2 text-center text-[11px] font-medium uppercase tracking-[0.14em] text-gray-400"
        >
          {{ t("landing.step8_presenter_my_tickets") }}
        </p>
        <div
          class="flex min-h-[4.25rem] flex-wrap items-center justify-center gap-2"
        >
          <div
            v-for="ticket in userTickets"
            :key="ticket"
            class="min-w-[3.25rem] rounded-lg border px-3 py-2.5 text-center font-mono text-lg font-semibold transition-colors duration-500"
            :class="ticketClass(ticket)"
          >
            #{{ ticket }}
          </div>
        </div>
      </div>

      <div class="mt-4">
        <p
          class="mb-2 text-center text-[11px] font-medium uppercase tracking-[0.14em] text-gray-400"
        >
          {{ t("landing.step8_presenter_drawn_history") }}
        </p>
        <div
          class="flex min-h-[3.25rem] flex-wrap items-center justify-center gap-2"
        >
          <div
            v-for="(ticket, index) in drawnHistory"
            :key="`drawn-${index}-${ticket}`"
            class="min-w-[3.25rem] rounded-lg border px-3 py-2.5 text-center font-mono text-lg font-semibold transition-colors duration-500"
            :class="historyTicketClass(ticket)"
          >
            #{{ ticket }}
          </div>
        </div>
      </div>

      <p class="mt-4 text-center text-[11px] text-gray-500">
        {{ t("landing.step8_presenter_hint") }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";

type DrawPhase = "spinning" | "revealed" | "celebrating" | "done";

const { t } = useI18n();

const userTickets = [12, 24, 37, 60, 88];
const winningTickets = [60, 12];
/** Four draws: non-winner, winner + confetti, non-winner, second winner. */
const drawSequence = [16, 60, 59, 12];

const rootRef = ref<HTMLElement | null>(null);
const phase = ref<DrawPhase>("spinning");
const displayNumber = ref(drawSequence[0]);
const drawnHistory = ref<number[]>([]);
const revealedWinningTickets = ref<number[]>([]);
const showConfetti = ref(false);
const prefersReducedMotion = ref(false);

let spinTimer: ReturnType<typeof setTimeout> | null = null;
let phaseTimer: ReturnType<typeof setTimeout> | null = null;
let loopTimer: ReturnType<typeof setTimeout> | null = null;
let observer: IntersectionObserver | null = null;
let isVisible = false;
let cycleRunning = false;
let drawStepIndex = 0;
let postCelebrationDrawIndex = 2;
const currentDrawOrder = ref(1);

const winningTicketsLabel = computed(() =>
  winningTickets.map((n) => `#${n}`).join(", "),
);

const confettiPalette = [
  { color: "#ffd700", glow: "rgba(255, 215, 0, 0.95)" },
  { color: "#ffe566", glow: "rgba(255, 229, 102, 0.9)" },
  { color: "#ff9f1c", glow: "rgba(255, 159, 28, 0.9)" },
  { color: "#ff5c8a", glow: "rgba(255, 92, 138, 0.85)" },
  { color: "#3dffb5", glow: "rgba(61, 255, 181, 0.8)" },
  { color: "#5eb0ff", glow: "rgba(94, 176, 255, 0.85)" },
  { color: "#ffffff", glow: "rgba(255, 255, 255, 0.95)" },
  { color: "#ffc107", glow: "rgba(255, 193, 7, 0.9)" },
];

const confettiSizeClasses = ["h-3 w-8", "h-4 w-3.5", "h-3.5 w-3.5", "h-2.5 w-6"];

const burstParticles = Array.from({ length: 32 }, (_, i) => {
  const angle = (i / 32) * Math.PI * 2 + 0.35;
  const dist = 130 + (i % 8) * 24;
  return {
    color: confettiPalette[i % confettiPalette.length].color,
    glow: confettiPalette[i % confettiPalette.length].glow,
    sizeClass: confettiSizeClasses[i % confettiSizeClasses.length],
    left: 50 + Math.cos(angle) * 4,
    top: 46 + Math.sin(angle) * 6,
    tx: `${Math.round(Math.cos(angle) * dist)}px`,
    ty: `${Math.round(Math.sin(angle) * dist * 0.95)}px`,
    rot: `${(i * 41 + 120) % 360}deg`,
    delay: (i % 8) * 30,
  };
});

const rainParticles = Array.from({ length: 14 }, (_, i) => ({
  color: confettiPalette[(i + 3) % confettiPalette.length].color,
  glow: confettiPalette[(i + 3) % confettiPalette.length].glow,
  sizeClass: confettiSizeClasses[(i + 1) % confettiSizeClasses.length],
  left: 6 + ((i * 13.7) % 88),
  top: 4 + (i % 4) * 3,
  tx: `${Math.round(((i % 5) - 2) * 38)}px`,
  ty: `${110 + (i % 5) * 32}px`,
  rot: `${(i * 53) % 360}deg`,
  delay: 60 + (i % 6) * 45,
}));

const confettiParticles = [...burstParticles, ...rainParticles];

const showCongratsBanner = computed(() => phase.value === "done");

const statusBannerText = computed(() => {
  if (showCongratsBanner.value) {
    return t("landing.step8_presenter_congrats", {
      tickets: winningTicketsLabel.value,
    });
  }
  return t("landing.step8_presenter_in_progress", {
    order: currentDrawOrder.value,
  });
});

const statusBannerClass = computed(() => {
  if (showCongratsBanner.value) {
    return "border-emerald-500/50 bg-[#1e5d2f] text-white";
  }
  return "border-amber-500/45 bg-[#252a30] text-amber-100";
});

const showWinnerGlow = computed(() => revealedWinningTickets.value.length > 0);

const displayBoxClass = computed(() => {
  if (showWinnerGlow.value) {
    return "border-amber-400/80 bg-[#2a2418] shadow-[0_0_48px_rgba(255,204,0,0.35)] raffle-winner-pulse";
  }
  if (phase.value === "spinning" || phase.value === "revealed") {
    return "border-amber-500/40 bg-[#252a30] shadow-[inset_0_0_40px_rgba(251,191,36,0.08)]";
  }
  return "border-gray-600 bg-[#252a30]";
});

const numberClass = computed(() =>
  phase.value === "spinning" ? "raffle-number-spin" : "",
);

const winningTicketHighlightClass =
  "border-amber-200/80 bg-[#f5e6b8] text-gray-900 shadow-[0_0_20px_rgba(255,204,0,0.45)]";

function isWinningTicket(ticket: number) {
  return revealedWinningTickets.value.includes(ticket);
}

function ticketClass(ticket: number) {
  if (isWinningTicket(ticket)) {
    return winningTicketHighlightClass;
  }
  return "border-gray-600 bg-[#252a30] text-white";
}

function historyTicketClass(ticket: number) {
  if (isWinningTicket(ticket)) {
    return winningTicketHighlightClass;
  }
  return "border-gray-600 bg-[#252a30] text-white";
}

function clearTimers() {
  if (spinTimer) {
    clearTimeout(spinTimer);
    spinTimer = null;
  }
  if (phaseTimer) {
    clearTimeout(phaseTimer);
    phaseTimer = null;
  }
  if (loopTimer) {
    clearTimeout(loopTimer);
    loopTimer = null;
  }
}

function schedulePhase(delay: number, next: () => void) {
  phaseTimer = setTimeout(next, delay);
}

function spinToTarget(target: number, duration: number, onComplete: () => void) {
  const start = performance.now();

  const tick = () => {
    const elapsed = performance.now() - start;
    const progress = Math.min(1, elapsed / duration);

    if (progress < 1) {
      const max = 99;
      const min = Math.max(1, Math.floor(progress * 35));
      const nearTarget = progress > 0.82;
      displayNumber.value = nearTarget
        ? target
        : Math.floor(Math.random() * (max - min + 1)) + min;
      const delay = 35 + progress * progress * 130;
      spinTimer = setTimeout(tick, delay);
      return;
    }

    displayNumber.value = target;
    onComplete();
  };

  tick();
}

function finishCycle() {
  cycleRunning = false;
  if (isVisible) {
    runDrawCycle();
  }
}

function playConfetti(then: () => void) {
  phase.value = "celebrating";
  showConfetti.value = !prefersReducedMotion.value;
  schedulePhase(prefersReducedMotion.value ? 400 : 2000, () => {
    showConfetti.value = false;
    schedulePhase(prefersReducedMotion.value ? 500 : 900, then);
  });
}

function runPostCelebrationDraws() {
  if (postCelebrationDrawIndex >= drawSequence.length) {
    return;
  }

  const target = drawSequence[postCelebrationDrawIndex];
  currentDrawOrder.value = postCelebrationDrawIndex + 1;
  phase.value = "spinning";

  spinToTarget(target, 2200, () => {
    drawnHistory.value = [...drawnHistory.value, target];
    phase.value = "revealed";

    if (target === 12) {
      revealedWinningTickets.value = [...winningTickets];
      schedulePhase(1100, () => {
        playConfetti(() => {
          phase.value = "done";
          schedulePhase(prefersReducedMotion.value ? 2800 : 4200, finishCycle);
        });
      });
      return;
    }

    schedulePhase(1100, () => {
      postCelebrationDrawIndex += 1;
      runPostCelebrationDraws();
    });
  });
}

/** First two draws (#16, #60); celebration; then #59 and winning #12. */
const animatedDrawCount = 2;

function runDrawStep() {
  if (drawStepIndex >= animatedDrawCount) {
    return;
  }

  const target = drawSequence[drawStepIndex];
  currentDrawOrder.value = drawStepIndex + 1;
  phase.value = "spinning";

  spinToTarget(target, 2200, () => {
    drawnHistory.value = [...drawnHistory.value, target];
    phase.value = "revealed";

    if (drawStepIndex === 1) {
      revealedWinningTickets.value = [60];
      schedulePhase(1100, () => {
        playConfetti(() => runPostCelebrationDraws());
      });
      return;
    }

    schedulePhase(1100, () => {
      drawStepIndex += 1;
      runDrawStep();
    });
  });
}

function runDrawCycle() {
  if (!isVisible || cycleRunning) {
    return;
  }
  cycleRunning = true;
  clearTimers();
  showConfetti.value = false;
  revealedWinningTickets.value = [];
  drawnHistory.value = [];
  drawStepIndex = 0;
  postCelebrationDrawIndex = 2;
  currentDrawOrder.value = 1;
  phase.value = "spinning";
  displayNumber.value = userTickets[0];

  if (prefersReducedMotion.value) {
    drawnHistory.value = [...drawSequence];
    revealedWinningTickets.value = [...winningTickets];
    displayNumber.value = 12;
    phase.value = "done";
    cycleRunning = false;
    loopTimer = setTimeout(() => {
      if (isVisible) {
        runDrawCycle();
      }
    }, 9000);
    return;
  }

  runDrawStep();
}

onMounted(() => {
  prefersReducedMotion.value = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;

  if (prefersReducedMotion.value) {
    drawnHistory.value = [...drawSequence];
    revealedWinningTickets.value = [...winningTickets];
    displayNumber.value = 12;
    phase.value = "done";
    return;
  }

  observer = new IntersectionObserver(
    (entries) => {
      const entry = entries[0];
      isVisible = entry?.isIntersecting ?? false;
      if (isVisible) {
        if (!cycleRunning) {
          runDrawCycle();
        }
      } else {
        clearTimers();
        cycleRunning = false;
      }
    },
    { threshold: 0.35 },
  );

  if (rootRef.value) {
    observer.observe(rootRef.value);
  }
});

onBeforeUnmount(() => {
  clearTimers();
  observer?.disconnect();
});
</script>

<style scoped>
.raffle-number-spin {
  filter: blur(0.3px);
}

.raffle-winner-pulse {
  animation: raffle-winner-glow 1.2s ease-in-out infinite;
}

@keyframes raffle-winner-glow {
  0%,
  100% {
    box-shadow: 0 0 32px rgba(255, 204, 0, 0.25);
  }
  50% {
    box-shadow: 0 0 56px rgba(255, 204, 0, 0.5);
  }
}

.raffle-confetti-layer {
  mix-blend-mode: screen;
}

@supports (mix-blend-mode: plus-lighter) {
  .raffle-confetti-layer {
    mix-blend-mode: plus-lighter;
  }
}

.raffle-confetti-veil {
  background: radial-gradient(
    ellipse 100% 95% at 50% 45%,
    rgba(255, 210, 80, 0.22) 0%,
    rgba(255, 180, 50, 0.08) 45%,
    transparent 72%
  );
  animation: raffle-confetti-veil 1.8s ease-out forwards;
}

@keyframes raffle-confetti-veil {
  0% {
    opacity: 1;
  }
  100% {
    opacity: 0;
  }
}

.raffle-confetti-flash {
  background: radial-gradient(
    ellipse 100% 95% at 50% 46%,
    rgba(255, 230, 120, 0.55) 0%,
    rgba(255, 200, 60, 0.2) 40%,
    transparent 70%
  );
  animation: raffle-confetti-flash 1.6s ease-out forwards;
}

@keyframes raffle-confetti-flash {
  0% {
    opacity: 0.95;
    transform: scale(0.85);
  }
  100% {
    opacity: 0;
    transform: scale(1.15);
  }
}

.raffle-confetti-piece {
  opacity: 0;
  background-color: var(--piece-color);
  border: 1px solid rgba(255, 255, 255, 0.45);
  box-shadow:
    0 0 10px var(--piece-glow),
    0 1px 2px rgba(0, 0, 0, 0.35);
  animation: raffle-confetti-fly 1.85s ease-out forwards;
  animation-delay: var(--delay, 0ms);
}

@keyframes raffle-confetti-fly {
  0% {
    opacity: 1;
    transform: translate(-50%, -50%) rotate(0deg) scale(1);
  }
  100% {
    opacity: 0;
    transform: translate(calc(-50% + var(--tx)), calc(-50% + var(--ty)))
      rotate(var(--rot)) scale(0.55);
  }
}
</style>
