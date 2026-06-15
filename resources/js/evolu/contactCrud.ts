import { maxLength, NonEmptyString, sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { ContactFormState } from "@/composables/useCompanyContact";
import { contactPayloadFromForm } from "./contactMap";
import type { CompanyId, ContactId, InvoicingLocalSchema } from "./schema";

const NameType = maxLength(255)(NonEmptyString);
const Opt16 = maxLength(16)(NonEmptyString);
const Opt32 = maxLength(32)(NonEmptyString);
const Opt64 = maxLength(64)(NonEmptyString);
const Opt128 = maxLength(128)(NonEmptyString);
const Opt255 = maxLength(255)(NonEmptyString);
const Opt1000 = maxLength(1000)(NonEmptyString);
const Opt4000 = maxLength(4000)(NonEmptyString);
const EmailType = maxLength(100)(NonEmptyString);

function parseOpt(value: string | null, type: ReturnType<typeof maxLength>) {
    if (value == null || value.trim() === "") return { ok: true as const, value: null };
    return type.from(value.trim());
}

function mapPayloadFields(payload: ReturnType<typeof contactPayloadFromForm>) {
    const name = NameType.from(payload.name);
    if (!name.ok) return name;

    const registrationNumber = parseOpt(payload.registrationNumber, Opt64);
    if (!registrationNumber.ok) return registrationNumber;
    const peppol = parseOpt(payload.peppolParticipantId, Opt64);
    if (!peppol.ok) return peppol;
    const email = payload.email ? EmailType.from(payload.email) : { ok: true as const, value: null };
    if (!email.ok) return email;
    const phone = parseOpt(payload.phone, Opt64);
    if (!phone.ok) return phone;
    const fax = parseOpt(payload.fax, Opt64);
    if (!fax.ok) return fax;
    const taxId = parseOpt(payload.taxId, Opt64);
    if (!taxId.ok) return taxId;
    const vatId = parseOpt(payload.vatId, Opt32);
    if (!vatId.ok) return vatId;
    const street = parseOpt(payload.street, Opt255);
    if (!street.ok) return street;
    const city = parseOpt(payload.city, Opt128);
    if (!city.ok) return city;
    const postal = parseOpt(payload.postalCode, Opt32);
    if (!postal.ok) return postal;
    const state = parseOpt(payload.stateRegion, Opt64);
    if (!state.ok) return state;
    const country = parseOpt(payload.country, Opt128);
    if (!country.ok) return country;
    const bankAccount = parseOpt(payload.bankAccount, Opt64);
    if (!bankAccount.ok) return bankAccount;
    const bankCode = parseOpt(payload.bankCode, Opt16);
    if (!bankCode.ok) return bankCode;
    const iban = parseOpt(payload.iban, Opt64);
    if (!iban.ok) return iban;
    const swift = parseOpt(payload.swift, Opt16);
    if (!swift.ok) return swift;
    const deliveryStreet = parseOpt(payload.deliveryStreet, Opt255);
    if (!deliveryStreet.ok) return deliveryStreet;
    const deliveryPostal = parseOpt(payload.deliveryPostalCode, Opt32);
    if (!deliveryPostal.ok) return deliveryPostal;
    const deliveryCity = parseOpt(payload.deliveryCity, Opt128);
    if (!deliveryCity.ok) return deliveryCity;
    const deliveryCountry = parseOpt(payload.deliveryCountry, Opt128);
    if (!deliveryCountry.ok) return deliveryCountry;
    const terms = parseOpt(payload.defaultPaymentTermsDays, Opt16);
    if (!terms.ok) return terms;
    const notes = parseOpt(payload.notes, Opt1000);
    if (!notes.ok) return notes;
    const personsJson = parseOpt(payload.contactPersonsJson, Opt4000);
    if (!personsJson.ok) return personsJson;

    return {
        ok: true as const,
        value: {
            name: name.value,
            registrationNumber: registrationNumber.value,
            peppolParticipantId: peppol.value,
            email: email.value,
            phone: phone.value,
            fax: fax.value,
            taxId: taxId.value,
            vatId: vatId.value,
            street: street.value,
            city: city.value,
            postalCode: postal.value,
            stateRegion: state.value,
            country: country.value,
            bankAccount: bankAccount.value,
            bankCode: bankCode.value,
            iban: iban.value,
            swift: swift.value,
            deliveryStreet: deliveryStreet.value,
            deliveryPostalCode: deliveryPostal.value,
            deliveryCity: deliveryCity.value,
            deliveryCountry: deliveryCountry.value,
            defaultPaymentTermsDays: terms.value,
            notes: notes.value,
            contactPersonsJson: personsJson.value,
            isActive: payload.isActive,
        },
    };
}

export function insertLocalContactFromForm(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    form: ContactFormState,
    includeDelivery: boolean,
) {
    const payload = contactPayloadFromForm(form, includeDelivery);
    const mapped = mapPayloadFields(payload);
    if (!mapped.ok) return mapped;

    return evolu.insert("contact", {
        companyId,
        ...mapped.value,
    });
}

export function updateLocalContactFromForm(
    evolu: Evolu<InvoicingLocalSchema>,
    contactId: ContactId,
    form: ContactFormState,
    includeDelivery: boolean,
) {
    const payload = contactPayloadFromForm(form, includeDelivery);
    const mapped = mapPayloadFields(payload);
    if (!mapped.ok) return mapped;

    return evolu.update("contact", {
        id: contactId,
        ...mapped.value,
    });
}

export function deleteLocalContact(
    evolu: Evolu<InvoicingLocalSchema>,
    contactId: ContactId,
) {
    return evolu.update("contact", { id: contactId, isDeleted: sqliteTrue });
}
