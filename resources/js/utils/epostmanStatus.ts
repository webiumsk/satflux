import { formatPeppolEndpoint, resolvePeppolEndpoint } from './peppolEndpoint';

export function normalizeEfakturaUrl(value: unknown): string {
  const text = String(value ?? '').trim().replace(/\/+$/, '');
  try { return new URL(text).href.replace(/\/+$/, ''); } catch { return text; }
}

/** Compare connection identity in memory; never persist a credential fingerprint. */
export function epostmanConnectionIdentity(company: Record<string, unknown> | null, settings?: Record<string, unknown>): string {
  const raw = settings ?? (company?.app_settings ?? {}) as Record<string, unknown>;
  const derived = formatPeppolEndpoint(resolvePeppolEndpoint({
    tax_id: company?.tax_id as string | null,
    registration_number: company?.registration_number as string | null,
    country: company?.country as string | null,
    jurisdiction: company?.jurisdiction as string | null,
  }));
  return JSON.stringify([normalizeEfakturaUrl(raw.efaktura_sapi_base_url),
    String(raw.efaktura_peppol_participant_id ?? '').trim(), derived]);
}

export function isValidEpostmanReplacementKey(value: unknown): boolean {
  return value === undefined || value === null || value === ''
    || (typeof value === 'string' && value.trim() !== '' && /^[\x21-\x7e]{1,4096}$/.test(value));
}

export function epostmanKeyIsSet(company: Record<string, unknown> | null): boolean {
  const raw = (company?.app_settings ?? {}) as Record<string, unknown>;
  return raw.efaktura_epostman_api_key_set === true && raw.efaktura_epostman_key_save_pending !== true;
}

/** The Enterprise secret belongs only to the owner-authorized server bridge. */
export function withoutLocalEpostmanSecrets(settings: Record<string, unknown>): Record<string, unknown> {
  const clean = { ...settings };
  delete clean.efaktura_epostman_api_key;
  delete clean.efaktura_epostman_api_key_encrypted;
  delete clean.efaktura_epostman_key_identity;
  delete clean.efaktura_epostman_status_generation;
  delete clean.efaktura_epostman_status_last_scan_at;
  return clean;
}

const statuses = ['PENDING', 'VALIDATING', 'VALIDATED', 'VALIDATION_FAILED', 'READY', 'SENDING',
  'DELIVERED', 'DELIVERED_NON_PEPPOL', 'ACCEPTED', 'REJECTED', 'CANCELLED', 'UNDELIVERABLE',
  'UNCONFIRMED', 'FAILED', 'RECEIVED', 'ACKNOWLEDGED', 'AP', 'RE', 'AB', 'CA', 'IP', 'OK', 'NOK',
  'ERROR', 'SUCCESS', 'PROCESSED', 'TIMEOUT', 'OTHER'] as const;
export type EpostmanStatus = typeof statuses[number] | 'UNKNOWN';
export function epostmanStatus(value: unknown): EpostmanStatus {
  if (value === null || value === undefined || value === '') return 'UNKNOWN';
  return typeof value === 'string' && (statuses as readonly string[]).includes(value)
    ? value as EpostmanStatus : 'OTHER';
}

export function enterpriseStatusFromRaw(value: unknown) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
  const raw = value as Record<string, unknown>;
  const date = (input: unknown) => typeof input === 'string' && Number.isFinite(Date.parse(input)) ? input : null;
  return {
    status: epostmanStatus(raw.status), c3: epostmanStatus(raw.mls_c3_status), c5: epostmanStatus(raw.mls_c5_status),
    checkedAt: date(raw.checked_at), lastAttemptAt: date(raw.last_attempt_at), nextCheckAt: date(raw.next_check_at),
    // Error strings are mapped to a fixed UI message; never render provider text.
    error: typeof raw.error_code === 'string' && raw.error_code !== ''
      ? ['projection_unavailable', 'configuration_error', 'authentication_failed', 'not_found', 'rate_limited', 'provider_unavailable',
        'response_invalid', 'identity_mismatch', 'network_error', 'binding_unavailable'].includes(raw.error_code)
        ? raw.error_code : 'check_failed' : null,
    bindingUnavailable: raw.error_code === 'binding_unavailable',
  };
}
