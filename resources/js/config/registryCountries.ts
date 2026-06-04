export type RegistryCountryOption = {
  value: string;
  label: string;
  group: string;
  provider: 'subjekt' | 'openregistry' | 'manual';
  autocomplete: boolean;
};

export const REGISTRY_COUNTRY_GROUPS: Record<string, string> = {
  central_eu: 'registry_group_central_eu',
  western_eu: 'registry_group_western_eu',
  other_eu_eea: 'registry_group_other_eu_eea',
  uk_offshore: 'registry_group_uk_offshore',
  americas: 'registry_group_americas',
  asia: 'registry_group_asia',
};

/** Fallback when /company-registry/coverage is not loaded yet. */
export const DEFAULT_REGISTRY_OPTIONS: RegistryCountryOption[] = [
  { value: 'sk', label: 'SK', group: 'central_eu', provider: 'subjekt', autocomplete: true },
  { value: 'cz', label: 'CZ', group: 'central_eu', provider: 'subjekt', autocomplete: true },
  { value: 'pl', label: 'PL', group: 'central_eu', provider: 'openregistry', autocomplete: true },
  { value: 'fr', label: 'FR', group: 'western_eu', provider: 'openregistry', autocomplete: true },
  { value: 'nl', label: 'NL', group: 'western_eu', provider: 'openregistry', autocomplete: true },
  { value: 'be', label: 'BE', group: 'western_eu', provider: 'openregistry', autocomplete: true },
  { value: 'es', label: 'ES', group: 'western_eu', provider: 'openregistry', autocomplete: true },
  { value: 'it', label: 'IT', group: 'western_eu', provider: 'openregistry', autocomplete: true },
  { value: 'de', label: 'DE', group: 'western_eu', provider: 'manual', autocomplete: false },
  { value: 'at', label: 'AT', group: 'western_eu', provider: 'manual', autocomplete: false },
  { value: 'pt', label: 'PT', group: 'western_eu', provider: 'manual', autocomplete: false },
  { value: 'hu', label: 'HU', group: 'western_eu', provider: 'manual', autocomplete: false },
  { value: 'ch', label: 'CH', group: 'other_eu_eea', provider: 'openregistry', autocomplete: true },
  { value: 'ie', label: 'IE', group: 'other_eu_eea', provider: 'openregistry', autocomplete: true },
  { value: 'fi', label: 'FI', group: 'other_eu_eea', provider: 'openregistry', autocomplete: true },
  { value: 'cy', label: 'CY', group: 'other_eu_eea', provider: 'openregistry', autocomplete: true },
  { value: 'gb', label: 'GB', group: 'uk_offshore', provider: 'openregistry', autocomplete: true },
  { value: 'gi', label: 'GI', group: 'uk_offshore', provider: 'manual', autocomplete: false },
  { value: 'ky', label: 'KY', group: 'uk_offshore', provider: 'manual', autocomplete: false },
  { value: 'pa', label: 'PA', group: 'uk_offshore', provider: 'manual', autocomplete: false },
  { value: 'us', label: 'US', group: 'americas', provider: 'manual', autocomplete: false },
  { value: 'hk', label: 'HK', group: 'asia', provider: 'openregistry', autocomplete: true },
];

export function registrySupportsAutocomplete(
  value: string,
  options: RegistryCountryOption[] = DEFAULT_REGISTRY_OPTIONS
): boolean {
  const opt = options.find((o) => o.value === value);
  return opt?.autocomplete ?? false;
}

export function registryOptionForCountry(
  isoOrRegistry: string,
  options: RegistryCountryOption[] = DEFAULT_REGISTRY_OPTIONS
): RegistryCountryOption | undefined {
  const v = isoOrRegistry.toLowerCase();
  return options.find((o) => o.value === v) ?? options.find((o) => o.value === v.slice(0, 2));
}

export function defaultRegistryForJurisdiction(jurisdiction: string, country?: string): string {
  if (jurisdiction === 'eu_sk') return 'sk';
  if (jurisdiction === 'eu_cz') return 'cz';
  if (jurisdiction === 'us') return 'us';
  if (jurisdiction === 'uk') return 'gb';
  if (jurisdiction === 'asia') return 'hk';
  if (jurisdiction === 'offshore') return 'gi';
  const c = (country || '').toUpperCase();
  if (c === 'CZ') return 'cz';
  if (c === 'SK') return 'sk';
  if (c === 'US') return 'us';
  if (c === 'GB' || c === 'UK') return 'gb';
  if (c && registrySupportsAutocomplete(c.toLowerCase())) return c.toLowerCase();
  if (c) return c.toLowerCase();
  return 'sk';
}
