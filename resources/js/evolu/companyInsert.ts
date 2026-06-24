import { booleanToSqliteBoolean } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { CompanyJurisdictionValue } from "@/config/companyJurisdiction";
import { ensureDefaultLocalWarehouse } from "./warehouseCrud";
import type { EvoluWarehouseRow } from "./warehouseMap";
import {
    CountryType,
    CurrencyType,
    LegalNameType,
    Opt16,
    Opt32,
    Opt64,
    Opt128,
    Opt255,
    Opt512,
    parseOptional,
    parseRequired,
} from "./parseUtils";
import type { InvoicingLocalSchema } from "./schema";
import { stableCompanyIdFromIdentity } from "./companyStableId";

/** Payload aligned with CompanyForm save() / StoreCompanyRequest. */
export interface LocalCompanyCreatePayload {
    legal_name: string;
    store_id: string | null;
    jurisdiction: CompanyJurisdictionValue;
    default_currency: string;
    registration_number: string | null;
    tax_id: string | null;
    street: string | null;
    city: string | null;
    postal_code: string | null;
    state_region: string | null;
    country: string | null;
    bank_name: string | null;
    bank_account: string | null;
    bank_code: string | null;
    iban: string | null;
    bic: string | null;
    vat_number: string | null;
    commercial_register: string | null;
    vat_payer: boolean;
    vat_status: "none" | "payer" | "partial";
}

export function insertLocalCompanyFromPayload(
    evolu: Evolu<InvoicingLocalSchema>,
    payload: LocalCompanyCreatePayload,
) {
    const legalName = parseRequired(payload.legal_name, LegalNameType);
    if (!legalName.ok) return legalName;

    const defaultCurrency = parseRequired(payload.default_currency || "EUR", CurrencyType);
    if (!defaultCurrency.ok) return defaultCurrency;

    const country = parseOptional(payload.country, CountryType);
    if (!country.ok) return country;

    const registrationNumber = parseOptional(payload.registration_number, Opt64);
    if (!registrationNumber.ok) return registrationNumber;
    const taxId = parseOptional(payload.tax_id, Opt64);
    if (!taxId.ok) return taxId;
    const vatNumber = parseOptional(payload.vat_number, Opt32);
    if (!vatNumber.ok) return vatNumber;
    const commercialRegister = parseOptional(payload.commercial_register, Opt512);
    if (!commercialRegister.ok) return commercialRegister;
    const street = parseOptional(payload.street, Opt255);
    if (!street.ok) return street;
    const city = parseOptional(payload.city, Opt128);
    if (!city.ok) return city;
    const postalCode = parseOptional(payload.postal_code, Opt32);
    if (!postalCode.ok) return postalCode;
    const stateRegion = parseOptional(payload.state_region, Opt64);
    if (!stateRegion.ok) return stateRegion;
    const iban = parseOptional(payload.iban, Opt64);
    if (!iban.ok) return iban;
    const bic = parseOptional(payload.bic, Opt16);
    if (!bic.ok) return bic;
    const bankName = parseOptional(payload.bank_name, Opt128);
    if (!bankName.ok) return bankName;
    const bankAccount = parseOptional(payload.bank_account, Opt64);
    if (!bankAccount.ok) return bankAccount;
    const bankCode = parseOptional(payload.bank_code, Opt16);
    if (!bankCode.ok) return bankCode;
    const linkedStoreId = parseOptional(payload.store_id, Opt64);
    if (!linkedStoreId.ok) return linkedStoreId;

    const stableId = stableCompanyIdFromIdentity(
        legalName.value,
        registrationNumber.value,
    );
    if (!stableId) {
        return { ok: false as const, error: "invalid_company_id" };
    }

    const result = evolu.upsert("company", {
        id: stableId,
        legalName: legalName.value,
        tradeName: null,
        jurisdiction: payload.jurisdiction,
        defaultCurrency: defaultCurrency.value,
        registrationNumber: registrationNumber.value,
        taxId: taxId.value,
        vatNumber: vatNumber.value,
        commercialRegister: commercialRegister.value,
        street: street.value,
        city: city.value,
        postalCode: postalCode.value,
        country: country.value,
        stateRegion: stateRegion.value,
        iban: iban.value,
        bic: bic.value,
        bankName: bankName.value,
        bankAccount: bankAccount.value,
        bankCode: bankCode.value,
        vatPayer: booleanToSqliteBoolean(payload.vat_payer),
        vatStatus: payload.vat_status,
        vatRateDefault: null,
        legalFooterNote: null,
        issuerName: null,
        issuerPhone: null,
        issuerEmail: null,
        website: null,
        invoiceNumberPrefix: null,
        linkedStoreId: linkedStoreId.value,
    });
    if (!result.ok) return result;

    ensureDefaultLocalWarehouse(evolu, result.value.id, [] as EvoluWarehouseRow[]);
    return result;
}
