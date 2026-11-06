import { readonly, ref } from 'vue';
import api from '../services/api';

const version = ref<string | null>(null);
let inFlight: Promise<void> | null = null;

/** Load the optional public version once per document, sharing concurrent requests and allowing retries after failure. */
function loadVersion(): Promise<void> {
  if (version.value !== null) return Promise.resolve();
  if (inFlight) return inFlight;

  inFlight = Promise.resolve().then(async () => {
    try {
      const { data } = await api.get<{ version?: unknown }>('/version');
      if (typeof data.version === 'string' && data.version.trim() !== '') {
        version.value = data.version;
      }
    } catch {
      // Version display is optional; an unsuccessful request must not prevent a later mount from retrying.
    }
  }).finally(() => {
    inFlight = null;
  });

  return inFlight;
}

/** Share only public release metadata across footer mounts; authentication and merchant data are never cached here. */
export function useAppVersion() {
  return { version: readonly(version), load: loadVersion };
}
