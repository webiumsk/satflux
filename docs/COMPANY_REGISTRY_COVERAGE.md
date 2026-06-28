# Company registry lookup coverage

Satflux loads company/contact data from public registries when creating a firm or contact.

## Providers

| Provider | Countries | Backend |
|----------|-----------|---------|
| **subjekt.sk** | SK, CZ | `SubjektRegistryService` |
| **OpenRegistry** (proxy) | PL, FR, IT, ES, NL, BE, CH, IE, GB, FI, CY, HK | `OpenRegistryService` |
| **Manual** | US, DE, AT, HU, PT, GI, PA, KY, … | no search API |

**VIES** (EU VAT ID validation) is separate and always available where applicable.

## API

- `GET /api/invoicing/company-registry/coverage` - options for the UI dropdown
- `GET /api/invoicing/company-registry/search?q=&country=` - autocomplete
- `GET /api/invoicing/company-registry/entities/{id}?country=` - detail (full for SK/CZ; OpenRegistry profile also needs bearer token)

## Configuration

```env
# SK/CZ (default)
SUBJEKT_REGISTRY_BASE_URL=https://api.subjekt.sk/v1

# OpenRegistry (EU + HK autocomplete and profile)
OPENREGISTRY_ENABLED=true
OPENREGISTRY_BASE_URL=https://openregistry.sophymarine.com/api/v1
# Required for server-side autocomplete (GET /api/v1/companies). Create at https://openregistry.sophymarine.com/login
OPENREGISTRY_BEARER_TOKEN=
```

OpenRegistry **search** uses the authenticated REST endpoint `GET /api/v1/companies?q=&jurisdiction=` with `OPENREGISTRY_BEARER_TOKEN`. The legacy anonymous `/api/v1/search` path no longer works from the server (redirects to login). After changing `.env`, run `php artisan optimize:clear` (or the Docker equivalent).

Without a token, autocomplete for OpenRegistry countries returns empty results and the UI shows a registry-unavailable hint. Selecting a row still fills name, registry ID, and city/address line from the search hit when search succeeds.

## UI

Register dropdown groups: Central EU, Western EU, Other EU/EEA, UK & offshore, Americas, Asia.

Countries marked `*` in the dropdown have **no autocomplete** (manual + hints).

## Wyoming LLC (US)

US is manual only: WY filing ID, EIN, state, ZIP. No paid OpenCorporates integration.

## Adding a country

1. Add to `CompanyRegistryCoverage` in `app/Support/Invoicing/CompanyRegistryCoverage.php`
2. If OpenRegistry supports it, add to `OPEN_REGISTRY` and map in frontend `DEFAULT_REGISTRY_OPTIONS`
3. Add PHPUnit test with `Http::fake` for the upstream URL
4. Add i18n label under `invoicing.country_*` if needed
