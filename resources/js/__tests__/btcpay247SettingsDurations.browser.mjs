// Standalone synthetic Chrome regression. No Laravel server, credentials or .env.
// Run: node resources/js/__tests__/btcpay247SettingsDurations.browser.mjs
// BROWSER_EXECUTABLE_PATH may select an existing system Chromium installation.
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from '@playwright/test';

async function main() {
const root = fileURLToPath(new URL('../../..', import.meta.url));
const stub = '\0duration-test-stub';
const harness = `
import { createApp } from 'vue';
import StoreSettings from '/resources/js/components/stores/StoreSettings.vue';
createApp(StoreSettings, { store: { id: 'synthetic-local-uuid' } }).mount('#app');
`;
const stubs = `
const query = new URLSearchParams(location.search);
window.durationUpdates = [];
export const useI18n = () => ({ t: key => key });
export const useRouter = () => ({});
export const useRoute = () => ({ query: {} });
export const useStoresStore = () => ({});
export const useFlashStore = () => ({ clear() {}, success() {}, error() {} });
export const useEffectiveAccess = () => ({ hasFeature: () => query.get('editable') !== 'false' });
export const storesApi = { settings: {
  get: async () => ({ name: 'Synthetic store', invoice_expiration: Number(query.get('invoice') ?? 900), monitoring_expiration: Number(query.get('monitoring') ?? 86400) }),
  update: async (_id, payload) => { window.durationUpdates.push(JSON.parse(JSON.stringify(payload))); },
} };
export default { render: () => null };
`;
const cacheDir = mkdtempSync(join(tmpdir(), 'satflux-duration-browser-'));
const server = await createServer({
  cacheDir,
  root,
  configFile: false,
  envFile: false,
  logLevel: 'error',
  server: { host: '127.0.0.1', port: 0 },
  resolve: { alias: { '@': `${root}/resources/js` } },
  plugins: [{
    name: 'synthetic-duration-test',
    enforce: 'pre',
    resolveId(id) {
      if (id === '/duration-harness.js') return '\0duration-harness';
      if (['vue-i18n', 'vue-router'].includes(id)
        || /(?:^|\/)useEffectiveAccess$/.test(id)
        || /(?:store\/(?:stores|flash)|services\/api)$/.test(id)
        || /(?:StoreSettings(?:General|Rates|Checkout|Lightning|EmailRules)|UpgradeModal)\.vue$/.test(id)) return stub;
    },
    load(id) {
      if (id === stub) return stubs;
      if (id === '\0duration-harness') return harness;
    },
    configureServer(vite) {
      vite.middlewares.use((req, res, next) => {
        if (req.url?.split('?')[0] !== '/') return next();
        res.setHeader('Content-Type', 'text/html');
        res.end('<div id="app"></div><script type="module" src="/duration-harness.js"></script>');
      });
    },
  }, vue()],
});
let browser;
let assertions = 0;
try {
  await server.listen();
  browser = await chromium.launch({ executablePath: process.env.BROWSER_EXECUTABLE_PATH || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const origin = server.resolvedUrls.local[0];
  const check = (actual, expected, message) => { assert.deepEqual(actual, expected, message); assertions++; };
  async function load({ invoice = 900, monitoring = 86400, editable = true } = {}) {
    await page.goto(`${origin}?invoice=${invoice}&monitoring=${monitoring}&editable=${editable}`);
    await page.locator('form').waitFor();
  }
  async function tab(name) {
    await page.getByRole('button', { name: `stores.settings_tab_${name}`, exact: true }).click();
  }
  async function submit() {
    await page.locator('form button[type=submit]').click();
    await page.waitForFunction(() => !document.querySelector('form button[type=submit]').disabled);
  }
  async function updates() { return page.evaluate(() => window.durationUpdates); }
  async function validity() { return page.locator('form').evaluate(form => form.checkValidity()); }
  for (const name of ['general', 'rates', 'checkout', 'lightning']) {
    await load({ invoice: 2073660, monitoring: 0 });
    await tab(name);
    check(await page.locator('#monitoring_expiration').isDisabled(), true, `${name}: hidden monitoring disabled`);
    check(await page.locator('#invoice_expiration').isDisabled(), true, `${name}: hidden invoice disabled`);
    check(await validity(), true, `${name}: hidden invalid durations do not block form`);
    await submit();
    const payloads = await updates();
    check(payloads.length, 1, `${name}: real click submits`);
    check('monitoring_expiration' in payloads[0], false, `${name}: invalid monitoring omitted`);
    check('invoice_expiration' in payloads[0], false, `${name}: invalid invoice omitted`);
    await tab('payment');
    check(await page.locator('#monitoring_expiration').inputValue(), '0', 'legacy value preserved');
    check(await validity(), false, 'visible legacy value requires correction');
    await submit();
    check((await updates()).length, 1, 'invalid active payment never submits');
  }
  for (const name of ['general', 'payment']) {
    await load({ invoice: 0, monitoring: 0, editable: false });
    await tab(name);
    check(await validity(), true, 'inaccessible duration controls cannot block form');
    check(await page.locator('#monitoring_expiration').isDisabled(), true, 'inaccessible monitoring disabled');
    check(await page.locator('#invoice_expiration').isDisabled(), true, 'inaccessible invoice disabled');
    await submit();
    const payloads = await updates();
    check(payloads.length, 1, 'inaccessible invalid durations permit unrelated save');
    for (const field of ['invoice_expiration', 'monitoring_expiration', 'network_fee_mode', 'payment_tolerance', 'refund_bolt11_expiration', 'speed_policy', 'lightning_description_template']) {
      check(field in payloads[0], false, `permission strips ${field}`);
    }
  }
  for (const name of ['general', 'payment']) {
    for (const [invoiceMinutes, monitorMinutes] of [[1, 10], [34560, 34560], [30, 60]]) {
      await load({ invoice: invoiceMinutes * 60, monitoring: monitorMinutes * 60 });
      await tab(name);
      check(await page.locator('#invoice_expiration').inputValue(), String(invoiceMinutes), 'invoice seconds hydrate as minutes');
      check(await page.locator('#monitoring_expiration').inputValue(), String(monitorMinutes), 'monitoring seconds hydrate as minutes');
      check(await validity(), true, 'valid boundary/wider values accepted');
      await submit();
      const payloads = await updates();
      check(payloads.length, 1, 'valid form submits');
      check(payloads[0].invoice_expiration, invoiceMinutes * 60, 'invoice converted back exactly once');
      check(payloads[0].monitoring_expiration, monitorMinutes * 60, 'monitoring converted back exactly once');
    }
  }
  for (const [field, min] of [['invoice_expiration', 1], ['monitoring_expiration', 10]]) {
    await load();
    await tab('payment');
    check(await page.locator(`#${field}`).isEnabled(), true, 'active editable input enabled');
    for (const value of [String(min - 1), '34561', String(min + 0.5)]) {
      await page.locator(`#${field}`).fill(value);
      check(await validity(), false, `${field}: rejects ${JSON.stringify(value)}`);
      await submit();
      check((await updates()).length, 0, 'native invalid form prevents Vue/API submit');
    }
    await page.locator(`#${field}`).fill(String(min));
    await submit();
    check((await updates())[0][field], min * 60, 'corrected value submits seconds');
    await page.locator(`#${field}`).fill('');
    check(await validity(), true, 'clearing the optional active duration remains allowed');
    await submit();
    check((await updates()).length, 2, 'cleared active duration submits');
    check((await updates())[1][field], '', 'cleared duration preserves existing nullable HTTP input');
    await tab('general');
    await submit();
    check((await updates()).length, 3, 'cleared inactive duration submits');
    check((await updates())[2][field], '', 'inactive nullable duration retained');
  }
  check(errors, [], 'browser runtime has no errors');
  console.log(`Chrome shared-form regression: ${assertions} assertions passed`);
} finally {
  await browser?.close();
  await server.close();
  rmSync(cacheDir, { recursive: true, force: true });
}

}
main().catch(error => { console.error(error); process.exitCode = 1; });
