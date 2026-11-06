import type { AppRef, RawAppConfig, RawPosItem } from '../types/btcpayApps';

const booleanSettings = ['showItems', 'showCustomAmount', 'showDiscount', 'showSearch', 'showCategories', 'enableTips'] as const;

/** Recognize explicit boolean values without treating missing or malformed data as false. */
function isBooleanSetting(value: unknown): boolean {
  return typeof value === 'boolean'
    || (typeof value === 'number' && Number.isFinite(value))
    || (typeof value === 'string' && ['true', 'false', '1', '0', 'yes', 'no'].includes(value.trim().toLowerCase()));
}

/** Read a supported catalog shape, distinguishing a confirmed empty list from an unreadable catalog. */
export function readPosProducts(config: RawAppConfig): RawPosItem[] | null {
  let source = Object.prototype.hasOwnProperty.call(config, 'items') ? config.items : config.template;
  if (typeof source === 'string') {
    try {
      source = JSON.parse(source);
    } catch {
      return null;
    }
  }
  if (!Array.isArray(source)) return null;
  if (!source.every(item => item !== null && typeof item === 'object' && !Array.isArray(item)
    && typeof item.title === 'string' && item.title.trim() !== '')) return null;
  return source as RawPosItem[];
}

/** Treat explicit server read diagnostics as an incomplete configuration, including malformed diagnostics. */
export function hasPosConfigReadErrors(app: AppRef | null | undefined): boolean {
  const errors = app?.config_read_errors;
  return errors !== undefined && (!Array.isArray(errors) || errors.length > 0);
}

/** Require the supported detail contract before the editor can replace any settings or products. */
export function canEditPosConfig(app: AppRef | null | undefined): boolean {
  const config = app?.config;
  if (hasPosConfigReadErrors(app)) return false;
  return !!config
    && ['Light', 'Static', 'Cart', 'Print'].includes(config.defaultView ?? '')
    && booleanSettings.every(key => isBooleanSetting(config[key]))
    && readPosProducts(config) !== null;
}

const preservedSettings = [
  'title', 'description', 'currency', 'tipsMessage', 'defaultTaxRate', 'tipTaxRate',
  'taxIncludedInPrice', 'requestCustomerData', 'fixedAmountPayButtonText', 'customAmountPayButtonText',
  'htmlLang', 'htmlMetaTags', 'redirectUrl', 'redirectAutomatically', 'notificationUrl',
] as const;

/** Raw known settings and their rendered values from one successful editor hydration. */
export interface PosSettingsSnapshot {
  rendered: Record<string, unknown>;
  values: Record<string, unknown>;
}

/** Capture known submitted values, allowing confirmed response fields to supersede them without exposing other settings. */
export function capturePosSettings(config: RawAppConfig, form: Record<string, unknown>, confirmed?: RawAppConfig): PosSettingsSnapshot {
  const snapshot: PosSettingsSnapshot = { rendered: {}, values: {} };
  for (const key of preservedSettings) {
    snapshot.rendered[key] = form[key];
    for (const source of confirmed ? [config, confirmed] : [config]) {
      const sourceKey = key === 'tipsMessage' && Object.prototype.hasOwnProperty.call(source, 'tipText') ? 'tipText' : key;
      if (Object.prototype.hasOwnProperty.call(source, sourceKey)) snapshot.values[key] = source[sourceKey];
    }
  }
  return snapshot;
}

/** Restore unchanged known scalars while retaining explicit edits, normalized booleans and entitlement omissions. */
export function preserveUnchangedPosSettings(payload: Record<string, unknown>, form: Record<string, unknown>, snapshot: PosSettingsSnapshot | null): void {
  if (!snapshot) return;
  for (const key of preservedSettings) {
    if (!Object.prototype.hasOwnProperty.call(payload, key) || !Object.is(form[key], snapshot.rendered[key])) continue;
    const original = snapshot.values[key];
    if (original === undefined) {
      delete payload[key];
    } else if ((key !== 'taxIncludedInPrice' && key !== 'redirectAutomatically') || original === null) {
      payload[key] = original;
    }
  }
}
