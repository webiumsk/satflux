<template>
  <footer class="shrink-0 border-t border-gray-800 bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-4 lg:px-4 py-4">
      <div class="text-center">
        <div class="flex justify-center gap-4 mb-4">
          <p class="text-sm text-gray-400">
          SATFLUX
          <span v-if="version" class="text-gray-600">v{{ version }}</span>
        </p>
          <a
            href="https://github.com/webiumsk/Satflux"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="t('common.footer_github_aria')"
            class="text-gray-500 hover:text-gray-300 transition-colors"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"
              />
            </svg>
          </a>
          <a
            href="https://x.com/satflux"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Satflux on X (Twitter)"
            class="text-gray-500 hover:text-gray-300 transition-colors"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
            </svg>
          </a>
          <a
            href="https://www.youtube.com/@Satflux"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Satflux on YouTube"
            class="text-gray-500 hover:text-gray-300 transition-colors"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
            </svg>
          </a>
          <a
            href="https://njump.me/npub1suj9trhvxcc5c77d5k45xyjl8xqa5w9nu94dmth096w7dxcpp65szde8uw"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Satflux on Nostr"
            class="text-gray-500 hover:text-gray-300 transition-colors"
          >
            <svg class="w-5 h-5" viewBox="0 0 750 750" fill="currentColor" aria-hidden="true">
              <g transform="matrix(0.1, 0, 0, -0.1, 0, 0)">
                <path d="m5404,-1199c-53,-24 -107,-88 -129,-154c-10,-30 -20,-94 -23,-154c-3,-85 -1,-114 17,-172c40,-132 139,-253 321,-391c176,-134 230,-214 238,-352c11,-187 -57,-312 -211,-388c-82,-40 -140,-47 -266,-30c-85,11 -104,11 -153,-4c-31,-9 -67,-16 -80,-16c-14,0 -95,43 -182,95c-274,164 -364,189 -681,190c-253,0 -318,-10 -526,-81c-135,-46 -177,-64 -499,-222c-113,-55 -237,-110 -275,-121c-110,-35 -246,-51 -422,-51c-182,0 -202,-6 -182,-58c6,-15 16,-34 23,-42c29,-34 28,-39 -11,-28c-21,5 -45,16 -53,23c-43,38 -206,45 -326,15c-204,-53 -392,-228 -465,-436c-24,-67 -25,-122 -3,-131c10,-3 47,9 88,29c39,19 86,39 103,42c41,9 42,3 8,-100c-31,-96 -33,-162 -4,-198c11,-14 27,-26 35,-26c8,0 58,41 111,90c66,62 104,90 120,90c16,0 23,-4 19,-12c-39,-94 -47,-231 -15,-251c18,-11 26,-8 154,67c107,63 263,108 423,123c50,5 57,2 115,-37c34,-23 98,-59 143,-81c98,-47 342,-129 479,-159c55,-13 104,-26 108,-30c12,-11 -72,-117 -164,-207c-125,-122 -270,-235 -338,-261c-106,-41 -159,-103 -186,-217c-9,-38 -39,-90 -120,-206c-60,-85 -156,-221 -213,-304c-262,-374 -251,-362 -355,-385c-68,-15 -138,-53 -175,-94c-37,-42 -56,-100 -41,-127c11,-22 26,-23 65,-9c36,14 40,7 15,-28c-35,-50 -90,-172 -101,-228c-8,-40 -8,-68 0,-108c11,-54 40,-94 59,-81c6,3 29,43 52,88c46,93 78,135 200,262c47,50 110,128 139,175c90,145 218,323 429,595c196,253 230,293 274,310c85,34 152,104 171,179c11,41 23,52 239,215l229,172l125,45c69,24 129,44 134,44c11,0 -20,-72 -36,-85c-8,-5 -36,-70 -64,-143c-56,-146 -64,-207 -35,-268c9,-18 31,-43 49,-54c51,-32 101,-26 177,20c50,30 144,64 399,144c458,143 474,148 522,144l42,-3l11,-62c13,-72 35,-110 71,-124c39,-15 55,4 48,60c-10,80 4,80 86,-2c77,-77 140,-117 184,-117c34,0 42,24 21,67c-38,81 -231,366 -264,391c-32,25 -43,27 -126,27c-99,0 -55,12 -706,-194c-124,-40 -234,-74 -244,-77c-25,-8 -19,7 38,94c34,51 58,75 93,95c72,39 133,85 133,101c0,8 11,28 25,44l24,29l168,6c332,13 537,72 705,205c146,114 207,264 208,508c0,81 4,125 13,136c6,8 59,44 117,79c260,158 390,290 498,504c74,148 100,334 68,484c-37,175 -124,292 -342,460c-136,105 -165,138 -171,195c-6,53 7,64 112,94c55,15 107,20 235,21c206,3 233,18 102,56c-34,10 -59,22 -55,26c4,4 35,9 68,11c45,2 60,7 60,18c0,11 -46,30 -155,66c-126,41 -171,61 -235,104c-128,87 -204,106 -281,71z" />
              </g>
            </svg>
          </a>
        </div>
        
        <p
          class="text-xs text-gray-600 mt-2 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 leading-none"
        >
          <span class="inline-flex items-center gap-1 text-gray-600 leading-none">
            <span class="leading-none">{{ t('common.footer_created_with') }}</span>
            <svg
              class="h-[1.08em] w-[1.08em] text-rose-500 shrink-0 block translate-y-[0.03em]"
              viewBox="0 0 24 24"
              fill="currentColor"
              role="img"
              :aria-label="t('common.footer_love_aria')"
            >
              <path
                d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
              />
            </svg>
          </span>
          <span class="text-gray-600 leading-none" aria-hidden="true">|</span>
          <span class="text-gray-600 leading-none inline-flex items-center">
            {{ t('common.footer_open_source') }}&nbsp;
            <a
              href="https://github.com/webiumsk/Satflux/blob/master/LICENSE"
              target="_blank"
              rel="noopener noreferrer"
              class="underline hover:text-gray-400 focus:outline-none focus-visible:ring-1 focus-visible:ring-gray-500 rounded leading-none"
            >{{ t('common.footer_license_link') }}</a>
          </span>
        <span class="text-gray-600 leading-none" aria-hidden="true">|</span>
            <span
              class="inline-flex h-5 items-center leading-none text-gray-500"
            >{{ t('common.footer_support_me') }}</span>
            <span>
              <a
                :href="`lightning:${lnAddress}`"
                class="inline-flex items-center font-mono text-indigo-200/95 leading-none hover:text-indigo-100 focus:outline-none focus-visible:ring-1 focus-visible:ring-indigo-400 rounded"
              >{{ lnAddress }}</a>
            </span>
            <button
              type="button"
              class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-gray-500 hover:text-gray-300 focus:outline-none focus-visible:ring-1 focus-visible:ring-gray-500"
              :aria-label="t('common.footer_ln_copy_aria')"
              :title="t('common.footer_ln_copy_aria')"
              @click="copyLnAddress"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                />
              </svg>
            </button>
            <span
              v-if="lnCopied"
              class="inline-flex h-5 items-center text-emerald-500/90 leading-none"
              role="status"
            >{{ t('common.footer_ln_copied') }}</span>
          </p>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import api from "../../services/api";

const { t } = useI18n();
const version = ref<string | null>(null);

/** Public Lightning Address for tips (shown in footer; default donate link uses lightning: URI). */
const lnAddress = "hello@satflux.org";

const lnCopied = ref(false);
let lnCopyResetTimer: ReturnType<typeof setTimeout> | null = null;

async function copyLnAddress() {
  try {
    await navigator.clipboard.writeText(lnAddress);
    lnCopied.value = true;
    if (lnCopyResetTimer) clearTimeout(lnCopyResetTimer);
    lnCopyResetTimer = setTimeout(() => {
      lnCopied.value = false;
      lnCopyResetTimer = null;
    }, 2000);
  } catch {
    // Clipboard may be denied; ignore
  }
}

onMounted(async () => {
  try {
    const response = await api.get("/version");
    version.value = response.data.version || null;
  } catch (error) {
    console.error("Failed to load app version:", error);
    // Silently fail - version is optional
  }
});
</script>
