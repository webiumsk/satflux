export type CompanyEfakturaSettingsState = {
  efaktura_enabled: boolean;
  efaktura_auto_send: boolean;
  efaktura_inbound_enabled: boolean;
  efaktura_sapi_base_url: string;
  efaktura_peppol_participant_id: string;
  efaktura_sapi_client_id: string;
  efaktura_sapi_client_secret: string;
};

export function defaultEfakturaSettings(): CompanyEfakturaSettingsState {
  return {
    efaktura_enabled: false,
    efaktura_auto_send: false,
    efaktura_inbound_enabled: false,
    efaktura_sapi_base_url: '',
    efaktura_peppol_participant_id: '',
    efaktura_sapi_client_id: '',
    efaktura_sapi_client_secret: '',
  };
}

export function efakturaSettingsFromCompany(company: Record<string, unknown> | null): CompanyEfakturaSettingsState {
  const raw = (company?.app_settings ?? {}) as Record<string, unknown>;
  const base = defaultEfakturaSettings();

  return {
    ...base,
    efaktura_enabled: Boolean(raw.efaktura_enabled),
    efaktura_auto_send: Boolean(raw.efaktura_auto_send),
    efaktura_inbound_enabled: Boolean(raw.efaktura_inbound_enabled),
    efaktura_sapi_base_url: String(raw.efaktura_sapi_base_url ?? ''),
    efaktura_peppol_participant_id: String(raw.efaktura_peppol_participant_id ?? ''),
    efaktura_sapi_client_id: String(raw.efaktura_sapi_client_id ?? ''),
    efaktura_sapi_client_secret: '',
  };
}

export function efakturaSecretIsSet(company: Record<string, unknown> | null): boolean {
  const raw = (company?.app_settings ?? {}) as Record<string, unknown>;
  if (raw.efaktura_sapi_client_secret_set === true) {
    return true;
  }

  const encrypted = raw.efaktura_sapi_client_secret_encrypted;
  return typeof encrypted === 'string' && encrypted !== '';
}
