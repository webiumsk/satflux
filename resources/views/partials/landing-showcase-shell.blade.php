{{-- First paint only; the selected Vue homepage removes this shell after mounting. --}}
<style>
  #landing-shell.sf-showcase-shell { background: #101119; color: #f7f7fc; font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif; min-height: 100dvh; line-height: 1.6; }
  #landing-shell .sf-showcase-wrap { width: min(1180px, calc(100% - 64px)); margin: 0 auto; }
  #landing-shell .sf-showcase-hero { padding: 76px 0 64px; }
  #landing-shell .sf-showcase-grid { display: grid; grid-template-columns: 1fr 1.18fr; gap: 52px; align-items: center; }
  #landing-shell .sf-showcase-eyebrow { display: flex; align-items: center; gap: 8px; color: #ffa46c; font-size: 11px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; margin: 0 0 20px; }
  #landing-shell .sf-showcase-dot { width: 6px; height: 6px; border-radius: 50%; background: #ffa46c; }
  #landing-shell .sf-showcase-title { font-size: clamp(38px, 4.3vw, 58px); font-weight: 700; letter-spacing: -.045em; line-height: 1.1; margin: 0 0 22px; }
  #landing-shell .sf-showcase-title span { background: linear-gradient(135deg, #fff 40%, #c5baff 100%); background-clip: text; -webkit-background-clip: text; color: transparent; }
  #landing-shell .sf-showcase-intro { color: #b2b3c5; font-size: 16px; line-height: 1.7; max-width: 460px; margin: 0 0 28px; }
  #landing-shell .sf-showcase-actions { display: flex; flex-wrap: wrap; gap: 12px; }
  #landing-shell .sf-showcase-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; gap: 9px; padding: 12px 20px; border: 1px solid #444356; border-radius: 10px; font-weight: 650; font-size: 13px; line-height: 1.4; text-decoration: none; color: #e8e6f4; background: #ffffff0a; }
  #landing-shell .sf-showcase-primary { color: #171326; background: #a49bff; border-color: transparent; }
  #landing-shell .sf-showcase-note { color: #9ea1b5; font-size: 12px; margin-top: 18px; }
  #landing-shell .sf-showcase-powered { display: flex; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 34px; color: #85889a; font-size: 11px; }
  #landing-shell .sf-showcase-powered strong { font-size: 12px; color: #b6bbca; font-weight: 650; }
  #landing-shell .sf-showcase-preview { min-width: 0; border: 1px solid #4a4560; border-radius: 18px; background: #181a25; overflow: hidden; }
  #landing-shell .sf-showcase-preview-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 12px 18px; background: #252637; border-bottom: 1px solid #ffffff12; }
  #landing-shell .sf-showcase-preview-tabs { display: flex; gap: 6px; padding: 3px; background: #00000059; border-radius: 8px; font-size: 11px; font-weight: 600; }
  #landing-shell .sf-showcase-preview-tab { display: inline-flex; align-items: center; min-height: 44px; padding: 6px 13px; border: 1px solid transparent; color: #a29cb8; border-radius: 6px; }
  #landing-shell .sf-showcase-preview-active { color: #fff; background: #a49bff2e; border-color: #a49bff59; }
  #landing-shell .sf-showcase-preview-status { font-size: 10px; color: #8be0bb; }
  #landing-shell .sf-showcase-preview-stage { padding: 12px; background: #161724; }
  #landing-shell .sf-showcase-preview img { display: block; width: 100%; height: auto; border: 1px solid #47405c; border-radius: 12px; }
  #landing-shell .sf-showcase-caption { padding: 12px 18px; font-size: 11px; color: #a8abba; border-top: 1px solid #2c2e3e; }
  @media (max-width: 1024px) { #landing-shell .sf-showcase-grid { grid-template-columns: 1fr; gap: 42px; } }
  @media (max-width: 768px) { #landing-shell .sf-showcase-wrap { width: calc(100% - 36px); } #landing-shell .sf-showcase-hero { padding: 48px 0 40px; } }
  @media (max-width: 420px) { #landing-shell .sf-showcase-preview-head { flex-wrap: wrap; } #landing-shell .sf-showcase-preview-status { margin-left: auto; } #landing-shell .sf-showcase-preview-tabs { flex-wrap: wrap; } }
</style>
<div id="landing-shell" class="sf-showcase-shell">
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
  <section class="sf-showcase-hero" aria-labelledby="hero-title">
    <div class="sf-showcase-wrap sf-showcase-grid">
      <div>
        <p class="sf-showcase-eyebrow"><span class="sf-showcase-dot" aria-hidden="true"></span>{{ \App\Support\LandingCopy::get('showcase.hero.eyebrow') }}</p>
        <h1 id="hero-title" class="sf-showcase-title">
          {{ \App\Support\LandingCopy::get('showcase.hero.line1') }}<br>
          {{ \App\Support\LandingCopy::get('showcase.hero.line2') }}<br>
          <span>{{ \App\Support\LandingCopy::get('showcase.hero.line3') }}</span>
        </h1>
        <p class="sf-showcase-intro">{{ \App\Support\LandingCopy::get('showcase.hero.intro') }}</p>
        <div class="sf-showcase-actions">
          <a href="/register" class="sf-showcase-button sf-showcase-primary">{{ \App\Support\LandingCopy::get('showcase.hero.start') }} <span aria-hidden="true">↗</span></a>
          <a href="#how-it-works-pos" class="sf-showcase-button"><span aria-hidden="true">⚡</span>{{ \App\Support\LandingCopy::get('showcase.hero.demo') }}</a>
        </div>
        <p class="sf-showcase-note">{{ \App\Support\LandingCopy::get('showcase.hero.note') }}</p>
        <div class="sf-showcase-powered">
          <span>{{ \App\Support\LandingCopy::get('showcase.hero.powered') }}</span>
          <strong>BTCPay Server + Evolu</strong>
          <span>{{ \App\Support\LandingCopy::get('showcase.hero.open_source') }}</span>
        </div>
      </div>
      <div id="how-it-works-pos" class="sf-showcase-preview" aria-hidden="true">
        <div class="sf-showcase-preview-head">
          <div class="sf-showcase-preview-tabs">
            <span class="sf-showcase-preview-tab">⚡ {{ \App\Support\LandingCopy::get('showcase.ui.live_pos') }}</span>
            <span class="sf-showcase-preview-tab sf-showcase-preview-active">▥ {{ \App\Support\LandingCopy::get('showcase.ui.overview') }}</span>
          </div>
          <span class="sf-showcase-preview-status">{{ \App\Support\LandingCopy::get('showcase.ui.sample') }}</span>
        </div>
        <div class="sf-showcase-preview-stage"><img src="/img/landing-showcase/satflux-overview-v6.png" alt="" width="2160" height="1500" fetchpriority="high"></div>
        <p class="sf-showcase-caption">{{ \App\Support\LandingCopy::get('showcase.ui.sample_caption') }}</p>
      </div>
    </div>
  </section>
</div>
