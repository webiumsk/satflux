{{-- Static LCP shell: removed by Vue after mount. Copy from locale JSON via LandingCopy. --}}
<style>
  #landing-shell {
    font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  #landing-shell .sf-hero-gradient {
    background: linear-gradient(to bottom right, #fff, #fff, rgba(165, 180, 252, 0.9));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }
</style>
<div id="landing-shell" class="min-h-screen bg-gray-900 text-white">
  <header class="border-b border-gray-800 bg-gray-900/90 backdrop-blur-md sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
      <a href="/" class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center p-1">
          <img src="/img/logo-satflux-white.svg" alt="" width="32" height="32" class="w-full h-full object-contain" aria-hidden="true">
        </div>
        <span class="text-xl font-bold text-white tracking-tight">SATFLUX</span>
      </a>
    </div>
  </header>

  <section class="relative overflow-hidden" aria-labelledby="landing-hero-heading">
    <div class="absolute inset-0 bg-gray-900" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24 lg:py-28">
      <div class="text-center lg:text-left max-w-3xl mx-auto lg:mx-0">
        <p class="font-mono text-xs uppercase tracking-[0.28em] text-orange-400/95 mb-5 leading-relaxed">
          {{ \App\Support\LandingCopy::get('landing.hero_kicker') }}
        </p>
        <div class="inline-flex items-center px-3.5 py-1.5 rounded-full border border-indigo-500/35 bg-indigo-500/10 text-indigo-200/95 text-sm font-medium mb-6">
          {{ \App\Support\LandingCopy::get('landing.hero_badge') }}
        </div>
        <h1 id="landing-hero-heading" class="text-4xl sm:text-5xl xl:text-[3.35rem] xl:leading-[1.08] font-extrabold tracking-tight mb-5">
          <span class="sf-hero-gradient">{{ \App\Support\LandingCopy::get('landing.hero_headline') }}</span>
        </h1>
        <p class="text-lg sm:text-xl text-indigo-200/90 font-medium mb-8 max-w-2xl mx-auto lg:mx-0 leading-snug">
          {{ \App\Support\LandingCopy::get('landing.hero_subheadline') }}
        </p>
        <a href="#how-it-works" class="inline-flex items-center justify-center px-7 py-3.5 text-base font-semibold rounded-xl text-indigo-200 border border-indigo-500/35 bg-indigo-500/5">
          {{ \App\Support\LandingCopy::get('landing.hero_cta_how') }}
        </a>
      </div>
    </div>
    </section>
  {{-- Scroll target for hero_cta_how (#how-it-works) before Vue hydration --}}
  <div id="how-it-works" hidden aria-hidden="true"></div>
</div>
