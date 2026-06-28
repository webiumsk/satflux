/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_NAME: string;
  readonly VITE_CHORALA_WIDGET_URL?: string;
  readonly VITE_CHORALA_API_URL?: string;
  readonly VITE_PUSHER_APP_KEY?: string;
  readonly VITE_PUSHER_APP_CLUSTER?: string;
  readonly VITE_REVERB_APP_KEY?: string;
  readonly VITE_REVERB_HOST?: string;
  readonly VITE_REVERB_PORT?: string;
  readonly VITE_REVERB_SCHEME?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
